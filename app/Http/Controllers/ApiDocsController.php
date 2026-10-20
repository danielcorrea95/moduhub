<?php

namespace App\Http\Controllers;

use App\Http\Seo\MarketingPages;
use App\Models\Module;
use App\Platform\EventCatalog;
use App\Platform\ScopeCatalog;
use App\Providers\AppServiceProvider;
use Inertia\Inertia;
use Inertia\Response;

/** Documentação pública da API. Escopos e eventos saem dos catálogos para a página nunca divergir do código. */
class ApiDocsController extends Controller
{
    public function __invoke(): Response
    {
        $scopes = ScopeCatalog::forModules([Module::SIGNATURES, Module::INVOICES]);

        return Inertia::render('Docs/Api', [
            'baseUrl' => rtrim(url('/api/v1'), '/'),
            'scopes' => collect($scopes)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'events' => collect(EventCatalog::forModules([Module::SIGNATURES, Module::INVOICES]))
                ->collapse()
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'rateLimitPerMinute' => AppServiceProvider::API_REQUESTS_PER_MINUTE,
        ])->withViewData('seo', MarketingPages::apiDocs());
    }
}
