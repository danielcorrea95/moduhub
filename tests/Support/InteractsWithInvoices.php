<?php

namespace Tests\Support;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Sefin\FakeSefinClient;
use Illuminate\Support\Str;

/**
 * Workspace com módulo de notas, emitente configurado e certificado de teste, mais o fake da Sefin.
 */
trait InteractsWithInvoices
{
    use CreatesPkcs12;

    protected const ISSUER_CNPJ = '12345678000195';

    /** @return array{0: Tenant, 1: User} */
    protected function makeInvoicesTenant(string $name = 'Acme', string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'status' => 'active']);
        $tenant->users()->attach($user, ['role' => $role]);
        $module = Module::firstOrCreate(['key' => Module::INVOICES], ['name' => 'Notas Fiscais']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return [$tenant, $user];
    }

    protected function configureIssuer(Tenant $tenant, array $overrides = [], bool $withCertificate = true): Issuer
    {
        $service = app(IssuerServiceContract::class);
        $issuer = $service->configure($tenant, array_merge([
            'legal_name' => 'Acme Ltda',
            'trade_name' => 'Acme',
            'cnpj' => self::ISSUER_CNPJ,
            'municipal_registration' => '123456',
            'city_code' => '4313409',
            'city_name' => 'Novo Hamburgo',
            'state' => 'RS',
            'simples_nacional' => Issuer::SIMPLES_ME_EPP,
            'simples_regime' => Issuer::SIMPLES_REGIME_ALL_IN_SN,
            'special_regime' => Issuer::SPECIAL_REGIME_NONE,
            'phone' => '5135940000',
            'email' => 'fiscal@acme.test',
            'environment' => Issuer::ENV_RESTRICTED,
            'dps_series' => '1',
        ], $overrides));

        if ($withCertificate) {
            $issuer = $service->attachCertificate($tenant, $this->makePkcs12('ACME LTDA:'.self::ISSUER_CNPJ), self::PKCS12_PASSWORD);
        }

        return $issuer;
    }

    protected function sefin(): FakeSefinClient
    {
        return app(SefinClientContract::class);
    }

    protected function invoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'external_id' => 'pedido-1001',
            'competence_date' => now()->toDateString(),
            'taker' => [
                'document' => '11.222.333/0001-81',
                'name' => 'Cliente Exemplo S.A.',
                'email' => 'financeiro@cliente.test',
                'phone' => '(51) 99999-0000',
                'address' => [
                    'postal_code' => '93510-000',
                    'street' => 'Rua Júlio de Castilhos',
                    'number' => '100',
                    'complement' => 'Sala 2',
                    'district' => 'Centro',
                    'city_code' => '4313409',
                ],
            ],
            'service' => [
                'national_tax_code' => '01.07.01',
                'description' => "Suporte técnico mensal — outubro/2026\nAtendimento remoto",
            ],
            'values' => [
                'amount' => '1500.00',
                'iss_withheld' => false,
            ],
        ], $overrides);
    }
}
