<?php

namespace App\Modules\Mailing\Providers;

use App\Modules\Mailing\Console\CheckSendingDomains;
use App\Modules\Mailing\Contracts\EmailDeliveryRepositoryContract;
use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;
use App\Modules\Mailing\Contracts\EmailSuppressionRepositoryContract;
use App\Modules\Mailing\Contracts\EmailUsageRepositoryContract;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Contracts\SnsMessageVerifierContract;
use App\Modules\Mailing\Contracts\SuppressionListGatewayContract;
use App\Modules\Mailing\Listeners\RecordSentEmail;
use App\Modules\Mailing\Repositories\EmailDeliveryRepository;
use App\Modules\Mailing\Repositories\EmailSuppressionRepository;
use App\Modules\Mailing\Repositories\EmailUsageRepository;
use App\Modules\Mailing\Repositories\SendingDomainRepository;
use App\Modules\Mailing\Repositories\SmtpCredentialRepository;
use App\Modules\Mailing\Services\AwsSnsMessageVerifier;
use App\Modules\Mailing\Services\Identity\FakeEmailIdentityGateway;
use App\Modules\Mailing\Services\Identity\SesEmailIdentityGateway;
use App\Modules\Mailing\Services\MailingDashboardSection;
use App\Modules\Mailing\Services\Quota\MailingUsageMeter;
use App\Modules\Mailing\Services\Suppression\FakeSuppressionListGateway;
use App\Modules\Mailing\Services\Suppression\SesSuppressionListGateway;
use Aws\SesV2\SesV2Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MailingServiceProvider extends ServiceProvider
{
    public array $bindings = [
        EmailDeliveryRepositoryContract::class => EmailDeliveryRepository::class,
        SendingDomainRepositoryContract::class => SendingDomainRepository::class,
        SmtpCredentialRepositoryContract::class => SmtpCredentialRepository::class,
        EmailUsageRepositoryContract::class => EmailUsageRepository::class,
        EmailSuppressionRepositoryContract::class => EmailSuppressionRepository::class,
        SnsMessageVerifierContract::class => AwsSnsMessageVerifier::class,
    ];

    public function register(): void
    {
        $this->app->tag([MailingUsageMeter::class], 'usage-meters');
        $this->app->tag([MailingDashboardSection::class], 'dashboard-sections');

        // Singletons para os fakes guardarem estado entre chamadas do mesmo processo (testes).
        $this->app->singleton(EmailIdentityGatewayContract::class, fn () => $this->usesSes()
            ? new SesEmailIdentityGateway($this->sesClient(), config('mail.mailers.ses-v2.options.ConfigurationSetName'))
            : new FakeEmailIdentityGateway);

        $this->app->singleton(SuppressionListGatewayContract::class, fn () => $this->usesSes()
            ? new SesSuppressionListGateway($this->sesClient())
            : new FakeSuppressionListGateway);
    }

    private function usesSes(): bool
    {
        return config('services.ses.identity_driver') === 'ses';
    }

    private function sesClient(): SesV2Client
    {
        $config = config('services.ses');

        return new SesV2Client(array_filter([
            'version' => 'latest',
            'region' => $config['region'],
            // Sem chave no .env, o SDK usa a role da instância.
            'credentials' => $config['key'] && $config['secret']
                ? ['key' => $config['key'], 'secret' => $config['secret']]
                : null,
        ]));
    }

    public function boot(): void
    {
        Event::listen(MessageSent::class, RecordSentEmail::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CheckSendingDomains::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('mailing:check-domains')->hourly()->withoutOverlapping()->runInBackground();
        });
    }
}
