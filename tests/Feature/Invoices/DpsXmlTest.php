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
