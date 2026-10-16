<?php

namespace App\Modules\Signatures\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

class PdfSigner
{
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
        } finally {
            if ($chainFile !== null) {
                @unlink($chainFile);
            }
        }
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
