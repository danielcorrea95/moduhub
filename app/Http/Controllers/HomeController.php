<?php

namespace App\Http\Controllers;

use App\Http\Seo\MarketingPages;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(protected PlanCatalogService $catalog) {}

    public function __invoke(): Response
    {
        $products = $this->catalog->products();
        $seo = MarketingPages::home($products);

        return Inertia::render('Welcome', [
            'canRegister' => Route::has('register'),
            'products' => $products,
            'faq' => MarketingPages::faq('home'),
            'seoTitle' => $seo->title,
            'apiBaseUrl' => rtrim(url('/api/v1'), '/'),
            // Cota grátis do disparo, a mesma que o EmailQuotaService aplica.
            'mailingFreeQuota' => [
                'daily' => (int) config('mailing.quota.free.daily'),
                'monthly' => (int) config('mailing.quota.free.monthly'),
            ],
        ])->withViewData('seo', $seo);
    }
}
