<?php

namespace App\Modules\Invoices\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Invoices\Contracts\InvoiceRepositoryContract;
use App\Modules\Invoices\Contracts\IssuerRepositoryContract;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use App\Platform\Contracts\DashboardSectionContract;
use App\Platform\Dashboard\DashboardAlert;
use App\Platform\Dashboard\DashboardSection;
use Carbon\CarbonImmutable;

/**
 * Notas na tela inicial: o valor emitido mês a mês (mês do calendário, em Brasília), o que está
 * pendente na Sefin e o que impede de emitir.
 */
class InvoicesDashboardSection implements DashboardSectionContract
{
    private const MONTHS = 6;

    private const CERTIFICATE_WARNING_DAYS = 30;

    private const TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        private InvoiceRepositoryContract $invoices,
        private IssuerRepositoryContract $issuers,
        private InvoiceQuotaService $quota,
    ) {}

    public function section(Tenant $tenant, User $user): ?DashboardSection
    {
        if (! $tenant->hasModule(Module::INVOICES)) {
            return null;
        }

        $usage = $this->quota->usage($tenant);
        $issuer = $this->issuers->forCurrentTenant();
        $counts = $this->invoices->countByStatus();
        $months = $this->months();
        $overage = $usage->included !== null && $usage->overageAllowed ? max(0, $usage->used - $usage->included) : 0;

        return new DashboardSection(
            key: Module::INVOICES,
            data: [
                'months' => $months,
                'pace' => $this->pace(),
                'pending' => (int) ($counts[Invoice::STATUS_PENDING] ?? 0),
                'issuer_ready' => (bool) $issuer?->isReadyToIssue(),
                'usage' => $usage->toArray(),
            ],
            charges: $overage > 0 && $usage->overageCents ? [[
                'label' => $overage === 1 ? '1 nota excedente' : "{$overage} notas excedentes",
                'cents' => $overage * $usage->overageCents,
            ]] : [],
            alerts: $this->alerts($issuer, $usage),
        );
    }

    /** @return list<array{month: string, count: int, total_cents: int}> do mais antigo ao atual */
    private function months(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $first = $now->startOfMonth()->subMonths(self::MONTHS - 1);

        $rows = $this->invoices
            ->issuedTotalsByMonth($first->utc(), $now->endOfMonth()->utc(), $now->format('P'))
            ->keyBy('month');

        return array_map(function (int $i) use ($first, $rows) {
            $month = $first->addMonths($i)->format('Y-m');
            $row = $rows->get($month);

            return [
                'month' => $month,
                'count' => (int) ($row?->getAttribute('total_count') ?? 0),
                // SUM de decimal volta como string; centavos evitam float no front.
                'total_cents' => (int) round(((float) ($row?->getAttribute('total_amount') ?? 0)) * 100),
            ];
        }, range(0, self::MONTHS - 1));
    }

    /**
     * Valor acumulado dia a dia no mês atual (até hoje) e no anterior (inteiro). Comparar no mesmo
     * dia é o justo: no dia 20, outubro inteiro contra setembro inteiro sempre parece pior.
     *
     * @return array{month: string, previous_month: string, today: int, days_in_month: int, current: list<int>, previous: list<int>}
     */
    private function pace(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $start = $now->startOfMonth();
        $previousStart = $start->subMonthNoOverflow();

        $daily = $this->invoices
            ->issuedTotalsByDay($previousStart->utc(), $now->endOfDay()->utc(), $now->format('P'))
            ->mapWithKeys(fn ($row) => [(string) $row->getAttribute('day') => (int) round(((float) $row->getAttribute('total_amount')) * 100)]);

        $cumulative = function (CarbonImmutable $first, int $days) use ($daily): array {
            $sum = 0;

            return array_map(function (int $i) use ($first, $daily, &$sum) {
                $sum += $daily->get($first->addDays($i)->toDateString(), 0);

                return $sum;
            }, range(0, $days - 1));
        };

        return [
            'month' => $start->format('Y-m'),
            'previous_month' => $previousStart->format('Y-m'),
            'today' => $now->day,
            'days_in_month' => $now->daysInMonth,
            'current' => $cumulative($start, $now->day),
            'previous' => $cumulative($previousStart, $previousStart->daysInMonth),
        ];
    }

    /** @return list<DashboardAlert> */
    private function alerts(?Issuer $issuer, InvoiceUsage $usage): array
    {
        $edit = route('invoices.issuer.edit');

        if ($issuer === null || ! $issuer->hasCertificate()) {
            return [new DashboardAlert(DashboardAlert::WARNING, 'Configure o emitente para emitir notas', 'Faltam os dados da empresa ou o certificado digital (e-CNPJ A1).', $edit, 'Configurar emitente')];
        }

        $alerts = [];

        if ($issuer->certificateIsExpired()) {
            $alerts[] = new DashboardAlert(DashboardAlert::DANGER, 'O certificado digital venceu', 'Nenhuma nota pode ser emitida até enviar um certificado novo.', $edit, 'Enviar certificado');
        } elseif ($issuer->certificateExpiresWithin(self::CERTIFICATE_WARNING_DAYS)) {
            $alerts[] = new DashboardAlert(
                DashboardAlert::WARNING,
                'O certificado digital vence em '.$issuer->certificate_valid_until->format('d/m/Y'),
                'Renove com a sua certificadora e envie o novo antes do vencimento.',
                $edit,
                'Enviar certificado',
            );
        }

        if ($usage->exhausted() && ! $usage->overageAllowed) {
            $alerts[] = new DashboardAlert(
                DashboardAlert::DANGER,
                "As {$usage->included} notas do plano acabaram",
                'A emissão volta em '.$usage->periodEnd->format('d/m/Y').'. Mude de plano para emitir antes.',
                route('invoices.index'),
            );
        }

        return $alerts;
    }
}
