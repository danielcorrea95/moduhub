<?php

namespace App\Http\Controllers;

use App\Http\Seo\MarketingPages;
use App\Platform\Services\PlanCatalogService;
use Illuminate\Http\Response;

/** sitemap.xml, robots.txt e llms.txt, gerados a cada acesso a partir das páginas e do catálogo. */
class SeoFilesController extends Controller
{
    public function __construct(protected PlanCatalogService $catalog) {}

    public function sitemap(): Response
    {
        $paths = ['/', ...array_column(MarketingPages::PRODUCTS, 'path'), '/docs/api'];

        $urls = collect($paths)->map(fn (string $path) => '  <url><loc>'.e(url($path)).'</loc></url>')->implode("\n");

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>\n")
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response(MarketingPages::robotsTxt())->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(): Response
    {
        return response(MarketingPages::llmsTxt($this->catalog->products()))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
