<?php

namespace App\Modules\Signatures\Services;

use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfSigner
{
    /** CMS_CADES do OpenSSL 3: o PHP repassa a flag e o CMS sai com signing-certificate-v2. */
    protected const CMS_CADES = 0x100000;

    /**
     * Re-imports $sourceAbsolute via FPDI and outputs a PKCS#7-signed copy
     * at $destinationAbsolute. The visual content is unchanged — only the
     * crypto signature is added.
     */
    public function signWithPkcs12(
        string $sourceAbsolute,
        string $destinationAbsolute,
        IcpCertificate $cert,
        string $reason,
    ): void {
        // O TCPDF reserva ~5,8 KB para o PKCS#7 e corta o que passar disso. Com a cadeia ICP-Brasil
        // completa embutida a assinatura passa de 7 KB, então reservamos 16 KB.
        $pdf = new class('P', 'mm', 'A4') extends Fpdi
        {
            protected $signature_max_length = 32768;
        };
        $pdf->SetCreator('Moduhub');
        $pdf->SetAuthor($cert->subjectCn);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pageCount = $pdf->setSourceFile($sourceAbsolute);
        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $orientation = $size['orientation'] ?? ($size['width'] >= $size['height'] ? 'L' : 'P');
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
        }

        // O openssl_pkcs7_sign do TCPDF só aceita a cadeia como caminho de arquivo.
        $chainFile = $this->writeChainFile($cert);

        try {
            $pdf->setSignature(
                $cert->certPem,
                $cert->privateKeyPem,
                '',
                $chainFile ?? '',
                2,
                [
                    'Name' => $cert->subjectCn,
                    'Location' => 'Brasil',
                    'Reason' => $reason,
                    'ContactInfo' => $cert->issuerCn,
                ],
            );

            $pdf->Output($destinationAbsolute, 'F');

            $this->resignAsCades($destinationAbsolute, $cert, $chainFile);
        } finally {
            if ($chainFile !== null) {
                @unlink($chainFile);
            }
        }
    }

    /**
     * O TCPDF assina com openssl_pkcs7_sign, que não grava o atributo signing-certificate-v2. Sem ele a
     * assinatura não é PAdES, e o verificador do ITI recusa o arquivo. Aproveitamos o PDF e a reserva de
     * /Contents do TCPDF e trocamos só o CMS, agora em modo CAdES. O SubFilter novo tem o mesmo tamanho
     * do antigo, então o /ByteRange continua valendo.
     */
    protected function resignAsCades(string $pdfPath, IcpCertificate $cert, ?string $chainFile): void
    {
        $pdf = file_get_contents($pdfPath);

        if (! preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m)) {
            throw new RuntimeException('PDF assinado sem /ByteRange.');
        }
        [, $start1, $length1, $start2, $length2] = array_map('intval', $m);

        $pdf = str_replace('/SubFilter /adbe.pkcs7.detached', '/SubFilter /ETSI.CAdES.detached', $pdf);

        $content = tempnam(sys_get_temp_dir(), 'mdhb_cades_in_');
        $signature = tempnam(sys_get_temp_dir(), 'mdhb_cades_out_');

        try {
            file_put_contents($content, substr($pdf, $start1, $length1) . substr($pdf, $start2, $length2));

            $signed = openssl_cms_sign(
                $content,
                $signature,
                $cert->certPem,
                $cert->privateKeyPem,
                [],
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | self::CMS_CADES,
                OPENSSL_ENCODING_DER,
                $chainFile,
            );

            if (! $signed) {
                throw new RuntimeException('Falha ao gerar o CMS CAdES: ' . openssl_error_string());
            }

            $hex = bin2hex(file_get_contents($signature));
        } finally {
            @unlink($content);
            @unlink($signature);
        }

        // Entre os dois trechos assinados fica "<" + reserva em hexadecimal + ">".
        $reserved = $start2 - ($start1 + $length1) - 2;
        if (strlen($hex) > $reserved) {
            throw new RuntimeException('O CMS CAdES não coube na reserva de /Contents do PDF.');
        }

        $pdf = substr_replace($pdf, str_pad($hex, $reserved, '0'), $start1 + $length1 + 1, $reserved);
        file_put_contents($pdfPath, $pdf);
    }

    protected function writeChainFile(IcpCertificate $cert): ?string
    {
        if ($cert->chainPem === []) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'mdhb_chain_');
        chmod($path, 0600);
        file_put_contents($path, implode("\n", $cert->chainPem));

        return $path;
    }
}
