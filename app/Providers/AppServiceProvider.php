<?php

namespace App\Providers;

use App\Http\Ssr\PublicPagesSsrGateway;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Platform\Contracts\ApiKeyRepositoryContract;
use App\Platform\Contracts\ApiKeyServiceContract;
use App\Platform\Contracts\InvitationRepositoryContract;
use App\Platform\Contracts\InvitationServiceContract;
use App\Platform\Contracts\MemberRepositoryContract;
use App\Platform\Contracts\MemberServiceContract;
use App\Platform\Contracts\PlanRepositoryContract;
use App\Platform\Contracts\RegistrationServiceContract;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\Contracts\TenantServiceContract;
use App\Platform\Contracts\WebhookDispatcherContract;
use App\Platform\Contracts\WebhookRepositoryContract;
use App\Platform\Contracts\WebhookServiceContract;
use App\Platform\Repositories\ApiKeyRepository;
use App\Platform\Repositories\InvitationRepository;
use App\Platform\Repositories\MemberRepository;
use App\Platform\Repositories\PlanRepository;
use App\Platform\Repositories\TenantRepository;
use App\Platform\Repositories\WebhookRepository;
use App\Platform\Services\ApiKeyService;
use App\Platform\Services\InvitationService;
use App\Platform\Services\MemberService;
use App\Platform\Services\DashboardService;
use App\Platform\Services\PlanUsageService;
use App\Platform\Services\RegistrationService;
use App\Platform\Services\TenantService;
use App\Platform\Services\WebhookDispatcher;
use App\Platform\Services\WebhookService;
use App\Policies\ApiKeyPolicy;
use App\Policies\WebhookEndpointPolicy;
use App\Tenancy\CurrentTenant;
use App\Tenancy\QueueTenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const API_REQUESTS_PER_MINUTE = 120;

    public array $bindings = [
        TenantRepositoryContract::class => TenantRepository::class,
        TenantServiceContract::class => TenantService::class,
        MemberRepositoryContract::class => MemberRepository::class,
        MemberServiceContract::class => MemberService::class,
        InvitationRepositoryContract::class => InvitationRepository::class,
        InvitationServiceContract::class => InvitationService::class,
        ApiKeyRepositoryContract::class => ApiKeyRepository::class,
        ApiKeyServiceContract::class => ApiKeyService::class,
        WebhookRepositoryContract::class => WebhookRepository::class,
        WebhookServiceContract::class => WebhookService::class,
        WebhookDispatcherContract::class => WebhookDispatcher::class,
        RegistrationServiceContract::class => RegistrationService::class,
        PlanRepositoryContract::class => PlanRepository::class,
    ];

    public function register(): void
    {
        // Scoped, e não singleton: o Laravel zera a instância entre jobs da fila e entre requests no Octane,
        // então o tenant de um job nunca sobra para o próximo no mesmo worker.
        $this->app->scoped(CurrentTenant::class);

        // Cada módulo registra o seu medidor com a tag `usage-meters` no próprio provider.
        $this->app->when(PlanUsageService::class)->needs('$meters')->giveTagged('usage-meters');
        // Idem para a tela inicial: tag `dashboard-sections`, na ordem em que os providers sobem.
        $this->app->when(DashboardService::class)->needs('$sections')->giveTagged('dashboard-sections');

        $this->app->bind(\Inertia\Ssr\Gateway::class, PublicPagesSsrGateway::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        QueueTenancy::register();

        Gate::policy(ApiKey::class, ApiKeyPolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        // Equipe da Moduhub (planos e preços). Não tem relação com o papel no workspace.
        Gate::define('access-admin-panel', fn (User $user) => $user->isPlatformAdmin());

        // Limite por chave (não por IP): integrações costumam sair de poucos IPs compartilhados.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(self::API_REQUESTS_PER_MINUTE)->by(
            $request->attributes->get('api_key')?->getKey() ?? $request->ip(),
        ));
    }
}
