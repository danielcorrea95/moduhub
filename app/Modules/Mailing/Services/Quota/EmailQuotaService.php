<?php

namespace App\Modules\Mailing\Services\Quota;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Modules\Mailing\Contracts\EmailUsageRepositoryContract;
use App\Modules\Mailing\Exceptions\EmailQuotaExceeded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cota do disparo do cliente (relay SMTP e API), contada por mensagem.
 *
 * - Plano com o módulo `mailing`: limite diário (`daily_quota`) e mensal (`included_quota`) do
 *   plano; nulo = sem limite. O mês é o ciclo da assinatura.
 * - Sem o módulo: cota gratuita de `config('mailing.quota.free')`, mês do calendário.
 * - Passou de qualquer um dos dois, bloqueia até renovar. Não há excedente.
 *
 * Dias e meses viram no horário de Brasília. Convites de assinatura, códigos e NFS-e não passam
 * por aqui: pertencem aos próprios módulos.
 */
class EmailQuotaService
{
    public function __construct(
        private EmailUsageRepositoryContract $usage,
    ) {}

    public function usage(Tenant $tenant): EmailUsage
    {
        $limits = $this->limits($tenant);
        $today = $this->now()->toDateString();

        return $this->snapshot(
            $limits,
            $this->usage->countOn($tenant, $today),
            $this->usage->sumBetween($tenant, ...$this->periodDates($limits)),
        );
    }

    /**
     * Conta as mensagens antes do envio. Se o envio falhar depois, devolva com `release()`.
     *
     * @throws EmailQuotaExceeded
     */
    public function reserve(Tenant $tenant, int $messages = 1): EmailUsage
    {
        return DB::transaction(function () use ($tenant, $messages) {
            $limits = $this->limits($tenant);
            $day = $this->usage->lockDay($tenant, $this->now()->toDateString());
            $monthly = $this->usage->sumBetween($tenant, ...$this->periodDates($limits));

            if ($limits['daily'] !== null && $day->count + $messages > $limits['daily']) {
                throw EmailQuotaExceeded::daily($limits['daily'], $this->dayResetsAt());
            }

            if ($limits['monthly'] !== null && $monthly + $messages > $limits['monthly']) {
                throw EmailQuotaExceeded::monthly($limits['monthly'], $limits['period_end']);
            }

            $this->usage->add($day, $messages);

            return $this->snapshot($limits, $day->count, $monthly + $messages);
        });
    }

    /** Devolve mensagens reservadas cujo envio falhou. */
    public function release(Tenant $tenant, int $messages = 1): void
    {
        DB::transaction(function () use ($tenant, $messages) {
            $this->usage->add($this->usage->lockDay($tenant, $this->now()->toDateString()), -$messages);
        });
    }

    /**
     * @return array{plan: ?string, daily: ?int, monthly: ?int, period_start: CarbonImmutable, period_end: CarbonImmutable}
     */
    private function limits(Tenant $tenant): array
    {
        $subscription = $this->usage->subscriptionFor($tenant);
        $module = $subscription?->plan?->modules->firstWhere('key', Module::MAILING);

        if ($module === null) {
            [$start, $end] = $this->calendarMonth();

            return [
                'plan' => null,
                'daily' => config('mailing.quota.free.daily'),
                'monthly' => config('mailing.quota.free.monthly'),
                'period_start' => $start,
                'period_end' => $end,
            ];
        }

        [$start, $end] = $this->subscriptionPeriod($subscription);

        return [
            'plan' => $subscription->plan->name,
            'daily' => $module->pivot->daily_quota,
            'monthly' => $module->pivot->included_quota,
            'period_start' => $start,
            'period_end' => $end,
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} início e fim (exclusivo) */
    private function subscriptionPeriod(TenantSubscription $subscription): array
    {
        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;

        if ($start === null || $end === null || $end->isPast()) {
            return $this->calendarMonth();
        }

        return [CarbonImmutable::parse($start)->setTimezone($this->timezone()), CarbonImmutable::parse($end)->setTimezone($this->timezone())];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function calendarMonth(): array
    {
        $now = $this->now();

        return [$now->startOfMonth(), $now->startOfMonth()->addMonth()];
    }

    /** @return array{0: string, 1: string} datas inclusivas, no fuso de Brasília */
    private function periodDates(array $limits): array
    {
        return [$limits['period_start']->toDateString(), $limits['period_end']->subSecond()->toDateString()];
    }

    private function snapshot(array $limits, int $dailyUsed, int $monthlyUsed): EmailUsage
    {
        return new EmailUsage(
            plan: $limits['plan'],
            dailyUsed: $dailyUsed,
            dailyLimit: $limits['daily'],
            monthlyUsed: $monthlyUsed,
            monthlyLimit: $limits['monthly'],
            periodStart: $limits['period_start'],
            periodEnd: $limits['period_end'],
            dayResetsAt: $this->dayResetsAt(),
        );
    }

    private function dayResetsAt(): CarbonImmutable
    {
        return $this->now()->startOfDay()->addDay();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    private function timezone(): string
    {
        return config('mailing.quota.timezone');
    }
}
