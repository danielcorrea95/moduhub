<?php

namespace App\Modules\Invoices\Providers;

use App\Modules\Invoices\Console\SyncDistributedDocuments;
use App\Modules\Invoices\Console\SyncMunicipalAgreements;
use App\Modules\Invoices\Contracts\CertificateEventRepositoryContract;
use App\Modules\Invoices\Contracts\DfeDistributionContract;
use App\Modules\Invoices\Contracts\DistributedDocumentRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\InvoiceServiceContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerServiceContract;
use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Contracts\MunicipalAgreementsContract;
use App\Modules\Invoices\Contracts\MunicipalParametersContract;
use App\Modules\Invoices\Contracts\ReceivedInvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\SefinClientContract;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Models\ReceivedInvoice;
use App\Modules\Invoices\Policies\InvoicePolicy;
use App\Modules\Invoices\Policies\IssuerPolicy;
use App\Modules\Invoices\Policies\ReceivedInvoicePolicy;
use App\Modules\Invoices\Repositories\CertificateEventRepository;
use App\Modules\Invoices\Repositories\DistributedDocumentRepository;
use App\Modules\Invoices\Repositories\InvoiceRepository;
use App\Modules\Invoices\Repositories\IssuerRepository;
use App\Modules\Invoices\Repositories\MunicipalAgreementRepository;
use App\Modules\Invoices\Repositories\ReceivedInvoiceRepository;
use App\Modules\Invoices\Services\Adn\AdnAgreementsClient;
use App\Modules\Invoices\Services\Adn\AdnDistributionClient;
use App\Modules\Invoices\Services\Adn\AdnParametersClient;
use App\Modules\Invoices\Services\Adn\FakeDfeDistribution;
use App\Modules\Invoices\Services\Adn\FakeMunicipalAgreements;
use App\Modules\Invoices\Services\Adn\FakeMunicipalParameters;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Invoices\Services\IssuerService;
use App\Modules\Invoices\Services\Sefin\FakeSefinClient;
use App\Modules\Invoices\Services\Sefin\SefinHttpClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InvoicesServiceProvider extends ServiceProvider
{
    public array $bindings = [
        IssuerRepositoryContract::class => IssuerRepository::class,
        IssuerServiceContract::class => IssuerService::class,
        InvoiceRepositoryContract::class => InvoiceRepository::class,
        InvoiceServiceContract::class => InvoiceService::class,
        MunicipalAgreementRepositoryContract::class => MunicipalAgreementRepository::class,
        ReceivedInvoiceRepositoryContract::class => ReceivedInvoiceRepository::class,
        DistributedDocumentRepositoryContract::class => DistributedDocumentRepository::class,
        CertificateEventRepositoryContract::class => CertificateEventRepository::class,
    ];

    public function register(): void
    {
        // Singleton para o fake guardar as notas "emitidas" entre chamadas do mesmo processo (testes).
        $this->app->singleton(SefinClientContract::class, fn ($app) => config('invoices.sefin_driver') === 'fake'
            ? $app->make(FakeSefinClient::class)
            : $app->make(SefinHttpClient::class));

        // O ADN acompanha o driver da Sefin: no fake, nenhuma chamada sai para o governo.
        $this->app->singleton(MunicipalParametersContract::class, fn ($app) => config('invoices.sefin_driver') === 'fake'
            ? $app->make(FakeMunicipalParameters::class)
            : $app->make(AdnParametersClient::class));

        $this->app->singleton(DfeDistributionContract::class, fn ($app) => config('invoices.sefin_driver') === 'fake'
            ? $app->make(FakeDfeDistribution::class)
            : $app->make(AdnDistributionClient::class));

        $this->app->singleton(MunicipalAgreementsContract::class, fn ($app) => config('invoices.sefin_driver') === 'fake'
            ? $app->make(FakeMunicipalAgreements::class)
            : $app->make(AdnAgreementsClient::class));
    }

    public function boot(): void
    {
        Gate::policy(Issuer::class, IssuerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(ReceivedInvoice::class, ReceivedInvoicePolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncMunicipalAgreements::class, SyncDistributedDocuments::class]);
        }

        // Convênios mudam devagar (adesões são publicadas algumas vezes por mês): uma varredura por semana basta.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('invoices:sync-agreements')->weeklyOn(0, '03:00')->withoutOverlapping()->runInBackground();
            // Notas recebidas e eventos de terceiros (rejeição do tomador, cancelamento por ofício) chegam pelo ADN.
            $schedule->command('invoices:sync-documents')->hourly()->withoutOverlapping()->runInBackground();
        });
    }
}
