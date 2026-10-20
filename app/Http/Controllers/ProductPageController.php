<?php

namespace App\Http\Controllers;

use App\Http\Seo\MarketingPages;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/** Página própria de cada produto (/assinatura-eletronica, /emissor-nfse, /disparo-de-email), para busca orgânica. */
class ProductPageController extends Controller
{
    public function __construct(protected PlanCatalogService $catalog) {}

    public function __invoke(string $product): Response
    {
        $page = MarketingPages::PRODUCTS[$product] ?? abort(404);
        $products = $this->catalog->products();
        $current = collect($products)->firstWhere('key', $product);
        $seo = MarketingPages::product($product, $current);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'headline' => $page['headline'],
            'canRegister' => Route::has('register'),
            'products' => $products,
            'faq' => MarketingPages::faq($product),
            'seoTitle' => $seo->title,
            'apiBaseUrl' => rtrim(url('/api/v1'), '/'),
            'mailingFreeQuota' => [
                'daily' => (int) config('mailing.quota.free.daily'),
                'monthly' => (int) config('mailing.quota.free.monthly'),
            ],
            // Endereço de cada página de produto, para os links cruzados.
            'productPaths' => collect(MarketingPages::PRODUCTS)->map(fn (array $p) => $p['path'])->all(),
        ])->withViewData('seo', $seo);
    }
}
