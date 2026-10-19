<?php

namespace Tests\Feature\Invoices;

use App\Modules\Invoices\Models\Issuer;
use App\Platform\Security\CertificateEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class CertificateKeyRotationTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    public function test_rotation_moves_every_certificate_to_the_new_key(): void
    {
        [$tenant] = $this->makeInvoicesTenant();
        $issuer = $this->configureIssuer($tenant);
        $oldKey = (string) config('certificates.key');
        $newKey = 'base64:'.base64_encode(random_bytes(32));

        // Passo 1 da troca: chave nova em vigor, a antiga só para leitura.
        $this->useKeys($newKey, [$oldKey]);
        $this->assertSame(self::PKCS12_PASSWORD, $this->fresh($issuer)->certificate_password, 'Com a antiga em previous_keys, tudo continua legível.');

        // Passo 2: recifrar.
        $this->artisan('invoices:reencrypt-certificates')->assertSuccessful();

        // Passo 3: a antiga sai. O certificado continua abrindo, e a chave antiga sozinha já não abre nada.
        $this->useKeys($newKey, []);
        $this->assertSame(self::PKCS12_PASSWORD, $this->fresh($issuer)->certificate_password);

        $raw = DB::table('invoice_issuers')->where('id', $issuer->getKey())->first();
        $old = new Encrypter(base64_decode(substr($oldKey, 7)), 'AES-256-CBC');
        foreach (['certificate_pfx', 'certificate_password'] as $column) {
            try {
                $old->decryptString($raw->{$column});
                $this->fail("{$column} ainda abre com a chave antiga.");
            } catch (DecryptException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_reports_certificates_that_no_known_key_opens(): void
    {
        [$tenant] = $this->makeInvoicesTenant();
        $this->configureIssuer($tenant);

        // Chave antiga perdida: nada abre, e o comando avisa em vez de seguir como se estivesse tudo certo.
        $this->useKeys('base64:'.base64_encode(random_bytes(32)), []);

        $this->artisan('invoices:reencrypt-certificates')
            ->expectsOutputToContain('Não remova as chaves antigas')
            ->assertFailed();
    }

    /** @param  list<string>  $previous */
    private function useKeys(string $key, array $previous): void
    {
        config(['certificates.key' => $key, 'certificates.previous_keys' => $previous]);
        $this->app->forgetInstance(CertificateEncrypter::class);
    }

    private function fresh(Issuer $issuer): Issuer
    {
        return Issuer::query()->withoutTenantScope()->findOrFail($issuer->getKey());
    }
}
