<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\MunicipalAgreementsContract;
use App\Modules\Invoices\Models\MunicipalAgreement;
use App\Modules\Invoices\Services\Adn\AgreementCheck;
use App\Modules\Invoices\Services\Adn\FakeMunicipalAgreements;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

class MunicipalAgreementsTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
    }

    public function test_reads_the_adn_agreement_responses(): void
    {
        $active = AgreementCheck::fromResponse(200, [
            'parametrosConvenio' => [
                'aderenteAmbienteNacional' => 1, 'aderenteEmissorNacional' => 1, 'situacaoEmissaoPadraoContribuintesRFB' => 0,
                'aderenteMAN' => 0, 'permiteAproveitametoDeCreditos' => true,
            ],
            'mensagem' => 'Parâmetros do convênio recuperados com sucesso.',
        ]);
        $this->assertSame(AgreementCheck::ACTIVE, $active->status);
        $this->assertTrue($active->nationalEmitter);
        $this->assertFalse($active->rfbStandardIssuance);
        $this->assertTrue($active->creditUse);

        $inactive = AgreementCheck::fromResponse(404, ['parametrosConvenio' => null, 'mensagem' => 'O convênio do o município <Ariquemes/RO> ainda não está ativo no Sistema Nacional da NFS-e']);
        $this->assertSame(AgreementCheck::INACTIVE, $inactive->status);

        $this->assertSame(AgreementCheck::NOT_FOUND, AgreementCheck::fromResponse(404, ['mensagem' => 'Convênio para o  município não encontrado.'])->status);
        $this->assertSame(AgreementCheck::UNKNOWN, AgreementCheck::fromResponse(503, null)->status);
    }

    public function test_command_syncs_the_requested_states_and_keeps_the_last_known_state_on_failure(): void
    {
        $this->fake()
            ->answer('production', '1200401', $this->active(nationalEmitter: true))
            ->answer('production', '1200013', $this->active(nationalEmitter: false));

        $this->artisan('invoices:sync-agreements', ['--environment' => ['production'], '--uf' => ['ac']])->assertSuccessful();

        $this->assertSame(22, MunicipalAgreement::query()->where('environment', 'production')->count());
        $this->assertTrue(MunicipalAgreement::query()->where('city_code', '1200401')->first()->allowsNationalIssuance());
        $this->assertSame('adn_only', MunicipalAgreement::query()->where('city_code', '1200013')->first()->situation());
        $this->assertSame('not_found', MunicipalAgreement::query()->where('city_code', '1200054')->first()->situation());

        $this->fake()->answer('production', '1200401', AgreementCheck::unknown('timeout'));
        $this->artisan('invoices:sync-agreements', ['--environment' => ['production'], '--uf' => ['AC']])->assertSuccessful();

        $this->assertTrue(MunicipalAgreement::query()->where('city_code', '1200401')->first()->allowsNationalIssuance(), 'Sem resposta, vale a última situação.');
        $this->assertSame(['production'], array_values(array_unique(array_column($this->fake()->queries, 'environment'))));
    }

    public function test_panel_lists_and_filters_the_agreements(): void
    {
        $this->configureIssuer($this->tenant, ['environment' => 'production']);
        $this->seedAgreements();

        $this->actingAs($this->owner)
            ->get(route('invoices.agreements', ['situation' => 'national_emitter']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Agreements')
                ->where('counts.national_emitter', 2)
                ->where('counts.adn_only', 1)
                ->where('counts.inactive', 1)
                ->has('agreements.data', 2)
                ->where('agreements.data.0.name', 'São Paulo')
                ->where('issuerCity.label', 'Novo Hamburgo / RS')
                ->where('issuerCity.agreement.situation', 'adn_only'));

        $this->actingAs($this->owner)
            ->get(route('invoices.agreements', ['search' => 'paulo', 'uf' => 'SP']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('agreements.data', 1)
                ->where('agreements.data.0.city_code', '3550308')
                ->where('counts.national_emitter', 1));

        $this->actingAs($this->owner)
            ->get(route('invoices.create'))
            ->assertInertia(fn (Assert $page) => $page->where('issuer.agreement_situation', 'adn_only'));
    }

    public function test_api_lists_and_shows_agreements(): void
    {
        $this->seedAgreements();
        $key = app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:read'])['plaintext'];

        $this->withToken($key)
            ->getJson('/api/v1/invoices/municipalities?situation=national_emitter&uf=SP')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city_code', '3550308')
            ->assertJsonPath('data.0.issues_through_sefin', true)
            ->assertJsonPath('data.0.agreement.national_emitter', true);

        $this->withToken($key)
            ->getJson('/api/v1/invoices/municipalities/4313409')
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Hamburgo')
            ->assertJsonPath('data.situation', 'adn_only')
            ->assertJsonPath('data.issues_through_sefin', false);

        $this->withToken($key)
            ->getJson('/api/v1/invoices/municipalities/4313409?environment=restricted')
            ->assertNotFound();
    }

    private function seedAgreements(): void
    {
        $row = fn (string $code, string $status, ?bool $emitter) => [
            'city_code' => $code, 'environment' => 'production', 'status' => $status,
            'national_environment' => $status === 'active' ? true : null, 'national_emitter' => $emitter, 'checked_at' => now(),
        ];

        foreach ([
            $row('3550308', 'active', true),
            $row('4314902', 'active', true),
            $row('4313409', 'active', false),
            $row('1100023', 'inactive', null),
        ] as $attrs) {
            MunicipalAgreement::query()->create($attrs);
        }
    }

    private function active(bool $nationalEmitter): AgreementCheck
    {
        return new AgreementCheck(AgreementCheck::ACTIVE, nationalEnvironment: true, nationalEmitter: $nationalEmitter);
    }

    private function fake(): FakeMunicipalAgreements
    {
        return app(MunicipalAgreementsContract::class);
    }
}
