<?php

namespace App\Modules\Signatures\Providers;

use App\Modules\Signatures\Console\CheckPlatformCertificate;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\DocumentSubmissionServiceContract;
use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Modules\Signatures\Contracts\SignatureServiceContract;
use App\Modules\Signatures\Contracts\SignerRepositoryContract;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Policies\DocumentPolicy;
use App\Modules\Signatures\Repositories\DocumentRepository;
use App\Modules\Signatures\Repositories\SignatureRepository;
use App\Modules\Signatures\Repositories\SignerRepository;
use App\Modules\Signatures\Services\DocumentService;
use App\Modules\Signatures\Services\DocumentSubmissionService;
use App\Modules\Signatures\Services\SignatureService;
use App\Modules\Signatures\Services\SignaturesDashboardSection;
use App\Modules\Signatures\Services\SignaturesUsageMeter;
use App\Modules\Signatures\Services\SignerService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SignaturesServiceProvider extends ServiceProvider
{
    public array $bindings = [
        DocumentRepositoryContract::class => DocumentRepository::class,
        DocumentServiceContract::class => DocumentService::class,
        DocumentSubmissionServiceContract::class => DocumentSubmissionService::class,
        SignerRepositoryContract::class => SignerRepository::class,
        SignerServiceContract::class => SignerService::class,
        SignatureRepositoryContract::class => SignatureRepository::class,
        SignatureServiceContract::class => SignatureService::class,
    ];

    public function register(): void
    {
        $this->app->tag([SignaturesUsageMeter::class], 'usage-meters');
        $this->app->tag([SignaturesDashboardSection::class], 'dashboard-sections');
    }

    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CheckPlatformCertificate::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('signatures:check-platform-certificate')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
        });
    }
}
