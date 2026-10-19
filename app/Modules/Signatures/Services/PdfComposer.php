<?php

namespace App\Modules\Signatures\Services;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfComposer
{
    protected const FOOTER_HEIGHT = 12;
    protected const MARGIN_X = 16;
    protected const CONTENT_BOTTOM = 277;

    // Paleta do painel: grafite, azul caneta e os tons de status.
    protected const INK = [27, 34, 48];
    protected const MUTED = [91, 100, 116];
    protected const LINE = [225, 228, 234];
    protected const MIST = [245, 246, 248];
    protected const PEN = [35, 56, 184];
    protected const PEN_LIGHT = [150, 162, 222];
    protected const SIGNED = [31, 122, 77];
    protected const SIGNED_SOFT = [227, 242, 234];
    protected const PENDING = [154, 95, 12];
    protected const PENDING_SOFT = [251, 239, 217];
    protected const REJECTED = [180, 35, 24];
    protected const REJECTED_SOFT = [251, 230, 228];

    protected const FONTS = [
        'regular' => 'ibmplexsans',
        'medium' => 'ibmplexsansmedium',
        'semibold' => 'ibmplexsanssemib',
        'mono' => 'ibmplexmono',
    ];

    /**
     * Composes a "working PDF" from the original + footer on every page
     * + a final signatures report listing every signer's current status.
     *
     * Returns the storage path (relative, on the documents disk) of the generated file.
     */
    public function buildWorkingPdf(Document $document): string
    {
        $disk = Storage::disk($document->file_disk);
        $scratch = new LocalScratch();

        try {
            $output = $scratch->create();
            $this->renderWorkingPdf($document, $output, $scratch);

            $outputRelative = $this->workingPathFor($document);
            $scratch->upload($output, $disk, $outputRelative);

            return $outputRelative;
        } finally {
            $scratch->cleanup();
        }
    }

    /**
     * Same composition as buildWorkingPdf(), written to a local file and not uploaded,
     * for callers that still need to process it (the platform seal).
     * With $seal, the report announces the platform seal that is applied right after.
     */
    public function renderWorkingPdf(Document $document, string $outputAbsolute, LocalScratch $scratch, ?IcpCertificate $seal = null): void
    {
        $source = $scratch->download(Storage::disk($document->file_disk), $document->file_path);

        $pdf = $this->makePdf();
        $pageCount = $pdf->setSourceFile($source);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['orientation'] ?? ($size['width'] >= $size['height'] ? 'L' : 'P');

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            $this->drawFooter($pdf, $document);
        }

        $this->drawReport($pdf, $document, $seal !== null);

        $pdf->Output($outputAbsolute, 'F');
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
        $pdf->setCellPaddings(0, 0, 0, 0);

        foreach (self::FONTS as $family) {
            $pdf->AddFont($family, '', resource_path("fonts/tcpdf/{$family}.php"));
        }

        return $pdf;
    }

    protected function drawFooter(Fpdi $pdf, Document $document): void
    {
        $pageWidth = $pdf->getPageWidth();
        $top = $pdf->getPageHeight() - self::FOOTER_HEIGHT;
        $x = 10;
        $width = $pageWidth - 20;

        $pdf->SetFillColor(...self::MIST);
        $pdf->Rect(0, $top, $pageWidth, self::FOOTER_HEIGHT, 'F');
        $this->rule($pdf, 0, $top, $pageWidth);

        $verifyUrl = $this->verificationUrl($document);

        $this->font($pdf, 'medium', 7, self::INK);
        $pdf->SetXY($x, $top + 2.3);
        $pdf->Cell($width * 0.55, 3.5, $this->truncate($document->name ?: 'Documento', 70), 0, 0, 'L');

        $this->font($pdf, 'regular', 7, self::MUTED);
        $pdf->SetXY($x + $width * 0.55, $top + 2.3);
        $pdf->Cell($width * 0.45, 3.5, 'Verifique a autenticidade em ' . $verifyUrl, 0, 0, 'R', false, $verifyUrl);

        // O total de páginas só existe no fim (alias do TCPDF), então a largura não é confiável: fica à esquerda.
        $pdf->SetXY($x, $top + 6.4);
        $pdf->Cell($width * 0.5, 3.5, 'Página ' . $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'L');

        $this->font($pdf, 'mono', 6.5, self::MUTED);
        $pdf->SetXY($x + $width * 0.5, $top + 6.4);
        $pdf->Cell($width * 0.5, 3.5, 'SHA-256 do original ' . substr($document->file_hash, 0, 32), 0, 0, 'R');
    }

    protected function drawReport(Fpdi $pdf, Document $document, bool $sealed): void
    {
        $this->addReportPage($pdf, $document);
        $this->drawHeader($pdf);
        $this->drawSummary($pdf, $document);

        $signers = $document->signers()->orderBy('order')->get();
        $this->drawSignersTitle($pdf, $signers->where('status', DocumentSigner::STATUS_SIGNED)->count(), $signers->count());

        foreach ($signers as $signer) {
            $height = $this->signerCardHeight($pdf, $signer);
            $this->ensureSpace($pdf, $document, $height);
            $cardTop = $pdf->GetY();
            $this->drawSignerCard($pdf, $signer, $cardTop, $height);
            $pdf->SetY($cardTop + $height + 4);
        }

        if ($sealed) {
            $this->ensureSpace($pdf, $document, 50);
            $this->drawSealBlock($pdf, $document);
        }

        $this->ensureSpace($pdf, $document, 12);
        $this->drawClosingNote($pdf, $document);
    }

    protected function addReportPage(Fpdi $pdf, Document $document): void
    {
        $pdf->AddPage('P', 'A4');
        $this->drawFooter($pdf, $document);
        $pdf->SetY(16);
    }

    protected function ensureSpace(Fpdi $pdf, Document $document, float $height): void
    {
        if ($pdf->GetY() + $height <= self::CONTENT_BOTTOM) {
            return;
        }

        $this->addReportPage($pdf, $document);
        $this->font($pdf, 'medium', 9, self::MUTED);
        $pdf->SetXY(self::MARGIN_X, 16);
        $pdf->Cell(100, 5, 'Relatório de assinaturas, continuação', 0, 0, 'L');
        $this->drawLogo($pdf, $pdf->getPageWidth() - self::MARGIN_X, 15.5, 5, false);
        $pdf->SetY(27);
    }

    protected function drawHeader(Fpdi $pdf): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $tz = config('signatures.report_timezone');

        $this->font($pdf, 'semibold', 19, self::INK);
        $pdf->SetXY($x, 15);
        $pdf->Cell($width - 50, 9, 'Relatório de assinaturas', 0, 0, 'L');

        $this->font($pdf, 'regular', 9, self::MUTED);
        $pdf->SetXY($x, 25);
        $pdf->Cell($width - 50, 4.5, "Horários em {$tz} (UTC" . now($tz)->format('P') . ')', 0, 0, 'L');
        $pdf->SetXY($x, 29.5);
        $pdf->Cell($width - 50, 4.5, 'Última atualização em ' . $this->dateTime(now()), 0, 0, 'L');

        $this->drawLogo($pdf, $x + $width, 17, 7.5, true);

        $this->rule($pdf, $x, 38, $width);
        $pdf->SetY(43);
    }

    /**
     * Moduhub mark: "mh" ligature on a 48-unit grid (same paths as app-logo.tsx). The tall stem
     * with the first arch reads h; the lighter second arch completes the m. Drawn right-aligned at $right.
     */
    protected function drawLogo(Fpdi $pdf, float $right, float $top, float $size, bool $withWordmark): void
    {
        $wordmarkWidth = 0;
        if ($withWordmark) {
            $this->font($pdf, 'semibold', $size * 2.05, self::INK);
            $wordmarkWidth = $pdf->GetStringWidth('moduhub') + $size * 0.32;
        }

        $x = $right - $wordmarkWidth - $size;
        $u = $size / 48;
        $p = fn (float $gx, float $gy): array => [$x + $gx * $u, $top + $gy * $u];

        $arch = function (float $from, array $color) use ($pdf, $u, $p): void {
            $pdf->SetLineStyle(['width' => 7 * $u, 'color' => $color, 'cap' => 'round', 'join' => 'round']);
            $pdf->Curve(...$p($from, 23), ...$p($from, 18.5), ...$p($from + 3.5, 16), ...$p($from + 8, 16));
            $pdf->Curve(...$p($from + 8, 16), ...$p($from + 12.5, 16), ...$p($from + 16, 18.5), ...$p($from + 16, 23));
            $pdf->Line(...$p($from + 16, 23), ...$p($from + 16, 43));
        };

        // The light arch goes first so the solid stem it touches stays on top, as in the SVG.
        $arch(24, self::PEN_LIGHT);
        $arch(8, self::PEN);
        $pdf->Line(...$p(8, 5), ...$p(8, 43));

        if ($withWordmark) {
            // Nudged down to match the mark's optical center, which sits low because of the arches.
            $pdf->SetXY($right - $wordmarkWidth + $size * 0.32, $top + $size * 0.07);
            $pdf->Cell($wordmarkWidth, $size, 'moduhub', 0, 0, 'L', false, '', 0, false, 'T', 'M');
        }
    }

    protected function drawSummary(Fpdi $pdf, Document $document): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $qrSize = 30;
        $top = $pdf->GetY();
        $textWidth = $width - $qrSize - 8;

        $rows = [
            ['Status', $this->documentStatusLabel($document), 'regular'],
            ['Documento', $this->truncate($document->name ?: 'Documento', 70), 'regular'],
            ['Número', (string) $document->getKey(), 'mono'],
            ['Solicitado por', $this->truncate($document->tenant?->name ?? '—', 60), 'regular'],
            ['Data da criação', $this->dateTime($document->created_at), 'regular'],
            ['Hash do original (SHA-256)', $document->file_hash, 'mono'],
        ];

        $y = $top;
        foreach ($rows as [$label, $value, $valueFont]) {
            $this->font($pdf, 'medium', 9.5, self::INK);
            $labelText = $label . ':';
            $labelWidth = $pdf->GetStringWidth($labelText) + 1.6;
            $pdf->SetXY($x, $y);
            $pdf->Cell($labelWidth, 5.6, $labelText, 0, 0, 'L');

            $valueFont === 'mono'
                ? $this->font($pdf, 'mono', 7.8, self::INK)
                : $this->font($pdf, 'regular', 9.5, self::INK);
            $pdf->SetXY($x + $labelWidth, $y);
            $pdf->Cell($textWidth - $labelWidth, 5.6, $value, 0, 0, 'L', false, '', 1);
            $y += 6.2;
        }

        $verifyUrl = $this->verificationUrl($document);
        $qrX = $x + $width - $qrSize;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', $qrX, $top, $qrSize, $qrSize, [
            'border' => false,
            'padding' => 0,
            'fgcolor' => self::INK,
            'bgcolor' => false,
        ], 'N');

        $this->font($pdf, 'regular', 7, self::MUTED);
        $pdf->SetXY($qrX - 4, $top + $qrSize + 1);
        $pdf->Cell($qrSize + 4, 3.5, 'Aponte a câmera para validar', 0, 0, 'C', false, $verifyUrl);

        $bottom = max($y, $top + $qrSize + 5) + 3;
        $this->rule($pdf, $x, $bottom, $width);
        $pdf->SetY($bottom + 6);
    }

    protected function drawSignersTitle(Fpdi $pdf, int $signed, int $total): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $y = $pdf->GetY();

        $this->font($pdf, 'semibold', 13, self::INK);
        $pdf->SetXY($x, $y);
        $pdf->Cell($width / 2, 6, 'Assinaturas', 0, 0, 'L');

        $this->font($pdf, 'regular', 10, self::MUTED);
        $pdf->SetXY($x + $width / 2, $y);
        $pdf->Cell($width / 2, 6, "{$signed} de {$total} " . ($total === 1 ? 'assinatura' : 'assinaturas'), 0, 0, 'R');

        $pdf->SetY($y + 10);
    }

    protected function signerCardHeight(Fpdi $pdf, DocumentSigner $signer): float
    {
        return $this->identityHeight($pdf, $signer) + $this->evidenceHeight($pdf, $signer);
    }

    protected function identityHeight(Fpdi $pdf, DocumentSigner $signer): float
    {
        $height = 34;

        if ($signer->status === DocumentSigner::STATUS_REJECTED && $signer->rejection_reason) {
            $this->font($pdf, 'regular', 8.5, self::INK);
            $reasonHeight = $pdf->getStringHeight($this->identityWidth($pdf) - 10, 'Motivo: ' . $signer->rejection_reason);
            $height = max($height, 26.5 + $reasonHeight + 4);
        }

        return $height;
    }

    protected function evidenceHeight(Fpdi $pdf, DocumentSigner $signer): float
    {
        $columns = $this->evidenceColumns($pdf);
        $left = 6 + count($this->authenticationLines($signer)) * 4.6;

        $this->font($pdf, 'regular', 8.3, self::INK);
        $right = 6;
        foreach ($this->accessLines($signer) as $line) {
            $right += $pdf->getStringHeight($columns['rightWidth'], $line) + 0.6;
        }

        return max($left, $right) + 7;
    }

    protected function drawSignerCard(Fpdi $pdf, DocumentSigner $signer, float $y, float $height): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $identityHeight = $this->identityHeight($pdf, $signer);
        $identityWidth = $this->identityWidth($pdf);

        $pdf->SetFillColor(...self::MIST);
        $pdf->RoundedRect($x, $y + $identityHeight, $width, $height - $identityHeight, 2, '0011', 'F');

        $pdf->SetLineStyle(['width' => 0.25, 'color' => self::LINE, 'cap' => 'butt', 'join' => 'miter']);
        $pdf->RoundedRect($x, $y, $width, $height, 2, '1111', 'D');
        $pdf->Line($x, $y + $identityHeight, $x + $width, $y + $identityHeight);
        $pdf->Line($x + $identityWidth, $y, $x + $identityWidth, $y + $identityHeight);

        $this->drawIdentity($pdf, $signer, $x + 5, $y + 5, $identityWidth - 10);
        $this->drawSignatureBox($pdf, $signer, $x + $identityWidth, $y, $width - $identityWidth, $identityHeight);
        $this->drawEvidence($pdf, $signer, $y + $identityHeight + 4.5);
    }

    protected function drawIdentity(Fpdi $pdf, DocumentSigner $signer, float $x, float $y, float $width): void
    {
        $chipX = $x;
        $role = $signer->role;

        if ($signer->status === DocumentSigner::STATUS_SIGNED) {
            if ($role) {
                $chipX += $this->chip($pdf, $chipX, $y, "Assinado como {$role}", self::SIGNED_SOFT, self::SIGNED) + 2;
            }
            $this->chip($pdf, $chipX, $y, 'via Moduhub', self::SIGNED_SOFT, self::SIGNED, true);
        } elseif ($signer->status === DocumentSigner::STATUS_REJECTED) {
            $this->chip($pdf, $chipX, $y, $role ? "Recusou como {$role}" : 'Recusou o documento', self::REJECTED_SOFT, self::REJECTED);
        } else {
            $this->chip($pdf, $chipX, $y, $role ? "Assina como {$role}" : 'Aguardando assinatura', self::PENDING_SOFT, self::PENDING);
        }

        $this->font($pdf, 'semibold', 12, self::INK);
        $pdf->setFontSpacing(0.35);
        $pdf->SetXY($x, $y + 9.5);
        $pdf->Cell($width, 6, mb_strtoupper($signer->name), 0, 0, 'L', false, '', 1);
        $pdf->setFontSpacing(0);

        $this->font($pdf, 'regular', 8.8, self::INK);
        $pdf->SetXY($x, $y + 16.5);
        $pdf->Cell($width, 4.5, $this->signerTimeline($signer), 0, 0, 'L');

        if ($signer->status === DocumentSigner::STATUS_SIGNED) {
            $codeLabel = 'Código da assinatura: ';
            $codeLabelWidth = $pdf->GetStringWidth($codeLabel);
            $pdf->SetXY($x, $y + 21);
            $pdf->Cell($codeLabelWidth, 4.5, $codeLabel, 0, 0, 'L');
            $this->font($pdf, 'mono', 7.8, self::INK);
            $pdf->Cell($width - $codeLabelWidth, 4.5, (string) $signer->getKey(), 0, 0, 'L');
        }

        if ($signer->status === DocumentSigner::STATUS_REJECTED && $signer->rejection_reason) {
            $this->font($pdf, 'regular', 8.5, self::REJECTED);
            $pdf->SetXY($x, $y + 21.5);
            $pdf->MultiCell($width, 4, 'Motivo: ' . $signer->rejection_reason, 0, 'L');
        }
    }

    protected function signerTimeline(DocumentSigner $signer): string
    {
        return match ($signer->status) {
            DocumentSigner::STATUS_SIGNED => 'Data e hora da assinatura: ' . $this->dateTime($signer->signed_at),
            DocumentSigner::STATUS_REJECTED => 'Recusou em ' . $this->dateTime($signer->rejected_at),
            DocumentSigner::STATUS_VIEWED => 'Visualizou em ' . $this->dateTime($signer->viewed_at),
            default => 'Ainda não abriu o documento',
        };
    }

    protected function drawSignatureBox(Fpdi $pdf, DocumentSigner $signer, float $x, float $y, float $width, float $height): void
    {
        $this->font($pdf, 'regular', 8.5, self::MUTED);
        $pdf->SetXY($x + 4, $y + 4);
        $pdf->Cell($width - 8, 4, 'Assinatura', 0, 0, 'L');

        if ($signer->status !== DocumentSigner::STATUS_SIGNED) {
            $this->font($pdf, 'regular', 8.5, self::MUTED);
            $pdf->SetXY($x + 4, $y + $height / 2 - 2);
            $pdf->Cell($width - 8, 4, $signer->status === DocumentSigner::STATUS_REJECTED ? 'Não assinou' : 'Pendente', 0, 0, 'C');

            return;
        }

        $imageArea = ['x' => $x + 5, 'y' => $y + 9.5, 'w' => $width - 10, 'h' => $height - 19];
        $image = $this->signatureImage($signer);

        if ($image !== null) {
            $pdf->Image($image, $imageArea['x'], $imageArea['y'], $imageArea['w'], $imageArea['h'], 'PNG', '', '', true, 300, '', false, false, 0, 'CM');
        } else {
            $this->font($pdf, 'medium', 11, self::PEN);
            $pdf->SetXY($imageArea['x'], $imageArea['y']);
            $pdf->Cell($imageArea['w'], $imageArea['h'], $signer->name, 0, 0, 'C', false, '', 1, false, 'T', 'M');
        }

        $pdf->SetLineStyle(['width' => 0.2, 'color' => self::LINE]);
        $pdf->Line($x + 5, $y + $height - 8.5, $x + $width - 5, $y + $height - 8.5);

        $this->font($pdf, 'regular', 8.5, self::INK);
        $pdf->SetXY($x + 4, $y + $height - 7.5);
        $pdf->Cell($width - 8, 4, $signer->name, 0, 0, 'C', false, '', 1);
    }

    protected function drawEvidence(Fpdi $pdf, DocumentSigner $signer, float $y): void
    {
        $columns = $this->evidenceColumns($pdf);

        $this->font($pdf, 'semibold', 9, self::INK);
        $pdf->SetXY($columns['leftX'], $y);
        $pdf->Cell($columns['leftWidth'], 4.5, 'Pontos de autenticação', 0, 0, 'L');

        $lineY = $y + 6;
        foreach ($this->authenticationLines($signer) as [$label, $value]) {
            $this->labeled($pdf, $columns['leftX'], $lineY, $columns['leftWidth'], $label, $value);
            $lineY += 4.6;
        }

        $this->font($pdf, 'semibold', 9, self::INK);
        $pdf->SetXY($columns['rightX'], $y);
        $pdf->Cell($columns['rightWidth'], 4.5, 'Acesso', 0, 0, 'L');

        $this->font($pdf, 'regular', 8.3, self::INK);
        $pdf->SetXY($columns['rightX'], $y + 6);
        foreach ($this->accessLines($signer) as $line) {
            $pdf->SetX($columns['rightX']);
            $pdf->MultiCell($columns['rightWidth'], 4, $line, 0, 'L');
            $pdf->SetY($pdf->GetY() + 0.6);
        }
    }

    /** @return list<array{0: string, 1: string}> */
    protected function authenticationLines(DocumentSigner $signer): array
    {
        $lines = [['E-mail', $signer->email]];

        if ($signer->phone) {
            $lines[] = ['Telefone', $signer->phone];
        }
        if ($signer->cpf) {
            $lines[] = ['CPF', $signer->cpf];
        }
        if ($signer->cnpj) {
            $lines[] = ['CNPJ', $this->formatCnpj($signer->cnpj)];
        }

        // Por ora qualquer método confirma por código no e-mail: o SMS ainda não envia mensagem própria.
        $lines[] = ['Nível de segurança', 'Validado por código único enviado por e-mail'];

        return $lines;
    }

    /** @return list<string> */
    protected function accessLines(DocumentSigner $signer): array
    {
        if ($signer->ip_address === null) {
            return ['Ainda sem acesso registrado.'];
        }

        $lines = [];
        $location = $signer->geolocation;
        if (is_array($location) && isset($location['lat'], $location['lng'])) {
            $lines[] = "Localização aproximada: {$location['lat']}, {$location['lng']}";
        }
        $lines[] = 'IP: ' . $signer->ip_address;
        if ($signer->user_agent) {
            $lines[] = 'Dispositivo: ' . $this->truncate($signer->user_agent, 220);
        }

        return $lines;
    }

    /** @return array{leftX: float, leftWidth: float, rightX: float, rightWidth: float} */
    protected function evidenceColumns(Fpdi $pdf): array
    {
        $x = self::MARGIN_X + 5;
        $width = $this->contentWidth($pdf) - 10;
        $leftWidth = $width * 0.56;

        return [
            'leftX' => $x,
            'leftWidth' => $leftWidth,
            'rightX' => $x + $leftWidth + 6,
            'rightWidth' => $width - $leftWidth - 6,
        ];
    }

    protected function labeled(Fpdi $pdf, float $x, float $y, float $width, string $label, string $value): void
    {
        $this->font($pdf, 'regular', 8.3, self::MUTED);
        $text = $label . ': ';
        $labelWidth = $pdf->GetStringWidth($text);
        $pdf->SetXY($x, $y);
        $pdf->Cell($labelWidth, 4, $text, 0, 0, 'L');

        $this->font($pdf, 'regular', 8.3, self::INK);
        $pdf->Cell($width - $labelWidth, 4, $value, 0, 0, 'L', false, '', 1);
    }

    /**
     * Rounded status pill. Returns its width so callers can line pills up.
     */
    protected function chip(Fpdi $pdf, float $x, float $y, string $text, array $fill, array $color, bool $check = false): float
    {
        $this->font($pdf, 'medium', 8.3, $color);
        $iconWidth = $check ? 4.4 : 0;
        $width = $pdf->GetStringWidth($text) + 5 + $iconWidth;
        $height = 6;

        $pdf->SetFillColor(...$fill);
        $pdf->RoundedRect($x, $y, $width, $height, 1.4, '1111', 'F');

        if ($check) {
            $this->drawCheckBadge($pdf, $x + 2.5 + 1.6, $y + $height / 2, 1.6, $color);
        }

        $pdf->SetXY($x + 2.5 + $iconWidth, $y);
        $pdf->Cell($width - 5 - $iconWidth, $height, $text, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        return $width;
    }

    protected function drawCheckBadge(Fpdi $pdf, float $cx, float $cy, float $radius, array $color): void
    {
        $pdf->SetFillColor(...$color);
        $pdf->Circle($cx, $cy, $radius, 0, 360, 'F');
        $this->checkMark($pdf, $cx, $cy, $radius * 0.95, $radius * 0.26, [255, 255, 255]);
    }

    protected function checkMark(Fpdi $pdf, float $cx, float $cy, float $size, float $stroke, array $color): void
    {
        $pdf->SetLineStyle(['width' => $stroke, 'color' => $color, 'cap' => 'round', 'join' => 'round']);
        $pdf->PolyLine([
            $cx - 0.5 * $size, $cy + 0.02 * $size,
            $cx - 0.14 * $size, $cy + 0.36 * $size,
            $cx + 0.52 * $size, $cy - 0.34 * $size,
        ], 'D');
    }

    protected function drawSealBlock(Fpdi $pdf, Document $document): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $y = $pdf->GetY() + 2;
        $emblem = 34;
        $textWidth = $width - $emblem - 12;

        $this->rule($pdf, $x, $y, $width);

        $this->drawSealEmblem($pdf, $x + $width - $emblem / 2 - 2, $y + 4 + $emblem / 2, $emblem / 2);

        $this->font($pdf, 'semibold', 12.5, self::INK);
        $pdf->SetXY($x, $y + 6);
        $pdf->Cell($textWidth, 6, 'Integridade certificada ICP-Brasil', 0, 1, 'L');

        $this->font($pdf, 'regular', 9.3, self::MUTED);
        $pdf->SetXY($x, $y + 13);
        $pdf->MultiCell(
            $textWidth,
            4.6,
            'Este PDF foi selado com certificado digital ICP-Brasil: qualquer alteração no arquivo invalida o selo. '
            . 'Assinaturas eletrônicas válidas nos termos da MP 2.200-2/2001 (art. 10, § 2º) e da Lei 14.063/2020.',
            0,
            'L',
        );

        $verifyUrl = $this->verificationUrl($document);
        $this->font($pdf, 'medium', 9.3, self::PEN);
        $linkText = 'Confirme a integridade do documento';
        $pdf->SetXY($x, $pdf->GetY() + 1.5);
        $linkY = $pdf->GetY();
        $pdf->Cell($pdf->GetStringWidth($linkText), 5, $linkText, 0, 0, 'L', false, $verifyUrl);
        $pdf->SetLineStyle(['width' => 0.2, 'color' => self::PEN]);
        $pdf->Line($x, $linkY + 4.6, $x + $pdf->GetStringWidth($linkText), $linkY + 4.6);

        $pdf->SetY(max($linkY + 8, $y + $emblem + 8));
    }

    /**
     * Selo próprio no espírito dos carimbos de cartório e da impressão de segurança:
     * anel com a legenda, rosácea guilloché e o visto no centro.
     */
    protected function drawSealEmblem(Fpdi $pdf, float $cx, float $cy, float $radius): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineStyle(['width' => 0.55, 'color' => self::PEN, 'cap' => 'butt', 'join' => 'miter']);
        $pdf->Circle($cx, $cy, $radius, 0, 360, 'DF');
        $pdf->SetLineStyle(['width' => 0.15, 'color' => self::PEN]);
        $pdf->Circle($cx, $cy, $radius - 1.0, 0, 360, 'D');

        $innerRadius = $radius - 5.4;
        $pdf->SetLineStyle(['width' => 0.3, 'color' => self::PEN]);
        $pdf->Circle($cx, $cy, $innerRadius, 0, 360, 'D');

        $this->drawRingText($pdf, $cx, $cy, $radius - 3.25, 'INTEGRIDADE CERTIFICADA • ICP-BRASIL • ');

        $rosette = $innerRadius - 0.7;
        $pdf->SetLineStyle(['width' => 0.08, 'color' => self::PEN_LIGHT]);
        for ($i = 0; $i < 24; $i++) {
            $pdf->Ellipse($cx, $cy, $rosette, $rosette * 0.36, $i * 7.5, 0, 360, 'D');
        }
        $pdf->SetLineStyle(['width' => 0.08, 'color' => self::PEN]);
        for ($i = 0; $i < 18; $i++) {
            $pdf->Ellipse($cx, $cy, $rosette * 0.72, $rosette * 0.5, $i * 10 + 5, 0, 360, 'D');
        }

        $core = $innerRadius * 0.46;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineStyle(['width' => 0.4, 'color' => self::PEN]);
        $pdf->Circle($cx, $cy, $core + 0.9, 0, 360, 'DF');
        $pdf->SetFillColor(...self::PEN);
        $pdf->Circle($cx, $cy, $core, 0, 360, 'F');
        $this->checkMark($pdf, $cx, $cy, $core * 1.05, $core * 0.2, [255, 255, 255]);
    }

    protected function drawRingText(Fpdi $pdf, float $cx, float $cy, float $radius, string $text): void
    {
        $this->font($pdf, 'semibold', 5.6, self::PEN);
        $chars = mb_str_split($text);
        $widths = array_map(fn ($char) => $pdf->GetStringWidth($char), $chars);
        $total = array_sum($widths);
        $height = 2.2;

        // Começa no topo e corre no sentido horário, cada letra com o topo voltado para fora.
        $traveled = 0;
        foreach ($chars as $i => $char) {
            $angle = deg2rad(90 - ($traveled + $widths[$i] / 2) / $total * 360);
            $traveled += $widths[$i];

            $px = $cx + $radius * cos($angle);
            $py = $cy - $radius * sin($angle);

            $pdf->StartTransform();
            $pdf->Rotate(rad2deg($angle) - 90, $px, $py);
            $pdf->SetXY($px - $widths[$i] / 2, $py - $height / 2);
            $pdf->Cell($widths[$i], $height, $char, 0, 0, 'C', false, '', 0, false, 'T', 'M');
            $pdf->StopTransform();
        }
    }

    protected function drawClosingNote(Fpdi $pdf, Document $document): void
    {
        $x = self::MARGIN_X;
        $width = $this->contentWidth($pdf);
        $y = $pdf->GetY() + 1;

        $this->rule($pdf, $x, $y, $width);
        $this->font($pdf, 'regular', 8, self::MUTED);
        $pdf->SetXY($x, $y + 3);
        $pdf->MultiCell(
            $width,
            4,
            'Este relatório é parte integrante do documento número ' . $document->getKey()
            . ' e pode ser conferido em ' . $this->verificationUrl($document) . '.',
            0,
            'L',
        );
    }

    protected function documentStatusLabel(Document $document): string
    {
        return match ($document->status) {
            Document::STATUS_COMPLETED => 'Assinado',
            Document::STATUS_PARTIALLY_SIGNED => 'Assinado parcialmente',
            Document::STATUS_SENT => 'Aguardando assinaturas',
            Document::STATUS_REJECTED => 'Recusado',
            Document::STATUS_EXPIRED => 'Prazo encerrado',
            Document::STATUS_CANCELLED => 'Cancelado',
            default => 'Rascunho',
        };
    }

    protected function font(Fpdi $pdf, string $weight, float $size, array $color): void
    {
        $pdf->SetFont(self::FONTS[$weight], '', $size);
        $pdf->SetTextColor(...$color);
    }

    protected function rule(Fpdi $pdf, float $x, float $y, float $width): void
    {
        $pdf->SetLineStyle(['width' => 0.25, 'color' => self::LINE, 'cap' => 'butt', 'join' => 'miter']);
        $pdf->Line($x, $y, $x + $width, $y);
    }

    protected function contentWidth(Fpdi $pdf): float
    {
        return $pdf->getPageWidth() - self::MARGIN_X * 2;
    }

    protected function identityWidth(Fpdi $pdf): float
    {
        return $this->contentWidth($pdf) - 56;
    }

    protected function dateTime(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        $local = $value->copy()->setTimezone(config('signatures.report_timezone'));

        return $local->format('d/m/Y') . ' às ' . $local->format('H:i:s');
    }

    protected function formatCnpj(string $digits): string
    {
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
    }

    /** A imagem em memória, no formato '@<bytes>' que o TCPDF aceita no lugar de um caminho. */
    protected function signatureImage(DocumentSigner $signer): ?string
    {
        if (empty($signer->signature_image_path)) {
            return null;
        }

        $disk = Storage::disk($signer->document->file_disk);
        if (! $disk->exists($signer->signature_image_path)) {
            return null;
        }

        return '@' . $disk->get($signer->signature_image_path);
    }

    public function workingPathFor(Document $document): string
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

    protected function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }
}
