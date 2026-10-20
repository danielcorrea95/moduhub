<?php

namespace Tests\Feature;

use App\Http\Ssr\PublicPagesSsrGateway;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** O que buscadores e robôs de IA recebem sem executar JavaScript. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class]);
    }

    public function test_home_html_has_title_description_canonical_and_json_ld_without_ssr(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="pt-BR">', $html);
        $this->assertSame(1, substr_count($html, '<title'));
        $this->assertStringContainsString('<title inertia>Assinatura eletrônica, emissor de NFS-e e disparo de e-mails - Moduhub</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Assine contratos pelo celular', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/og/moduhub.png').'">', $html);

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
        $graph = collect(json_decode($m[1], true)['@graph']);
        $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication', 'SoftwareApplication', 'SoftwareApplication', 'FAQPage'], $graph->pluck('@type')->all());
        $this->assertSame(24.9, $graph->firstWhere('name', 'Moduhub Disparo de e-mails')['offers']['lowPrice']);
    }

    public function test_faq_on_the_page_is_the_same_as_in_json_ld(): void
    {
        $response = $this->get('/emissor-nfse')->assertOk();

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $response->getContent(), $m);
        $faqPage = collect(json_decode($m[1], true)['@graph'])->firstWhere('@type', 'FAQPage');
        $questions = array_column($faqPage['mainEntity'], 'name');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product', 'invoices')
            ->where('faq', fn ($faq) => collect($faq)->pluck('question')->all() === $questions));
    }

    public function test_each_product_has_its_own_page(): void
    {
        foreach (['/assinatura-eletronica' => 'signatures', '/emissor-nfse' => 'invoices', '/disparo-de-email' => 'mailing'] as $path => $product) {
            $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->where('product', $product));
        }
    }

    public function test_robots_sitemap_and_llms(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /sign/', false)
            ->assertSee('Disallow: /v/', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/emissor-nfse').'</loc>', false);

        $this->get('/llms.txt')->assertOk()
            ->assertSee('# Moduhub', false)
            ->assertSee('NFS-e 100: R$ 79,00 (100 notas por mês, excedente de R$ 0,70 por nota)', false)
            ->assertSee(url('/docs/api'), false);
    }

    public function test_ssr_runs_only_for_public_pages(): void
    {
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
        Http::fake(['*/render' => Http::response(['head' => ['<title inertia>X</title>'], 'body' => '<div id="app">ok</div>'])]);
        $gateway = app(PublicPagesSsrGateway::class);

        $this->assertNull($gateway->dispatch(['component' => 'Dashboard', 'props' => []]));
        $this->assertNull($gateway->dispatch(['component' => 'Billing/Show', 'props' => []]));
        Http::assertNothingSent();

        $this->assertSame('<div id="app">ok</div>', $gateway->dispatch(['component' => 'Welcome', 'props' => []])?->body);
        $this->assertNotNull($gateway->dispatch(['component' => 'Products/Show', 'props' => []]));
    }
}
