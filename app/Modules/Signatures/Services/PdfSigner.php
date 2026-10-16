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
    ): void {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetCreator('Moduhub');
        $pdf->SetAuthor($cert->subjectCn ?: 'Signatário');
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

        $pdf->setSignature(
            $cert->certPem,
            $cert->privateKeyPem,
            '',
            '',
            2,
            [
                'Name' => $cert->subjectCn ?: 'Signatário',
                'Location' => 'Brasil',
                'Reason' => 'Assinatura digital ICP-Brasil',
                'ContactInfo' => $cert->issuerCn,
            ],
        );

        $pdf->Output($destinationAbsolute, 'F');
    }
}
