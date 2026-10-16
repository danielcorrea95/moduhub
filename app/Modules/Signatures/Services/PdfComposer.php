<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfComposer
{
    protected const FOOTER_HEIGHT_MM = 14;

    /**
     * Composes a "working PDF" from the original + footer on every page
     * + a final signatures page listing every signer's current status.
     *
     * Returns the storage path (relative, on the documents disk) of the generated file.
     */
    public function buildWorkingPdf(Document $document): string
    {
        $disk = Storage::disk($document->file_disk);
        $sourcePath = $disk->path($document->file_path);

        $pdf = $this->makePdf();
        $pageCount = $pdf->setSourceFile($sourcePath);
        $verificationUrl = $this->verificationUrl($document);
        $shortHash = substr($document->file_hash, 0, 32);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['orientation'] ?? ($size['width'] >= $size['height'] ? 'L' : 'P');

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            $this->drawFooter($pdf, $document, $pageNumber, $pageCount + 1, $verificationUrl, $shortHash);
        }

        $pdf->AddPage('P', 'A4');
        $this->drawFooter($pdf, $document, $pageCount + 1, $pageCount + 1, $verificationUrl, $shortHash);
        $this->drawSignaturesPage($pdf, $document);

        $outputRelative = $this->workingPathFor($document);
        $outputAbsolute = $disk->path($outputRelative);

        $this->ensureDirectoryExists(dirname($outputAbsolute));
        $pdf->Output($outputAbsolute, 'F');

        return $outputRelative;
    }

    protected function makePdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetCreator('Moduhub');
        $pdf->SetAuthor('Moduhub');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        return $pdf;
    }

    protected function drawFooter(
        Fpdi $pdf,
        Document $document,
        int $pageNumber,
        int $totalPages,
        string $verificationUrl,
        string $shortHash,
    ): void {
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        $top = $pageHeight - self::FOOTER_HEIGHT_MM;

        $pdf->SetFillColor(245, 246, 250);
        $pdf->Rect(0, $top, $pageWidth, self::FOOTER_HEIGHT_MM, 'F');

        $pdf->SetDrawColor(220, 222, 230);
        $pdf->Line(0, $top, $pageWidth, $top);

        $pdf->SetTextColor(80, 85, 100);
        $pdf->SetFont('helvetica', '', 7);

        $title = $this->truncate($document->name ?: 'Documento', 60);
        $shortId = $this->shortDocumentId($document);

        $pdf->SetXY(8, $top + 2);
        $pdf->Cell(
            $pageWidth - 16,
            4,
            "Documento: {$title}  ·  ID: {$shortId}  ·  Página {$pageNumber} de {$totalPages}",
            0,
            0,
            'L',
        );

        $pdf->SetXY(8, $top + 7);
        $pdf->SetFont('courier', '', 6.5);
        $pdf->Cell(
            $pageWidth - 16,
            4,
            "Verifique: {$verificationUrl}   SHA-256 do original: {$shortHash}",
            0,
            0,
            'L',
        );
    }

    protected function drawSignaturesPage(Fpdi $pdf, Document $document): void
    {
        $marginX = 15;
        $marginTop = 20;
        $contentWidth = $pdf->getPageWidth() - ($marginX * 2);
        $verifyUrl = $this->verificationUrl($document);

        $qrSize = 28;
        $qrX = $pdf->getPageWidth() - $marginX - $qrSize;
        $qrY = $marginTop;

        $pdf->write2DBarcode(
            $verifyUrl,
            'QRCODE,M',
            $qrX,
            $qrY,
            $qrSize,
            $qrSize,
            [
                'border' => false,
                'padding' => 0,
                'fgcolor' => [20, 24, 40],
                'bgcolor' => false,
            ],
            'N',
        );

        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(90, 95, 110);
        $pdf->SetXY($qrX, $qrY + $qrSize + 1);
        $pdf->Cell($qrSize, 3, 'Validar documento', 0, 0, 'C');

        $titleWidth = $contentWidth - $qrSize - 6;

        $pdf->SetTextColor(20, 24, 40);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY($marginX, $marginTop);
        $pdf->Cell($titleWidth, 10, 'Relatório de Assinaturas', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(90, 95, 110);
        $pdf->SetX($marginX);
        $pdf->Cell(
            $titleWidth,
            5,
            "Documento: " . $this->truncate($document->name ?: 'Documento', 70)
                . '   ·   ID ' . $this->shortDocumentId($document),
            0,
            1,
            'L',
        );
        $pdf->SetX($marginX);
        $pdf->Cell(
            $titleWidth,
            5,
            'SHA-256 do PDF original: ' . substr($document->file_hash, 0, 48) . (strlen($document->file_hash) > 48 ? '…' : ''),
            0,
            1,
            'L',
        );
        $pdf->SetX($marginX);
        $pdf->SetFont('courier', '', 7.5);
        $pdf->Cell(
            $titleWidth,
            4,
            'Verifique em: ' . $verifyUrl,
            0,
            1,
            'L',
        );

        $pdf->SetY(max($pdf->GetY(), $qrY + $qrSize + 6));
        $pdf->Ln(3);

        $signers = $document->signers()->orderBy('order')->get();

        if ($signers->isEmpty()) {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->SetTextColor(140, 145, 160);
            $pdf->SetX($marginX);
            $pdf->Cell(
                $contentWidth,
                8,
                'Nenhum signatário cadastrado.',
                0,
                1,
                'L',
            );
            return;
        }

        foreach ($signers as $signer) {
            $this->drawSignerBlock($pdf, $signer, $marginX, $contentWidth);
            $pdf->Ln(4);
        }

        $hasIcp = $signers->contains(fn ($s) => $s->signature_type === DocumentSigner::TYPE_ICP);
        if ($hasIcp) {
            $pdf->Ln(6);
            $this->drawIcpBadge($pdf, $marginX, $contentWidth);
        }

        $pdf->Ln(4);
        $pdf->SetDrawColor(220, 222, 230);
        $pdf->Line($marginX, $pdf->GetY(), $marginX + $contentWidth, $pdf->GetY());
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(120, 125, 140);
        $pdf->SetX($marginX);
        $pdf->Cell(
            $contentWidth,
            4,
            'Documento preparado por Moduhub em ' . now()->format('d/m/Y H:i:s') . ' (UTC) · tenant ' . $this->shortTenantId($document),
            0,
            1,
            'L',
        );
    }

    protected function drawIcpBadge(Fpdi $pdf, float $x, float $width): void
    {
        $y = $pdf->GetY();
        $boxHeight = 20;

        $pdf->SetDrawColor(185, 200, 235);
        $pdf->SetFillColor(239, 244, 255);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, $y, $width, $boxHeight, 'FD');

        $badgeSize = 14;
        $badgeX = $x + $width - $badgeSize - 4;
        $badgeY = $y + ($boxHeight - $badgeSize) / 2;
        $this->drawIcpVectorMark($pdf, $badgeX, $badgeY, $badgeSize);

        $textX = $x + 5;
        $textWidth = $width - $badgeSize - 14;

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(28, 48, 110);
        $pdf->SetXY($textX, $y + 3);
        $pdf->Cell($textWidth, 5, 'INTEGRIDADE CERTIFICADA · ICP-BRASIL', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(60, 70, 110);
        $pdf->SetXY($textX, $y + 9);
        $pdf->Cell(
            $textWidth,
            4,
            'Este documento contém assinatura digital ICP-Brasil, com validade jurídica',
            0,
            1,
            'L',
        );
        $pdf->SetXY($textX, $y + 13);
        $pdf->Cell(
            $textWidth,
            4,
            'equivalente à presencial (MP 2.200-2/2001 e Lei 14.063/2020).',
            0,
            1,
            'L',
        );

        $pdf->SetY($y + $boxHeight);
    }

    protected function drawIcpVectorMark(Fpdi $pdf, float $x, float $y, float $size): void
    {
        $cx = $x + $size / 2;
        $cy = $y + $size / 2;
        $radius = $size / 2;

        $pdf->SetDrawColor(28, 48, 110);
        $pdf->SetFillColor(28, 48, 110);
        $pdf->SetLineWidth(0.3);
        $pdf->Circle($cx, $cy, $radius, 0, 360, 'F');

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x, $cy - 3.2);
        $pdf->Cell($size, 2.5, 'ICP', 0, 0, 'C');

        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($cx - 2.5, $cy - 0.2, $cx + 2.5, $cy - 0.2);

        $pdf->SetFont('helvetica', 'B', 3.8);
        $pdf->SetXY($x, $cy + 0.4);
        $pdf->Cell($size, 2.5, 'BRASIL', 0, 0, 'C');
    }

    protected function drawSignerBlock(Fpdi $pdf, DocumentSigner $signer, float $x, float $width): void
    {
        $y = $pdf->GetY();
        $blockHeight = 32;

        $pdf->SetDrawColor(220, 222, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $width, $blockHeight);

        $isSigned = $signer->status === DocumentSigner::STATUS_SIGNED;
        $isRejected = $signer->status === DocumentSigner::STATUS_REJECTED;

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(20, 24, 40);
        $pdf->SetXY($x + 4, $y + 3);
        $pdf->Cell($width - 60, 5, "#{$signer->order}  {$signer->name}", 0, 0, 'L');

        $badgeLabel = $isSigned
            ? 'Assinado'
            : ($isRejected ? 'Rejeitou' : 'Pendente');
        $badgeFill = $isSigned
            ? [217, 243, 221]
            : ($isRejected ? [254, 226, 226] : [243, 244, 249]);
        $badgeText = $isSigned
            ? [21, 94, 59]
            : ($isRejected ? [153, 27, 27] : [71, 80, 100]);

        $badgeW = 28;
        $badgeX = $x + $width - $badgeW - 4;
        $pdf->SetFillColor(...$badgeFill);
        $pdf->SetTextColor(...$badgeText);
        $pdf->Rect($badgeX, $y + 3, $badgeW, 6, 'F');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($badgeX, $y + 3.5);
        $pdf->Cell($badgeW, 5, $badgeLabel, 0, 0, 'C');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 65, 80);
        $details = [$signer->email];
        if ($signer->cpf) {
            $details[] = 'CPF ' . $signer->cpf;
        }
        $details[] = 'Autenticação: ' . $this->authLabel($signer->auth_method);

        $pdf->SetXY($x + 4, $y + 10);
        $pdf->Cell($width - 8, 4, implode('   ·   ', $details), 0, 0, 'L');

        if ($isSigned) {
            $pdf->SetFont('courier', '', 8);
            $pdf->SetTextColor(60, 65, 80);
            $when = $signer->signed_at?->format('d/m/Y H:i:s') ?? '-';
            $pdf->SetXY($x + 4, $y + 16);
            $typeLabel = $signer->signature_type === DocumentSigner::TYPE_ICP
                ? 'ICP-Brasil'
                : 'eletrônica';
            $pdf->Cell(
                $width - 8,
                4,
                "Assinado em {$when}   ·   IP: {$signer->ip_address}   ·   Tipo: {$typeLabel}",
                0,
                0,
                'L',
            );

            if ($signer->signature_type === DocumentSigner::TYPE_ICP) {
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(60, 65, 80);
                $pdf->SetXY($x + 4, $y + 21);
                $cert = trim(($signer->icp_certificate_subject ?? '') . ' · emitido por ' . ($signer->icp_certificate_issuer ?? ''));
                $pdf->Cell(
                    $width - 8,
                    4,
                    'Assinatura digital embutida — ' . $this->truncate($cert, 90),
                    0,
                    0,
                    'L',
                );
                $validTo = $signer->icp_certificate_valid_to?->format('d/m/Y') ?? '-';
                $pdf->SetXY($x + 4, $y + 25);
                $pdf->Cell(
                    $width - 8,
                    4,
                    'Certificado válido até ' . $validTo . ' · verificável pela trilha PKCS#7 anexa ao PDF',
                    0,
                    0,
                    'L',
                );
            } elseif (($signatureImageAbsolute = $this->signatureImageAbsolutePath($signer)) !== null) {
                $pdf->Image(
                    $signatureImageAbsolute,
                    $x + 4,
                    $y + 21,
                    0,
                    8,
                    'PNG',
                    '',
                    'T',
                    false,
                    300,
                    '',
                    false,
                    false,
                    0,
                    false,
                    false,
                    false,
                );
            } else {
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->SetTextColor(20, 24, 40);
                $pdf->SetXY($x + 4, $y + 22);
                $pdf->Cell(
                    $width - 8,
                    6,
                    'Assinado eletronicamente por ' . $signer->name,
                    0,
                    0,
                    'L',
                );
            }
        } else {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(140, 145, 160);
            $pdf->SetXY($x + 4, $y + 20);
            $label = $isRejected
                ? 'Signatário rejeitou a assinatura'
                : 'Aguardando assinatura';
            $pdf->Cell($width - 8, 5, $label, 0, 0, 'L');
        }

        $pdf->SetY($y + $blockHeight);
    }

    protected function signatureImageAbsolutePath(DocumentSigner $signer): ?string
    {
        if (empty($signer->signature_image_path)) {
            return null;
        }

        $disk = Storage::disk($signer->document->file_disk);
        if (! $disk->exists($signer->signature_image_path)) {
            return null;
        }

        return $disk->path($signer->signature_image_path);
    }

    protected function workingPathFor(Document $document): string
    {
        $base = pathinfo($document->file_path, PATHINFO_DIRNAME);
        return $base . '/working_' . $document->getKey() . '.pdf';
    }

    protected function verificationUrl(Document $document): string
    {
        return rtrim(config('app.url'), '/') . '/v/' . $this->shortDocumentId($document);
    }

    protected function shortDocumentId(Document $document): string
    {
        return strtoupper(substr($document->getKey(), -12));
    }

    protected function shortTenantId(Document $document): string
    {
        return strtoupper(substr($document->tenant_id, -8));
    }

    protected function authLabel(string $method): string
    {
        return match ($method) {
            DocumentSigner::AUTH_EMAIL => 'e-mail',
            DocumentSigner::AUTH_SMS => 'SMS',
            DocumentSigner::AUTH_EMAIL_AND_SMS => 'e-mail + SMS',
            DocumentSigner::AUTH_ICP => 'ICP-Brasil',
            default => $method,
        };
    }

    protected function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
