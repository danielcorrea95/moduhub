<?php

namespace Tests\Feature\Invoices;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\CancellationBuilder;
use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use App\Modules\Invoices\Services\Nfse\IssuerCredentials;
use App\Modules\Invoices\Services\Nfse\XmlSchemaValidator;
use App\Modules\Invoices\Services\Nfse\XmlSigner;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesPkcs12;
use Tests\TestCase;

/**
 * DPS e pedido de cancelamento contra os XSD oficiais v1.01, e a assinatura conferida como a Sefin confere.
 */
class DpsXmlTest extends TestCase
{
    use CreatesPkcs12;

    private const CNPJ = '12345678000195';

    public function test_dps_follows_the_official_schema_and_signature_verifies(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $credentials = $this->credentials();

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $credentials);
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<infDPS Id="DPS431340921234567800019500001000000000000042">', $xml);
        $this->assertStringContainsString('<tpAmb>2</tpAmb>', $xml);
        $this->assertMatchesRegularExpression('/<dhEmi>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-03:00<\/dhEmi>/', $xml);
        // Texto saneado para o leiaute: sem quebra de linha e sem travessão fora do Latin-1.
        $this->assertStringContainsString('<xDescServ>Suporte técnico - outubro/2026 Atendimento remoto</xDescServ>', $xml);
        $this->assertStringContainsString('<pTotTrib><pTotTribFed>0.00</pTotTribFed>', $xml);
        $this->assertStringNotContainsString('<xNome>Acme', $xml, 'Com tpEmit=1 o prestador não leva xNome.');

        $this->assertSignatureIsValid($xml, 'infDPS');
    }

    public function test_mei_declares_it_does_not_inform_approximate_taxes(): void
    {
        [$issuer, $invoice] = $this->fixtures(['simples_nacional' => Issuer::SIMPLES_MEI, 'simples_regime' => null]);

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $this->assertStringContainsString('<indTotTrib>0</indTotTrib>', $doc->saveXML());
        $this->assertStringNotContainsString('regApTribSN', $doc->saveXML());
    }

    public function test_schema_violations_surface_as_validation_errors(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->service = ['national_tax_code' => '17', 'description' => 'Consultoria'];

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);

        try {
            app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');
            $this->fail('O XML deveria ter sido recusado pelo schema.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cTribNac', implode(' ', $e->errors()['dps']));
        }
    }

    public function test_replacement_dps_carries_the_subst_group(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $original = new Invoice;
        $original->forceFill(['access_key' => str_repeat('3', 50)]);
        $invoice->forceFill(['substitution_reason_code' => '99', 'substitution_reason' => 'Descrição do serviço saiu incompleta']);
        $invoice->setRelation('replaces', $original);
        $invoice->replaces_invoice_id = '01JZZZZZZZZZZZZZZZZZZZZZZZ';

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $this->assertStringContainsString(
            '<cLocEmi>4313409</cLocEmi><subst><chSubstda>'.str_repeat('3', 50).'</chSubstda><cMotivo>99</cMotivo>'
            .'<xMotivo>Descrição do serviço saiu incompleta</xMotivo></subst><prest>',
            $doc->saveXML(),
        );
    }

    public function test_export_to_a_foreign_taker_with_intermediary_discounts_and_deduction(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->forceFill([
            'taker' => [
                'nif' => 'DE123456789',
                'name' => 'Müller GmbH',
                'email' => 'billing@mueller.test',
                'address' => [
                    'country' => 'DE', 'postal_code' => '10115', 'street' => 'Invalidenstraße', 'number' => '117',
                    'district' => 'Mitte', 'city' => 'Berlin', 'state' => 'Berlin',
                ],
            ],
            'intermediary' => ['document' => '52998224725', 'name' => 'Representante Comercial'],
            'service' => [
                'national_tax_code' => '010701',
                'description' => 'Desenvolvimento de software sob encomenda',
                'result_country' => 'DE',
                'foreign_trade' => ['provision_mode' => '1', 'currency' => '978', 'foreign_amount' => '250.00'],
            ],
            'iss_taxation' => Invoice::ISS_EXPORT,
            'iss_withheld' => true,
            'iss_withholder' => Invoice::WITHHOLDER_INTERMEDIARY,
            'unconditional_discount' => '100.00',
            'conditional_discount' => '15.50',
            'deduction_amount' => '200.00',
        ]);

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<toma><NIF>DE123456789</NIF><xNome>Müller GmbH</xNome><end><endExt><cPais>DE</cPais><cEndPost>10115</cEndPost><xCidade>Berlin</xCidade>', $xml);
        $this->assertStringContainsString('<interm><CPF>52998224725</CPF><xNome>Representante Comercial</xNome></interm>', $xml);
        $this->assertStringContainsString('<comExt><mdPrestacao>1</mdPrestacao><vincPrest>0</vincPrest><tpMoeda>978</tpMoeda><vServMoeda>250.00</vServMoeda><mecAFComexP>01</mecAFComexP><mecAFComexT>01</mecAFComexT><movTempBens>1</movTempBens><mdic>0</mdic></comExt>', $xml);
        $this->assertStringContainsString('<vDescCondIncond><vDescIncond>100.00</vDescIncond><vDescCond>15.50</vDescCond></vDescCondIncond><vDedRed><vDR>200.00</vDR></vDedRed>', $xml);
        $this->assertStringContainsString('<tribISSQN>3</tribISSQN><cPaisResult>DE</cPaisResult><tpRetISSQN>3</tpRetISSQN>', $xml);
    }

    public function test_foreign_taker_without_nif_and_immune_service(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->forceFill([
            'taker' => ['nif_absence_reason' => '2', 'name' => 'John Doe'],
            'iss_taxation' => Invoice::ISS_IMMUNE,
            'immunity_type' => 3,
            'deduction_percent' => '10.00',
        ]);

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<toma><cNaoNIF>2</cNaoNIF><xNome>John Doe</xNome></toma>', $xml);
        $this->assertStringContainsString('<vDedRed><pDR>10.00</pDR></vDedRed>', $xml);
        $this->assertStringContainsString('<tribISSQN>2</tribISSQN><tpImunidade>3</tpImunidade><tpRetISSQN>1</tpRetISSQN>', $xml);
    }

    public function test_ibs_cbs_group_declares_the_classification(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->ibs_cbs = ['cst' => '000', 'class_code' => '000001', 'operation_code' => '100301', 'final_consumer' => false];

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $this->assertStringContainsString(
            '</valores><IBSCBS><finNFSe>0</finNFSe><indFinal>0</indFinal><cIndOp>100301</cIndOp><indDest>0</indDest>'
            .'<valores><trib><gIBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib></gIBSCBS></trib></valores></IBSCBS></infDPS>',
            $doc->saveXML(),
        );
    }

    public function test_deduction_by_documents_covers_every_document_kind(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $base = ['reason' => '2', 'issued_on' => '2026-10-01', 'deductible_amount' => '100.00', 'amount' => '50.00'];
        $invoice->deduction_documents = [
            ['kind' => Invoice::DEDUCTION_DOC_NFSE, 'access_key' => str_repeat('1', 50), ...$base,
                'supplier' => ['document' => '11222333000181', 'name' => 'Fornecedor de Materiais Ltda']],
            ['kind' => Invoice::DEDUCTION_DOC_NFE, 'access_key' => str_repeat('2', 44), ...$base],
            ['kind' => Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE, 'city_code' => '3550308', 'number' => '1234', 'verification_code' => 'AbC12345', ...$base],
            ['kind' => Invoice::DEDUCTION_DOC_PAPER, 'number' => '581', 'model' => '1', 'series' => 'A1', ...$base],
            ['kind' => Invoice::DEDUCTION_DOC_FISCAL, 'number' => 'CT-e 9988', ...$base],
            ['kind' => Invoice::DEDUCTION_DOC_OTHER, 'number' => 'Recibo 17', ...$base, 'reason' => '99', 'reason_description' => 'Taxa de cartório'],
        ];

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $tail = '<tpDedRed>2</tpDedRed><dtEmiDoc>2026-10-01</dtEmiDoc><vDedutivelRedutivel>100.00</vDedutivelRedutivel><vDeducaoReducao>50.00</vDeducaoReducao>';
        $this->assertStringContainsString('<vDedRed><documentos><docDedRed><chNFSe>'.str_repeat('1', 50).'</chNFSe>'.$tail.'<fornec><CNPJ>11222333000181</CNPJ><xNome>Fornecedor de Materiais Ltda</xNome></fornec></docDedRed>', $xml);
        $this->assertStringContainsString('<NFSeMun><cMunNFSeMun>3550308</cMunNFSeMun><nNFSeMun>000000000001234</nNFSeMun><cVerifNFSeMun>AbC12345</cVerifNFSeMun></NFSeMun>', $xml);
        $this->assertStringContainsString('<NFNFS><nNFS>0000581</nNFS><modNFS>000000000000001</modNFS><serieNFS>A1</serieNFS></NFNFS>', $xml);
        $this->assertStringContainsString('<nDoc>Recibo 17</nDoc><tpDedRed>99</tpDedRed><xDescOutDed>Taxa de cartório</xDescOutDed>', $xml);
        $this->assertSame(6, substr_count($xml, '<docDedRed>'));
    }

    public function test_federal_taxes_municipal_benefit_and_iss_suspension(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->forceFill([
            'federal_taxes' => [
                'pis_cofins' => ['cst' => '01', 'base' => '1500.00', 'pis_rate' => '0.65', 'cofins_rate' => '3.00', 'pis_amount' => '9.75', 'cofins_amount' => '45.00', 'withholding' => '3'],
                'irrf' => ['rate' => '1.50', 'amount' => '22.50'],
                'csll' => ['rate' => '1.00', 'amount' => '15.00'],
                'inss' => ['rate' => null, 'amount' => '165.00'],
            ],
            'municipal_benefit' => ['number' => '35503080000012', 'reduction_percent' => '20.00'],
            'iss_suspension' => ['type' => '1', 'process_number' => '50012345620268260100'],
        ]);

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<tribISSQN>1</tribISSQN><exigSusp><tpSusp>1</tpSusp><nProcesso>000000000050012345620268260100</nProcesso></exigSusp><BM><nBM>35503080000012</nBM><pRedBCBM>20.00</pRedBCBM></BM><tpRetISSQN>1</tpRetISSQN>', $xml);
        $this->assertStringContainsString(
            '<tribFed><piscofins><CST>01</CST><vBCPisCofins>1500.00</vBCPisCofins><pAliqPis>0.65</pAliqPis><pAliqCofins>3.00</pAliqCofins><vPis>9.75</vPis><vCofins>45.00</vCofins><tpRetPisCofins>3</tpRetPisCofins></piscofins>'
            .'<vRetCP>165.00</vRetCP><vRetIRRF>22.50</vRetIRRF><vRetCSLL>15.00</vRetCSLL></tribFed><totTrib>',
            $xml,
        );
    }

    public function test_construction_event_additional_info_and_full_ibs_cbs(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $address = ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista'];
        $invoice->forceFill([
            'service' => [
                'national_tax_code' => '070201',
                'description' => 'Execução de obra',
                'construction' => ['registration' => 'IPTU-123', 'address' => $address],
                'event' => ['name' => 'Feira do Livro', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-03', 'event_id' => 'EVT-2026-77'],
                'additional_info' => ['technical_document' => 'ART 123', 'reference' => 'Contrato 45/2026', 'order' => 'PED-9', 'order_items' => ['1', '2'], 'notes' => 'Aluno: Fulano de Tal'],
            ],
            'intermediary' => ['document' => '52998224725', 'name' => 'Representante'],
            'intermediary_received' => '1600.00',
            'ibs_cbs' => [
                'cst' => '000', 'class_code' => '000001', 'operation_code' => '100301', 'final_consumer' => null,
                'operation_type' => '1',
                'referenced_invoices' => [str_repeat('4', 50)],
                'government_entity' => '4',
                'recipient' => ['document' => '11222333000181', 'name' => 'Filial Destinatária', 'municipal_registration' => '999'],
                'property' => ['cib' => 'AB12CD34'],
                'reimbursements' => [
                    ['kind' => 'dfe', 'dfe_type' => '2', 'access_key' => str_repeat('5', 44), 'issued_on' => '2026-10-01', 'competence_on' => '2026-10-01', 'type' => '03', 'amount' => '100.00',
                        'supplier' => ['document' => '11222333000181', 'name' => 'Gráfica']],
                    ['kind' => 'fiscal_document', 'city_code' => '3550308', 'number' => '77', 'description' => 'Nota municipal', 'issued_on' => '2026-10-01', 'competence_on' => '2026-10-01', 'type' => '99', 'type_description' => 'Taxa', 'amount' => '20.00'],
                    ['kind' => 'other_document', 'number' => 'R-1', 'description' => 'Recibo', 'issued_on' => '2026-10-01', 'competence_on' => '2026-10-01', 'type' => '01', 'amount' => '10.00'],
                ],
            ],
        ]);

        $doc = app(DpsBuilder::class)->build($invoice, $issuer);
        app(XmlSigner::class)->sign($this->element($doc, 'infDPS'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'DPS_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<obra><inscImobFisc>IPTU-123</inscImobFisc><end><CEP>01310100</CEP><xLgr>Av. Paulista</xLgr>', $xml);
        $this->assertStringContainsString('<atvEvento><xNome>Feira do Livro</xNome><dtIni>2026-11-01</dtIni><dtFim>2026-11-03</dtFim><idAtvEvt>EVT-2026-77</idAtvEvt></atvEvento>', $xml);
        $this->assertStringContainsString('<infoCompl><idDocTec>ART 123</idDocTec><docRef>Contrato 45/2026</docRef><xPed>PED-9</xPed><gItemPed><xItemPed>1</xItemPed><xItemPed>2</xItemPed></gItemPed><xInfComp>Aluno: Fulano de Tal</xInfComp></infoCompl>', $xml);
        $this->assertStringContainsString('<vServPrest><vReceb>1600.00</vReceb><vServ>1500.00</vServ></vServPrest>', $xml);
        $this->assertStringContainsString('<cIndOp>100301</cIndOp><tpOper>1</tpOper><gRefNFSe><refNFSe>'.str_repeat('4', 50).'</refNFSe></gRefNFSe><tpEnteGov>4</tpEnteGov><indDest>1</indDest><dest><CNPJ>11222333000181</CNPJ><xNome>Filial Destinatária</xNome></dest><imovel><cCIB>AB12CD34</cCIB></imovel>', $xml);
        $this->assertStringContainsString('<valores><gReeRepRes><documentos><dFeNacional><tipoChaveDFe>2</tipoChaveDFe>', $xml);
        $this->assertSame(3, substr_count($xml, '<documentos>'));
    }

    public function test_cancellation_request_follows_the_schema(): void
    {
        [$issuer, $invoice] = $this->fixtures();
        $invoice->access_key = str_repeat('1', 50);

        $doc = app(CancellationBuilder::class)->build($invoice, $issuer, Invoice::CANCEL_ISSUE_ERROR, 'Valor do serviço informado errado');
        app(XmlSigner::class)->sign($this->element($doc, 'infPedReg'), $this->credentials());
        app(XmlSchemaValidator::class)->assertValid($doc, 'pedRegEvento_v1.01.xsd');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('Id="PRE'.str_repeat('1', 50).'101101"', $xml);
        $this->assertStringContainsString('<xDesc>Cancelamento de NFS-e</xDesc>', $xml);
        $this->assertSignatureIsValid($xml, 'infPedReg');
    }

    /** @return array{0: Issuer, 1: Invoice} */
    private function fixtures(array $issuerOverrides = []): array
    {
        $issuer = new Issuer(array_merge([
            'legal_name' => 'Acme Ltda',
            'cnpj' => self::CNPJ,
            'municipal_registration' => '123456',
            'city_code' => '4313409',
            'simples_nacional' => Issuer::SIMPLES_ME_EPP,
            'simples_regime' => Issuer::SIMPLES_REGIME_ALL_IN_SN,
            'special_regime' => Issuer::SPECIAL_REGIME_NONE,
            'phone' => '5135940000',
            'email' => 'fiscal@acme.test',
            'environment' => Issuer::ENV_RESTRICTED,
            'dps_series' => '1',
        ], $issuerOverrides));

        $invoice = new Invoice([
            'competence_date' => now()->toDateString(),
            'taker_document' => '11222333000181',
            'taker_name' => 'Cliente Exemplo S.A.',
            'taker' => [
                'document' => '11222333000181',
                'name' => 'Cliente “Exemplo” S.A.',
                'email' => 'financeiro@cliente.test',
                'phone' => '51999990000',
                'address' => [
                    'postal_code' => '93510000',
                    'street' => 'Rua Júlio de Castilhos',
                    'number' => '100',
                    'district' => 'Centro',
                    'city_code' => '4313409',
                ],
            ],
            'service' => [
                'national_tax_code' => '010701',
                'description' => "Suporte técnico — outubro/2026\nAtendimento remoto",
            ],
            'amount' => '1500.00',
            'iss_withheld' => false,
            'iss_taxation' => Invoice::ISS_TAXABLE,
        ]);
        $invoice->forceFill([
            'dps_series' => '1',
            'dps_number' => 42,
            'dps_id' => DpsBuilder::dpsId($issuer, '1', 42),
            'emitted_at' => now()->subMinute(),
        ]);

        return [$issuer, $invoice];
    }

    private function credentials(): IssuerCredentials
    {
        openssl_pkcs12_read($this->makePkcs12('ACME LTDA:'.self::CNPJ), $certs, self::PKCS12_PASSWORD);

        return new IssuerCredentials($certs['pkey'], $certs['cert']);
    }

    private function element(DOMDocument $doc, string $name): DOMElement
    {
        return $doc->getElementsByTagNameNS(DpsBuilder::NS, $name)->item(0);
    }

    /** Refaz a verificação a partir do XML serializado, como o destinatário faria. */
    private function assertSignatureIsValid(string $xml, string $signedElement): void
    {
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', DpsBuilder::NS);
        $xpath->registerNamespace('ds', XmlSigner::DS_NS);

        $target = $xpath->query('//n:'.$signedElement)->item(0);
        $digest = base64_encode(sha1($target->C14N(false, false), true));
        $this->assertSame($digest, $xpath->evaluate('string(//ds:DigestValue)'));
        $this->assertSame('#'.$target->getAttribute('Id'), $xpath->evaluate('string(//ds:Reference/@URI)'));

        $certificate = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($xpath->evaluate('string(//ds:X509Certificate)'), 64)
            ."-----END CERTIFICATE-----\n";
        $signedInfo = $xpath->query('//ds:SignedInfo')->item(0)->C14N(false, false);
        $signature = base64_decode($xpath->evaluate('string(//ds:SignatureValue)'));

        $this->assertSame(1, openssl_verify($signedInfo, $signature, $certificate, OPENSSL_ALGO_SHA1));
    }
}
