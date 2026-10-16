<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sela o PDF final de cada documento concluído com o e-CNPJ ICP-Brasil da plataforma.
 * O selo garante a integridade do arquivo; as assinaturas das pessoas continuam eletrônicas.
 */
class PlatformSealService
{
    protected const REASON = 'Selo de integridade Moduhub';

    private ?IcpCertificate $certificate = null;

    public function __construct(
        protected IcpCertificateService $icp,
        protected PdfComposer $composer,
        protected PdfSigner $signer,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('signatures.seal.certificate_password'));
    }

    /**
     * Monta o PDF final do documento e, com o certificado configurado, aplica o selo.
     * Falha no selo não impede a conclusão: o último signatário já assinou e não pode
     * ser barrado por um problema da plataforma. O documento sai sem selo e o erro vai para o log.
     *
     * @return bool se o documento foi selado
     */
    public function finalize(Document $document): bool
    {
        if (! $this->isConfigured()) {
            $this->buildUnsealed($document);

            return false;
        }

        $tempAbsolute = null;

        try {
            $cert = $this->certificate();
            $relative = $this->composer->buildWorkingPdf($document, $cert);
            $absolute = Storage::disk($document->file_disk)->path($relative);
            $tempAbsolute = $absolute.'.seal.tmp';

            $this->signer->signWithPkcs12($absolute, $tempAbsolute, $cert, self::REASON);

            if (! is_file($tempAbsolute) || filesize($tempAbsolute) < 1024) {
                throw new RuntimeException('O PDF selado saiu vazio.');
            }

            rename($tempAbsolute, $absolute);
        } catch (Throwable $e) {
            if ($tempAbsolute !== null) {
                @unlink($tempAbsolute);
            }

            Log::critical('Documento concluído sem o selo ICP-Brasil da plataforma', [
                'document_id' => $document->getKey(),
                'error' => $e->getMessage(),
            ]);

            $this->buildUnsealed($document);

            return false;
        }

        $document->forceFill([
            'final_file_path' => $relative,
            'sealed_at' => now(),
            'seal_certificate_subject' => $cert->subjectCn,
            'seal_certificate_issuer' => $cert->issuerCn,
            'seal_certificate_serial' => $cert->serial,
            'seal_certificate_valid_to' => $cert->validTo,
        ])->save();

        return true;
    }

    public function certificate(): IcpCertificate
    {
        if ($this->certificate !== null) {
            return $this->certificate;
        }

        $path = (string) config('signatures.seal.certificate_path');
        $binary = is_file($path) ? file_get_contents($path) : false;

        if ($binary === false) {
            throw new RuntimeException("Certificado da plataforma não encontrado em {$path}.");
        }

        return $this->certificate = $this->icp->parse($binary, (string) config('signatures.seal.certificate_password'));
    }

    protected function buildUnsealed(Document $document): void
    {
        $relative = $this->composer->buildWorkingPdf($document);
        $document->forceFill(['final_file_path' => $relative])->save();
    }
}
