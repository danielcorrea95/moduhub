<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Console\RunBillingCycle;
use App\Modules\Billing\Contracts\BillingAccountRepositoryContract;
use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Contracts\PaymentGatewayContract;
use App\Modules\Billing\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Repositories\BillingAccountRepository;
use App\Modules\Billing\Repositories\BillingPaymentRepository;
use App\Modules\Billing\Repositories\SubscriptionRepository;
use App\Modules\Billing\Services\Gateway\AsaasPaymentGateway;
use App\Modules\Billing\Services\Gateway\FakePaymentGateway;
use App\Modules\Billing\Services\OverageBillingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Cobrança da Moduhub aos workspaces (Asaas). Não é um módulo vendido: não tem linha em `modules`
 * nem passa pelo middleware module.web.
 */
class BillingServiceProvider extends ServiceProvider
{
    public array $bindings = [
        BillingAccountRepositoryContract::class => BillingAccountRepository::class,
        BillingPaymentRepositoryContract::class => BillingPaymentRepository::class,
        SubscriptionRepositoryContract::class => SubscriptionRepository::class,
    ];

    public function register(): void
    {
        // Singleton para o fake guardar as chamadas entre resoluções do mesmo processo (testes).
        $this->app->singleton(PaymentGatewayContract::class, fn () => config('services.asaas.driver') === 'asaas'
            ? new AsaasPaymentGateway((string) config('services.asaas.api_key'), (string) config('services.asaas.base_url'))
            : new FakePaymentGateway);

        // Cada módulo registra o que cobra além do plano com a tag `billable-usage`.
        $this->app->when(OverageBillingService::class)->needs('$usage')->giveTagged('billable-usage');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunBillingCycle::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('billing:cycle')->hourly()->withoutOverlapping()->runInBackground();
        });
    }
}
