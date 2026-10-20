<?php

namespace App\Modules\Mailing\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Models\SendingDomain;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use App\Modules\Mailing\Services\Report\EmailReportService;
use App\Modules\Mailing\Services\Report\ReportPeriod;
use App\Platform\Contracts\DashboardSectionContract;
use App\Platform\Dashboard\DashboardAlert;
use App\Platform\Dashboard\DashboardSection;

/**
 * E-mails na tela inicial: entrega dos últimos 30 dias, o domínio e a cota. Só para dono e
 * administrador, como o resto do e-mail; some enquanto o workspace não tem domínio nem envios.
 */
class MailingDashboardSection implements DashboardSectionContract
{
    /** Acima disso a AWS revisa a conta SES inteira. */
    private const BOUNCE_LIMIT = 5.0;

    private const COMPLAINT_LIMIT = 0.1;

    public function __construct(
        private SendingDomainRepositoryContract $domains,
        private EmailReportService $reports,
        private EmailQuotaService $quota,
    ) {}

    public function section(Tenant $tenant, User $user): ?DashboardSection
    {
        if (! $user->can('update', $tenant)) {
            return null;
        }

        $domain = $this->domains->forTenant($tenant);
        $period = ReportPeriod::lastDays(30);
        $totals = $this->reports->totals($period);

        if ($domain === null && $totals['sent'] === 0 && ! $tenant->hasModule(Module::MAILING)) {
            return null;
        }

        $usage = $this->quota->usage($tenant);

        return new DashboardSection(
            key: Module::MAILING,
            data: [
                'domain' => $domain ? ['domain' => $domain->domain, 'status' => $domain->status] : null,
                'days' => $period->days,
                'totals' => $totals,
                'tracking' => (bool) config('mailing.track_engagement'),
                'usage' => $usage->toArray(),
            ],
            alerts: $this->alerts($domain, $totals, $usage->dailyRemaining() === 0, $usage->monthlyRemaining() === 0),
        );
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return list<DashboardAlert>
     */
    private function alerts(?SendingDomain $domain, array $totals, bool $dayFull, bool $monthFull): array
    {
        $alerts = [];
        $domainPage = route('sending-domain.show');

        if ($domain?->status === SendingDomain::STATUS_FAILED) {
            $alerts[] = new DashboardAlert(
                DashboardAlert::DANGER,
                "O domínio {$domain->domain} parou de funcionar",
                'Os registros sumiram do DNS. Os e-mails voltaram a sair pelo endereço da Moduhub.',
                route('sending-domain.show', ['aba' => 'dns']),
                'Ver registros',
            );
        } elseif ($domain?->status === SendingDomain::STATUS_PENDING && $domain->verified_at === null) {
            $alerts[] = new DashboardAlert(DashboardAlert::WARNING, "Falta publicar os registros de {$domain->domain}", 'Até lá, os e-mails saem pelo endereço da Moduhub.', $domainPage, 'Ver registros');
        }

        if ($totals['sent'] >= 20 && ($totals['rates']['bounced'] ?? 0) >= self::BOUNCE_LIMIT) {
            $rate = number_format($totals['rates']['bounced'], 1, ',', '');
            $alerts[] = new DashboardAlert(DashboardAlert::DANGER, "{$rate}% dos e-mails devolveram nos últimos 30 dias", 'Acima de 5% a entrega de todos os seus e-mails fica em risco. Revise a lista de destinatários.', route('emails.messages', ['status' => 'failed']), 'Ver devolvidos');
        }

        if ($totals['delivered'] >= 20 && ($totals['rates']['complained'] ?? 0) >= self::COMPLAINT_LIMIT) {
            $alerts[] = new DashboardAlert(DashboardAlert::DANGER, 'Destinatários estão marcando seus e-mails como spam', 'Envie só para quem pediu para receber e deixe claro como sair da lista.', route('emails.messages', ['status' => 'complained']), 'Ver mensagens');
        }

        if ($monthFull) {
            $alerts[] = new DashboardAlert(DashboardAlert::DANGER, 'A cota de e-mails do mês acabou', 'O envio pelo SMTP e pela API está parado até a renovação.', $domainPage);
        } elseif ($dayFull) {
            $alerts[] = new DashboardAlert(DashboardAlert::WARNING, 'A cota de e-mails de hoje acabou', 'O envio pelo SMTP e pela API volta à meia-noite.', $domainPage);
        }

        return $alerts;
    }
}
