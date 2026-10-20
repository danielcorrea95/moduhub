<?php

namespace App\Modules\Mailing\Services\Report;

use App\Modules\Mailing\Contracts\EmailDeliveryRepositoryContract;
use App\Modules\Mailing\Contracts\EmailSuppressionRepositoryContract;
use App\Modules\Mailing\Enums\MailboxProvider;
use App\Modules\Mailing\Models\EmailDelivery;

/**
 * Números da visão geral de e-mails do workspace atual. Entrega e devolução são sobre o enviado;
 * abertura, clique e spam são sobre o entregue, como fazem os provedores de envio.
 */
class EmailReportService
{
    private const COUNTERS = ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'complained', 'pending'];

    public function __construct(
        private EmailDeliveryRepositoryContract $deliveries,
        private EmailSuppressionRepositoryContract $suppressions,
    ) {}

    /** @return array<string, mixed> */
    public function overview(ReportPeriod $period): array
    {
        $totals = $this->totals($period);
        $daily = $this->deliveries->totalsByDayBetween($period->from, $period->to, $period->utcOffset())
            ->keyBy(fn (EmailDelivery $row) => (string) $row->getAttribute('day'));

        return [
            'totals' => $totals,
            'providers' => $this->deliveries->totalsByProviderBetween($period->from, $period->to)
                ->map(function (EmailDelivery $row) {
                    $counters = $this->counters($row);
                    $provider = MailboxProvider::tryFrom((string) $row->provider) ?? MailboxProvider::Other;

                    return ['key' => $provider->value, 'label' => $provider->label()] + $counters + ['rates' => $this->rates($counters)];
                })
                ->values()
                ->all(),
            'daily' => array_map(
                fn (string $date) => ['date' => $date] + $this->counters($daily->get($date)),
                $period->dates(),
            ),
            'suppressed' => $this->suppressions->count(),
        ];
    }

    /** @return array<string, mixed> contadores do período e as taxas */
    public function totals(ReportPeriod $period): array
    {
        $totals = $this->counters($this->deliveries->totalsBetween($period->from, $period->to));

        return $totals + ['rates' => $this->rates($totals)];
    }

    /** @return array<string, int> */
    private function counters(?EmailDelivery $row): array
    {
        return array_combine(self::COUNTERS, array_map(
            fn (string $key) => (int) ($row?->getAttribute($key) ?? 0),
            self::COUNTERS,
        ));
    }

    /**
     * @param  array<string, int>  $c
     * @return array<string, float|null>
     */
    private function rates(array $c): array
    {
        $share = fn (int $part, int $whole) => $whole === 0 ? null : round($part / $whole * 100, 1);

        return [
            'delivered' => $share($c['delivered'], $c['sent']),
            'bounced' => $share($c['bounced'], $c['sent']),
            'opened' => $share($c['opened'], $c['delivered']),
            'clicked' => $share($c['clicked'], $c['delivered']),
            'complained' => $share($c['complained'], $c['delivered']),
        ];
    }
}
