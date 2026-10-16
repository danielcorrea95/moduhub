<?php

namespace Tests\Feature\Invoices;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\MunicipalParametersContract;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\Adn\AdnParametersClient;
use App\Modules\Invoices\Services\Adn\FakeMunicipalParameters;
use App\Modules\Invoices\Services\Adn\ServiceCodeCheck;
use App\Platform\Contracts\ApiKeyServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithInvoices;
use Tests\TestCase;

/** Checagem prévia do código de serviço nos parâmetros do município (ADN), para evitar a E0312. */
class ServiceCodeCheckTest extends TestCase
{
    use InteractsWithInvoices, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Issuer $issuer;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->makeInvoicesTenant();
        $this->issuer = $this->configureIssuer($this->tenant);
    }

    public function test_formats_the_code_as_the_adn_expects(): void
    {
        $this->assertSame('08.02.01.000', ServiceCodeCheck::formatCode('080201', null));
        $this->assertSame('01.07.01.012', ServiceCodeCheck::formatCode('010701', '012'));
    }

    public function test_adn_client_reads_the_rate_and_the_missing_parametrization(): void
    {
        Http::fake([
            'adn.producaorestrita.nfse.gov.br/parametrizacao/4313409/08.02.01.000/2026-10-16/aliquota' => Http::response([
                'aliquotas' => ['08.02.01.000' => [['Incidencia' => 'SIM', 'Aliq' => 2.0, 'DtIni' => '2026-05-11T00:00:00', 'DtFim' => null]]],
                'mensagem' => 'Alíquotas recuperadas com sucesso.',
            ]),
            'adn.producaorestrita.nfse.gov.br/parametrizacao/4313409/01.01.01.000/*' => Http::response(['aliquotas' => null, 'mensagem' => 'Alíquotas não encontradas.'], 404),
        ]);
        $client = app(AdnParametersClient::class);
        $date = Carbon::parse('2026-10-16');

        $found = $client->serviceRate($this->issuer, '4313409', '080201', null, $date);
        $this->assertSame(ServiceCodeCheck::PARAMETRIZED, $found->status);
        $this->assertSame(2.0, $found->rate);

        $this->assertTrue($client->serviceRate($this->issuer, '4313409', '010101', null, $date)->isNotParametrized());
    }

    public function test_adn_failures_never_block_the_issue(): void
    {
        Http::fake([
            'adn.producaorestrita.nfse.gov.br/parametrizacao/4313409/08.02.01.000/*' => fn () => throw new ConnectionException('timeout'),
            'adn.producaorestrita.nfse.gov.br/parametrizacao/4313409/01.07.01.000/*' => Http::response('erro', 500),
        ]);
        $client = app(AdnParametersClient::class);

        $this->assertSame(ServiceCodeCheck::UNKNOWN, $client->serviceRate($this->issuer, '4313409', '080201', null, now())->status);
        $this->assertSame(ServiceCodeCheck::UNKNOWN, $client->serviceRate($this->issuer, '4313409', '010701', null, now())->status);
    }

    public function test_issue_is_refused_before_reaching_the_sefin_when_the_code_is_not_parametrized(): void
    {
        $this->parameters()->withoutParametrization('4313409', '01.07.01.000');

        $response = $this->withToken($this->key())
            ->postJson('/api/v1/invoices', $this->invoicePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service.national_tax_code');

        $this->assertStringContainsString('01.07.01.000 não está parametrizado em Novo Hamburgo', $response->json('errors')['service.national_tax_code'][0]);
        $this->assertSame([], $this->sefin()->requests, 'Nada foi enviado à Sefin.');
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
        $this->assertSame(1, Issuer::withoutGlobalScopes()->value('next_dps_number'), 'O número da DPS não foi consumido.');
    }

    public function test_parametrized_code_issues_normally(): void
    {
        $this->parameters()->parametrize('4313409', '01.07.01.000', 2.0);

        $this->withToken($this->key())->postJson('/api/v1/invoices', $this->invoicePayload())->assertCreated();
        $this->assertSame(['4313409|01.07.01.000'], $this->parameters()->queries);
    }

    public function test_does_not_check_when_the_service_is_provided_in_another_city(): void
    {
        // Para alguns serviços o ISS é do local da prestação; sem saber qual município consultar, não bloqueia.
        $this->parameters()->withoutParametrization('4313409', '01.07.01.000');

        $this->withToken($this->key())
            ->postJson('/api/v1/invoices', $this->invoicePayload(['service' => ['city_code' => '4314902']]))
            ->assertCreated();
        $this->assertSame([], $this->parameters()->queries);
    }

    public function test_panel_live_check_reports_rate_and_missing_code(): void
    {
        $this->parameters()->parametrize('4313409', '08.02.01.000', 2.0)->withoutParametrization('4313409', '01.01.01.000');

        $this->actingAs($this->owner)
            ->getJson(route('invoices.service-code-check', ['national_tax_code' => '08.02.01', 'competence_date' => '2026-10-16']))
            ->assertOk()
            ->assertJson(['status' => 'parametrized', 'service_code' => '08.02.01.000', 'rate' => 2.0, 'message' => null]);

        $this->actingAs($this->owner)
            ->getJson(route('invoices.service-code-check', ['national_tax_code' => '010101', 'competence_date' => '2026-10-16']))
            ->assertOk()
            ->assertJsonPath('status', 'not_parametrized')
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'competência 16/10/2026'));
    }

    private function parameters(): FakeMunicipalParameters
    {
        return app(MunicipalParametersContract::class);
    }

    private function key(): string
    {
        return app(ApiKeyServiceContract::class)->generate($this->tenant, $this->owner, 'Teste', ['invoices:*'])['plaintext'];
    }
}
