<?php

namespace App\Modules\Invoices\Providers;

use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Policies\InvoicePolicy;
use App\Modules\Invoices\Policies\IssuerPolicy;
use App\Modules\Invoices\Repositories\InvoiceRepository;
use App\Modules\Invoices\Repositories\IssuerRepository;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Invoices\Services\IssuerService;
use App\Modules\Invoices\Services\Sefin\FakeSefinClient;
use App\Modules\Invoices\Services\Sefin\SefinHttpClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InvoicesServiceProvider extends ServiceProvider
{
    public array $bindings = [
        IssuerRepositoryContract::class => IssuerRepository::class,
        IssuerServiceContract::class => IssuerService::class,
        InvoiceRepositoryContract::class => InvoiceRepository::class,
        InvoiceServiceContract::class => InvoiceService::class,
    ];

    public function register(): void
    {
        // Singleton para o fake guardar as notas "emitidas" entre chamadas do mesmo processo (testes).
        $this->app->singleton(SefinClientContract::class, fn ($app) => config('invoices.sefin_driver') === 'fake'
            ? $app->make(FakeSefinClient::class)
            : $app->make(SefinHttpClient::class));
    }

    public function boot(): void
    {
        Gate::policy(Issuer::class, IssuerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
