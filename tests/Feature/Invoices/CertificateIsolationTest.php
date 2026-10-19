<?php

namespace Tests\Feature\Invoices;

use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Nfse\IssuerCredentials;
use App\Modules\Invoices\Services\Nfse\IssuerCredentialsLoader;
use App\Modules\Signatures\Services\IcpCertificateService;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

/**
 * Um workspace nunca assina com o certificado de outro, nem quando a emissão roda sem tenant no
 * contexto (job, comando), onde o TenantScope não filtra nada.
 */
class CertificateIsolationTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    public function test_issuing_without_tenant_context_signs_with_the_given_tenant_certificate(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $firstIssuer = $this->configureIssuer($first);
        $secondIssuer = $this->configureIssuer($second);
        app(CurrentTenant::class)->clear();

        $invoice = app(InvoiceServiceContract::class)->issue($second, null, $this->normalizedPayload());

        $this->assertSame($second->getKey(), $invoice->tenant_id);
        $this->assertSame($secondIssuer->getKey(), $invoice->issuer_id);
        $this->assertStringContainsString($this->certificateOf($secondIssuer), $invoice->dps_xml);
        $this->assertStringNotContainsString($this->certificateOf($firstIssuer), $invoice->dps_xml);
    }

    public function test_tenant_without_certificate_cannot_issue_even_if_another_tenant_has_one(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $this->configureIssuer($first);
        $this->configureIssuer($second, withCertificate: false);
        app(CurrentTenant::class)->clear();

        try {
            app(InvoiceServiceContract::class)->issue($second, null, $this->normalizedPayload());
            $this->fail('Emitiu sem certificado próprio.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('issuer', $e->errors());
        }
    }

    public function test_external_id_is_not_reused_across_tenants(): void
    {
        [$first] = $this->makeInvoicesTenant('Primeira');
        [$second] = $this->makeInvoicesTenant('Segunda');
        $this->configureIssuer($first);
        $this->configureIssuer($second);
        app(CurrentTenant::class)->clear();
        $service = app(InvoiceServiceContract::class);

        $original = $service->issue($first, null, $this->normalizedPayload(['external_id' => 'pedido-42']));
        $other = $service->issue($second, null, $this->normalizedPayload(['external_id' => 'pedido-42']));

        $this->assertNotSame($original->getKey(), $other->getKey());
        $this->assertSame($second->getKey(), $other->tenant_id);
    }

    /** Lê o certificado guardado sem passar pelo load(), que registraria um uso da chave na auditoria. */
    private function credentialsOf(Issuer $issuer): IssuerCredentials
    {
        $issuer = Issuer::withoutGlobalScopes()->findOrFail($issuer->getKey());

        return app(IssuerCredentialsLoader::class)->fromPkcs12(base64_decode($issuer->certificate_pfx), $issuer->certificate_password);
    }

    private function certificateOf(Issuer $issuer): string
    {
        return $this->credentialsOf($issuer)->certificateBase64();
    }

    /** O serviço recebe o payload depois do FormRequest, já sem máscaras. */
    private function normalizedPayload(array $overrides = []): array
    {
        return $this->invoicePayload(array_replace_recursive([
            'taker' => ['document' => '11222333000181', 'phone' => '51999990000', 'address' => ['postal_code' => '93510000']],
            'service' => ['national_tax_code' => '010701'],
        ], $overrides));
    }

    public function test_password_and_private_key_stay_out_of_traces_and_dumps(): void
    {
        // Em produção o php.ini costuma omitir argumentos dos traces; aqui eles aparecem, para o teste valer.
        $previous = ini_set('zend.exception_ignore_args', '0');
        $password = 'senha-que-nao-pode-vazar';

        try {
            app(IcpCertificateService::class)->parse($this->makePkcs12('ACME LTDA:'.self::ISSUER_CNPJ), $password);
            $this->fail('Abriu o certificado com a senha errada.');
        } catch (ValidationException $e) {
            $this->assertStringNotContainsString($password, print_r($e->getTrace(), true));
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }

        [$tenant] = $this->makeInvoicesTenant();
        $issuer = $this->configureIssuer($tenant);
        $credentials = $this->credentialsOf($issuer);
        $certificate = app(IcpCertificateService::class)->parse($this->makePkcs12('ACME LTDA:'.self::ISSUER_CNPJ), self::PKCS12_PASSWORD);

        $this->assertStringNotContainsString('PRIVATE KEY', print_r($credentials, true));
        $this->assertStringNotContainsString('PRIVATE KEY', print_r($certificate, true));
        $this->assertStringContainsString('PRIVATE KEY', $credentials->privateKeyPem, 'A aplicação ainda lê a chave normalmente.');
    }
}
