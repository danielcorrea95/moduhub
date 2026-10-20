<?php

namespace App\Modules\Mailing\Services\Report;

use Carbon\CarbonImmutable;

/**
 * Janela dos relatórios de e-mail: os últimos N dias, contando hoje, no fuso de Brasília.
 */
final readonly class ReportPeriod
{
    public const OPTIONS = [7, 30, 90];

    public const DEFAULT = 30;

    private function __construct(
        public int $days,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function lastDays(?int $days = null): self
    {
        $days = in_array($days, self::OPTIONS, true) ? $days : self::DEFAULT;
        $now = CarbonImmutable::now(self::timezone());

        return new self($days, $now->startOfDay()->subDays($days - 1)->utc(), $now->endOfDay()->utc());
    }

    /** Offset fixo (Brasília não tem horário de verão desde 2019), no formato do CONVERT_TZ. */
    public function utcOffset(): string
    {
        return $this->to->setTimezone(self::timezone())->format('P');
    }

    /** @return list<string> as datas do período, Y-m-d no fuso local */
    public function dates(): array
    {
        $first = $this->from->setTimezone(self::timezone());

        return array_map(fn (int $i) => $first->addDays($i)->toDateString(), range(0, $this->days - 1));
    }

    private static function timezone(): string
    {
        return config('mailing.quota.timezone');
    }
}
