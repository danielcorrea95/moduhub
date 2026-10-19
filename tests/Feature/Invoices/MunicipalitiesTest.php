<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class MunicipalitiesTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
    }

    public function test_table_resolves_name_and_state_by_ibge_code(): void
    {
        $this->assertSame('São Paulo / SP', Municipalities::label('3550308'));
        $this->assertSame('Novo Hamburgo', Municipalities::name('4313409'));
        $this->assertSame('DF', Municipalities::uf('5300108'));
        $this->assertFalse(Municipalities::exists('3550307'));
        $this->assertNull(Municipalities::label('9999999'));
    }

    public function test_search_ignores_accents_and_puts_prefix_matches_first(): void
    {
        $results = Municipalities::search('sao paulo');

        $this->assertSame('3550308', $results[0]['code']);
        $this->assertSame(['3550308'], array_column(Municipalities::search('3550308'), 'code'));
        $this->assertSame(['RS'], array_values(array_unique(array_column(Municipalities::search('santa/rs', 50), 'uf'))));
    }

    public function test_panel_searches_municipalities_by_name(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('invoices.municipalities', ['q' => 'novo hamb']))
            ->assertOk()
            ->assertJsonPath('data.0', ['code' => '4313409', 'name' => 'Novo Hamburgo', 'uf' => 'RS']);
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('invoices.municipalities', ['q' => 's']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_issuer_city_name_and_state_come_from_the_ibge_table(): void
    {
        $this->actingAs($this->owner)
            ->put(route('invoices.issuer.update'), [
                'legal_name' => 'Acme Ltda',
                'cnpj' => self::ISSUER_CNPJ,
                'city_code' => '3550308',
                'city_name' => 'Sampa',
                'state' => 'RJ',
                'simples_nacional' => 1,
                'special_regime' => 0,
                'environment' => 'restricted',
                'dps_series' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoice_issuers', ['city_code' => '3550308', 'city_name' => 'São Paulo', 'state' => 'SP']);
    }

    public function test_unknown_ibge_code_is_rejected_when_issuing(): void
    {
        $this->configureIssuer($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('invoices.store'), $this->invoicePayload(['service' => ['city_code' => '4313408']]))
            ->assertSessionHasErrors(['service.city_code' => 'O código IBGE informado não corresponde a nenhum município.']);
    }
}
