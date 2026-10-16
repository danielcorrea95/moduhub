<?php

namespace Tests\Feature\Invoices;

use App\Modules\Invoices\Services\Danfse\DanfseData;
use App\Modules\Invoices\Services\Danfse\DanfseRenderer;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/** DANFSe conforme a NT 008/2026, a partir de uma NFS-e no formato devolvido pela Sefin Nacional. */
class DanfseTest extends TestCase
{
    public function test_extracts_and_formats_the_fields_from_the_nfse_xml(): void
    {
        $d = DanfseData::fromNfseXml($this->fixture());

        $this->assertTrue($d->restricted);
        $this->assertSame('35503082212345678000195000000000000126108607888201', $d->access_key);
        $this->assertSame(DanfseData::QR_BASE_URL.'35503082212345678000195000000000000126108607888201', $d->qr_url);
        $this->assertSame('Município: São Paulo / SP', $d->header_city);
        $this->assertSame('15/08/2025', $d->competence);
        $this->assertSame('16/10/2026 20:18:35', $d->issued_at);
        $this->assertSame('Prestador', $d->emitter);
        $this->assertSame('NFS-e Gerada', $d->status);
        $this->assertSame(DanfseData::EMPTY, $d->finality);

        // Com tpEmit=1 nome e endereço do prestador vêm do grupo emit; CEP volta a ter o zero à esquerda.
        $this->assertSame('ACME SOFTWARE LTDA', $d->provider['name']);
        $this->assertSame('12.345.678/0001-95', $d->provider['document']);
        $this->assertSame('3550308 / 01.311-000', $d->provider['ibge_cep']);
        $this->assertSame('São Paulo / SP', $d->provider['city']);
        $this->assertSame('(11) 99999-0000', $d->provider['phone']);

        $this->assertSame('529.982.247-25', $d->taker['document']);
        $this->assertSame('- / RS', $d->taker['city'], 'Sem nome do município no XML, só a UF derivada do código IBGE.');
        $this->assertSame('Rua Julio de Castilhos, 100, Centro', $d->taker['address']);
        $this->assertNull($d->intermediary);

        $this->assertSame('01.01.01 / -', $d->service['code']);
        $this->assertSame('São Paulo / SP', $d->service['location']);
        $this->assertSame('Operação Tributável', $d->iss['taxation']);
        $this->assertSame('Não Retido', $d->iss['withholding']);
        $this->assertSame(DanfseData::EMPTY, $d->iss['amount'], 'Simples Nacional: a Sefin não destaca o ISSQN.');
        $this->assertSame('R$ 10,00', $d->totals['service']);
        $this->assertSame('R$ 10,00', $d->totals['net']);
        $this->assertSame(
            'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: 4,15% ; Estaduais: 0,00% ; Municipais: 2,00%',
            $d->complementary,
        );
    }

    public function test_renders_a_single_a4_page_with_the_mandatory_marks(): void
    {
        $pdf = app(DanfseRenderer::class)->render(DanfseData::fromNfseXml($this->fixture(), cancelled: true));

        $this->assertStringStartsWith('%PDF', $pdf);
        $document = (new Parser)->parseContent($pdf);
        $this->assertCount(1, $document->getPages());

        $text = $document->getText();
        $this->assertStringContainsString('DANFSe v2.0', $text);
        $this->assertStringContainsString('NFS-e SEM VALIDADE JURÍDICA', $text);
        $this->assertStringContainsString('CANCELADA', $text);
        $this->assertStringContainsString('35503082212345678000195000000000000126108607888201', $text);
        $this->assertStringContainsString('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO', $text);
        $this->assertStringContainsString('INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $text);
    }

    public function test_production_invoice_has_no_test_warning_nor_watermark(): void
    {
        $xml = str_replace('<tpAmb>2</tpAmb>', '<tpAmb>1</tpAmb>', $this->fixture());
        $text = (new Parser)->parseContent(app(DanfseRenderer::class)->render(DanfseData::fromNfseXml($xml)))->getText();

        $this->assertStringNotContainsString('SEM VALIDADE', $text);
        $this->assertStringNotContainsString('CANCELADA', $text);
        $this->assertStringContainsString('Tipo de Ambiente: Produção', $text);
    }

    public function test_rejects_xml_that_is_not_an_nfse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DanfseData::fromNfseXml('<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"/>');
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/nfse/nfse-sp-restrita.xml'));
    }
}
