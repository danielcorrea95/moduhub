<?php

namespace App\Modules\Invoices\Services\Danfse;

use TCPDF;

/**
 * Desenha o DANFSe v2.0 conforme a NT 008/2026 v1.02 (Anexo I e item 2.4.5): A4 retrato, página única,
 * medidas em centímetros a partir da tabela de campos. Blocos ausentes viram uma linha de aviso
 * (notas 2 a 4) e a sobra de altura vai para "Informações Complementares" (itens 2.3.1 a 2.3.3).
 */
class DanfseRenderer
{
    private const LEFT = 0.30;
    private const TOP = 0.30;
    private const WIDTH = 20.40;
    private const BOTTOM = 29.40;

    /** Colunas da grade de 4 campos da NT. */
    private const COL = [0.30, 5.41, 10.51, 15.62];
    private const COL_W = 5.09;
    private const WIDE_W = 10.19;

    private const ROW = 0.645;
    /** A NT pede no mínimo 0,32 cm; um pouco mais evita que o texto em 7 pt encoste na linha seguinte. */
    private const NOTICE_ROW = 0.40;

    /** Cinza claro de 5% de densidade para cabeçalho, títulos e campos de destaque (item 2.2.3). */
    private const SHADE = [242, 242, 242];

    private const LABEL_FONT = 'helvetica';
    private const VALUE_FONT = 'helvetica';

    private TCPDF $pdf;

    private float $y;

    public function render(DanfseData $d): string
    {
        $this->pdf = new TCPDF('P', 'cm', 'A4', true, 'UTF-8');
        $this->pdf->SetCreator('Moduhub');
        $this->pdf->SetTitle('DANFSe '.$d->access_key);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->setCellPaddings(0.08, 0.02, 0.08, 0.02);
        $this->pdf->AddPage();

        $this->header($d);
        $this->identification($d);

        $this->y = 4.34;
        $this->provider($d->provider);
        $this->party('TOMADOR / ADQUIRENTE', $d->taker, 'TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e');
        // Sem o grupo IBSCBS/dest, o destinatário da operação é o próprio tomador (nota 3).
        $this->notice($d->taker !== null
            ? 'O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO'
            : 'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e');
        $this->party('INTERMEDIÁRIO DA OPERAÇÃO', $d->intermediary, 'INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e');
        $this->service($d->service);
        $this->municipalTax($d->iss);
        $this->federalTax($d->federal);
        $this->ibscbs($d->ibscbs);
        $this->totals($d->totals);
        $this->complementary($d->complementary);

        $this->pdf->SetLineWidth(0.0353); // 1 ponto
        $this->pdf->Rect(self::LEFT, self::TOP, self::WIDTH, self::BOTTOM - self::TOP);

        if ($d->cancelled || $d->replaced) {
            $this->watermark($d->cancelled ? 'CANCELADA' : 'SUBSTITUÍDA');
        }

        return $this->pdf->Output('danfse.pdf', 'S');
    }

    private function header(DanfseData $d): void
    {
        $this->pdf->SetFillColor(...self::SHADE);
        $this->pdf->Rect(self::LEFT, self::TOP, self::WIDTH, 1.16, 'F');

        $this->pdf->Image(resource_path('nfse/danfse/logo-nfse-horizontal.png'), 0.49, 0.44, 4.00, 0, 'PNG');

        $this->pdf->SetFont(self::LABEL_FONT, 'B', 9);
        // O TCPDF estica a célula para a altura da fonte × 1,25; com três linhas isso vaza o cabeçalho.
        $this->pdf->setCellHeightRatio(1.0);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->SetXY(5.41, $d->restricted ? 0.38 : 0.52);
        $this->pdf->Cell(10.19, 0.34, 'DANFSe v2.0', 0, 2, 'C');
        $this->pdf->Cell(10.19, 0.34, 'Documento Auxiliar da NFS-e', 0, 2, 'C');
        if ($d->restricted) {
            $this->pdf->SetTextColor(237, 28, 36);
            $this->pdf->Cell(10.19, 0.34, 'NFS-e SEM VALIDADE JURÍDICA', 0, 2, 'C');
            $this->pdf->SetTextColor(0);
        }
        $this->pdf->setCellHeightRatio(1.25);
        $this->pdf->setCellPaddings(0.08, 0.02, 0.08, 0.02);

        $this->pdf->SetFont(self::VALUE_FONT, '', 8);
        $this->pdf->SetXY(15.62, 0.42);
        $this->pdf->Cell(5.09, 0.40, $d->header_city, 0, 2, 'L');
        $this->pdf->SetFont(self::VALUE_FONT, '', 6);
        $this->pdf->Cell(5.09, 0.25, $d->header_generator, 0, 2, 'L');
        $this->pdf->Cell(5.09, 0.25, $d->header_environment, 0, 2, 'L');

        $this->line(1.46);
    }

    private function identification(DanfseData $d): void
    {
        // No bloco de identificação os títulos vão em 7 pt e caixa alta (item 2.4.2).
        $this->field(0.30, 1.48, 15.30, 0.77, 'CHAVE DE ACESSO DA NFS-E', $d->access_key, upperLabel: true);
        $this->field(0.30, 2.27, self::COL_W, 0.67, 'NÚMERO DA NFS-E', $d->number, upperLabel: true);
        $this->field(5.41, 2.27, self::COL_W, 0.67, 'COMPETÊNCIA DA NFS-E', $d->competence, upperLabel: true);
        $this->field(10.51, 2.27, self::COL_W, 0.67, 'DATA E HORA DA EMISSÃO DA NFS-E', $d->issued_at, upperLabel: true);
        $this->field(0.30, 2.96, self::COL_W, 0.67, 'NÚMERO DA DPS', $d->dps_number, upperLabel: true);
        $this->field(5.41, 2.96, self::COL_W, 0.67, 'SÉRIE DA DPS', $d->dps_series, upperLabel: true);
        $this->field(10.51, 2.96, self::COL_W, 0.67, 'DATA E HORA DA EMISSÃO DA DPS', $d->dps_emitted_at, upperLabel: true);

        $this->pdf->SetFillColor(...self::SHADE);
        $this->pdf->Rect(0.30, 3.65, self::COL_W, 0.67, 'F');
        $this->field(0.30, 3.65, self::COL_W, 0.67, 'EMITENTE DA NFS-E', $d->emitter, upperLabel: true);
        $this->field(5.41, 3.65, self::COL_W, 0.67, 'SITUAÇÃO DA NFS-E', $d->status, upperLabel: true);
        $this->field(10.51, 3.65, self::COL_W, 0.67, 'FINALIDADE', $d->finality, upperLabel: true);

        $this->pdf->write2DBarcode($d->qr_url, 'QRCODE,M', 17.48, 1.67, 1.52, 1.52, ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false]);
        $this->pdf->SetFont(self::VALUE_FONT, '', 6);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->setCellHeightRatio(1.1);
        $this->pdf->SetXY(15.80, 3.30);
        $this->pdf->MultiCell(4.72, 0.68, 'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e', 0, 'C');
        $this->pdf->setCellHeightRatio(1.25);
        $this->pdf->setCellPaddings(0.08, 0.02, 0.08, 0.02);

        $this->line(4.32);
    }

    /** @param  array<string, string>  $p */
    private function provider(array $p): void
    {
        $this->blockTitle('PRESTADOR / FORNECEDOR');
        $this->row([
            [self::COL[1], self::COL_W, 'CNPJ / CPF / NIF', $p['document']],
            [self::COL[2], self::COL_W, 'Indicador Municipal (Inscrição)', $p['municipal_registration']],
            [self::COL[3], self::COL_W, 'Telefone', $p['phone']],
        ]);
        $this->row([
            [self::COL[0], self::WIDE_W, 'Nome / Nome Empresarial', $p['name']],
            [self::COL[2], self::COL_W, 'Município / Sigla UF', $p['city']],
            [self::COL[3], self::COL_W, 'Código IBGE / CEP', $p['ibge_cep']],
        ]);
        $this->row([
            [self::COL[0], self::WIDE_W, 'Endereço', $p['address']],
            [self::COL[2], self::WIDE_W, 'E-mail', $p['email']],
        ]);
        $this->row([
            [self::COL[0], self::WIDE_W, 'Simples Nacional na Data de Competência', $p['simples']],
            [self::COL[2], self::WIDE_W, 'Regime de Apuração Tributária pelo SN', $p['simples_regime']],
        ]);
        $this->line($this->y);
    }

    /** @param  array<string, string>|null  $p */
    private function party(string $title, ?array $p, string $missing): void
    {
        if ($p === null) {
            $this->notice($missing);

            return;
        }

        $this->blockTitle($title);
        $this->row([
            [self::COL[1], self::COL_W, 'CNPJ / CPF / NIF', $p['document']],
            [self::COL[2], self::COL_W, 'Indicador Municipal (Inscrição)', $p['municipal_registration']],
            [self::COL[3], self::COL_W, 'Telefone', $p['phone']],
        ]);
        $this->row([
            [self::COL[0], self::WIDE_W, 'Nome / Nome Empresarial', $p['name']],
            [self::COL[2], self::COL_W, 'Município / Sigla UF', $p['city']],
            [self::COL[3], self::COL_W, 'Código IBGE / CEP', $p['ibge_cep']],
        ]);
        $this->row([
            [self::COL[0], self::WIDE_W, 'Endereço', $p['address']],
            [self::COL[2], self::WIDE_W, 'E-mail', $p['email']],
        ]);
        $this->line($this->y);
    }

    /** @param  array<string, string>  $s */
    private function service(array $s): void
    {
        $this->blockTitle('SERVIÇO PRESTADO');
        $this->row([
            [self::COL[1], self::COL_W, 'Código de Tributação Nacional / Municipal', $s['code']],
            [self::COL[2], self::COL_W, 'Código da NBS', $s['nbs']],
            [self::COL[3], self::COL_W, 'Local da Prestação / Sigla UF / País', $s['location']],
        ]);

        // Descrição do código de tributação: sem título, numa linha de 0,38 cm.
        $this->pdf->SetFont(self::VALUE_FONT, '', 7);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->Cell(self::WIDTH, 0.38, $s['code_description'], 0, 0, 'L');
        $this->y += 0.40;

        $this->pdf->SetFont(self::LABEL_FONT, 'B', 6);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->Cell(self::WIDTH, 0.26, 'Descrição do Serviço', 0, 2, 'L');
        $this->pdf->SetFont(self::VALUE_FONT, '', 7);
        $this->pdf->SetX(self::LEFT);
        $this->pdf->MultiCell(self::WIDTH, 0.30, $s['description'], 0, 'L');
        $this->y = max($this->y + self::ROW, $this->pdf->GetY() + 0.05);
        $this->line($this->y);
    }

    /** @param  array<string, mixed>  $t */
    private function municipalTax(array $t): void
    {
        if (! $t['subject']) {
            $this->notice('TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN');

            return;
        }

        $this->blockTitle('TRIBUTAÇÃO MUNICIPAL (ISSQN)');
        $this->row([
            [self::COL[1], self::COL_W, 'Tipo de Tributação do ISSQN', $t['taxation']],
            [self::COL[2], self::WIDE_W, 'Município / Sigla UF / País da Incidência do ISSQN', $t['incidence']],
        ]);
        $this->row([
            [self::COL[0], self::COL_W, 'Regime Especial de Tributação do ISSQN', $t['special_regime']],
            [self::COL[1], self::COL_W, 'Tipo de Imunidade do ISSQN', $t['immunity']],
            [self::COL[2], self::COL_W, 'Suspensão da Exigibilidade do ISSQN', $t['suspension']],
            [self::COL[3], self::COL_W, 'Número Processo Suspensão', $t['suspension_process']],
        ]);

        // Linha suprimível quando todos os campos estão vazios no XML (nota 5).
        $benefit = [$t['benefit'], $t['benefit_amount'], $t['deductions'], $t['unconditional_discount']];
        if (array_filter($benefit, fn ($v) => $v !== DanfseData::EMPTY) !== []) {
            $this->row([
                [self::COL[0], self::COL_W, 'Benefício Municipal', $t['benefit']],
                [self::COL[1], self::COL_W, 'Cálculo do BM', $t['benefit_amount']],
                [self::COL[2], self::COL_W, 'Total Deduções/Reduções', $t['deductions']],
                [self::COL[3], self::COL_W, 'Desconto Incondicionado', $t['unconditional_discount']],
            ]);
        }

        $this->row([
            [self::COL[0], self::COL_W, 'BC ISSQN', $t['base']],
            [self::COL[1], self::COL_W, 'Alíquota Aplicada', $t['rate']],
            [self::COL[2], self::COL_W, 'Retenção do ISSQN', $t['withholding']],
            [self::COL[3], self::COL_W, 'ISSQN Apurado', $t['amount']],
        ]);
        $this->line($this->y);
    }

    /** @param  array<string, string>  $f */
    private function federalTax(array $f): void
    {
        $this->blockTitle('TRIBUTAÇÃO FEDERAL (EXCETO CBS)');
        $this->row([
            [self::COL[1], self::COL_W, 'IRRF', $f['irrf']],
            [self::COL[2], self::COL_W, 'Contribuição Previdenciária - Retida', $f['social_security']],
            [self::COL[3], self::COL_W, 'Contribuições Sociais - Retidas', $f['social_contributions']],
        ]);
        $this->row([
            [self::COL[0], self::COL_W, 'PIS - Débito Apuração Própria', $f['pis']],
            [self::COL[1], self::COL_W, 'COFINS - Débito Apuração Própria', $f['cofins']],
            [self::COL[2], self::WIDE_W, 'Descrição Contrib. Sociais - Retidas', $f['withholding_description']],
        ]);
        $this->line($this->y);
    }

    /** @param  array<string, string>  $i */
    private function ibscbs(array $i): void
    {
        $this->blockTitle('TRIBUTAÇÃO IBS / CBS');
        $this->row([
            [self::COL[1], self::COL_W, 'CST / cClassTrib', $i['cst_class']],
            [self::COL[2], self::WIDE_W, 'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF', $i['incidence']],
        ]);
        $this->row([
            [self::COL[0], self::COL_W, 'Exclusões e Reduções da Base de Cálculo', $i['exclusions']],
            [self::COL[1], self::COL_W, 'Base de Cálculo Após Exclusões e Reduções', $i['base']],
            [self::COL[2], self::COL_W, 'Red. Alíquota IBS / Red. Alíquota CBS', $i['rate_reductions']],
            [self::COL[3], self::COL_W, 'Alíquota - IBS UF / IBS Mun', $i['ibs_rates']],
        ]);
        $this->row([
            [self::COL[0], self::COL_W, 'Alíq. Efetiva Municipal - IBS', $i['ibs_mun_effective']],
            [self::COL[1], self::COL_W, 'Valor Apurado Municipal - IBS', $i['ibs_mun_amount']],
            [self::COL[2], self::COL_W, 'Alíq. Efetiva Estadual - IBS', $i['ibs_uf_effective']],
            [self::COL[3], self::COL_W, 'Valor Apurado Estadual - IBS', $i['ibs_uf_amount']],
        ]);
        $this->row([
            [self::COL[0], self::COL_W, 'Valor Total Apurado - IBS', $i['ibs_total']],
            [self::COL[1], self::COL_W, 'Alíquota - CBS', $i['cbs_rate']],
            [self::COL[2], self::COL_W, 'Alíquota Efetiva - CBS', $i['cbs_effective']],
            [self::COL[3], self::COL_W, 'Valor Total Apurado - CBS', $i['cbs_total']],
        ]);
        $this->line($this->y);
    }

    /** @param  array<string, string>  $t */
    private function totals(array $t): void
    {
        $this->blockTitle('VALOR TOTAL DA NFS-E', 0.67);
        $this->row([
            [self::COL[1], self::COL_W, 'Valor da Operação / Serviço', $t['service']],
            [self::COL[2], self::COL_W, 'Desconto Incondicionado', $t['unconditional_discount']],
            [self::COL[3], self::COL_W, 'Desconto Condicionado', $t['conditional_discount']],
        ], 0.69);

        $this->pdf->SetFillColor(...self::SHADE);
        $this->pdf->Rect(self::COL[3], $this->y, self::COL_W, 0.67, 'F');
        $this->row([
            [self::COL[0], self::COL_W, 'Total das Retenções (ISSQN / Federais)', $t['withholdings']],
            [self::COL[1], self::COL_W, 'Valor Líquido da NFS-e', $t['net']],
            [self::COL[2], self::COL_W, 'Total do IBS/CBS', $t['ibscbs']],
            [self::COL[3], self::COL_W, 'Valor Líquido da NFS-e + IBS/CBS', $t['net_with_ibscbs']],
        ], 0.69);
        $this->line($this->y);
    }

    private function complementary(string $text): void
    {
        $this->blockTitle('INFORMAÇÕES COMPLEMENTARES', 0.39, fullWidth: true);
        $this->y += 0.41;

        // Sem canhoto, o bloco ocupa o resto da página (item 2.3.3).
        $this->pdf->SetFont(self::VALUE_FONT, '', 7);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->MultiCell(self::WIDTH, self::BOTTOM - $this->y - 0.05, $text, 0, 'L', false, 1, '', '', true, 0, false, true, self::BOTTOM - $this->y - 0.05, 'T', true);
    }

    /** Título do bloco na primeira coluna da primeira linha, com fundo cinza (itens 2.2.3 e 2.4.1). */
    private function blockTitle(string $title, float $height = self::ROW, bool $fullWidth = false): void
    {
        $this->pdf->SetFillColor(...self::SHADE);
        $this->pdf->SetFont(self::LABEL_FONT, 'B', 7);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->Cell($fullWidth ? self::WIDTH : self::COL_W, $height - 0.02, $title, 0, 0, 'L', true, '', 1, false, 'T', 'C');
    }

    /** Bloco suprimido: uma linha só com o aviso da NT (notas 2, 3 e 4). */
    private function notice(string $text): void
    {
        $this->pdf->SetFillColor(...self::SHADE);
        $this->pdf->SetFont(self::LABEL_FONT, 'B', 7);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->Cell(self::WIDTH, self::NOTICE_ROW, $text, 0, 0, 'L', true, '', 1, false, 'T', 'C');
        $this->y += self::NOTICE_ROW;
        $this->line($this->y);
    }

    /** @param  list<array{0: float, 1: float, 2: string, 3: string}>  $fields */
    private function row(array $fields, float $height = self::ROW): void
    {
        foreach ($fields as [$x, $w, $label, $value]) {
            $this->field($x, $this->y, $w, $height, $label, $value);
        }
        $this->y += $height;
    }

    private function field(float $x, float $y, float $w, float $h, string $label, string $value, bool $upperLabel = false): void
    {
        $this->pdf->SetFont(self::LABEL_FONT, 'B', $upperLabel ? 7 : 6);
        $this->pdf->SetXY($x, $y + 0.03);
        $this->pdf->Cell($w, 0.26, $label, 0, 0, 'L', false, '', 1);

        $this->pdf->SetFont(self::VALUE_FONT, '', 7);
        $this->pdf->SetXY($x, $y + 0.29);
        $this->pdf->Cell($w, $h - 0.32, $value, 0, 0, 'L', false, '', 1, false, 'T', 'T');
    }

    private function line(float $y): void
    {
        $this->pdf->SetLineWidth(0.0176); // 0,5 ponto
        $this->pdf->Line(self::LEFT, $y, self::LEFT + self::WIDTH, $y);
    }

    /** Marca d'água diagonal em cinza K35, 50 pt ou mais (item 2.5). */
    private function watermark(string $text): void
    {
        $this->pdf->SetAlpha(0.55);
        $this->pdf->SetTextColor(166, 166, 166);
        $this->pdf->SetFont(self::LABEL_FONT, '', 90);
        $this->pdf->StartTransform();
        $this->pdf->Rotate(45, 10.5, 14.85);
        $this->pdf->SetXY(0, 13.8);
        $this->pdf->Cell(21.0, 2.0, $text, 0, 0, 'C');
        $this->pdf->StopTransform();
        $this->pdf->SetAlpha(1);
        $this->pdf->SetTextColor(0);
    }
}
