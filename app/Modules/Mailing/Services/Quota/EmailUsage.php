<?php

namespace App\Modules\Mailing\Services\Quota;

use Carbon\CarbonImmutable;

/**
 * Retrato da cota de disparo de um workspace. Limite nulo = sem limite naquele eixo.
 */
final readonly class EmailUsage
{
    public function __construct(
        public ?string $plan,
        public int $dailyUsed,
        public ?int $dailyLimit,
        public int $monthlyUsed,
        public ?int $monthlyLimit,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public CarbonImmutable $dayResetsAt,
    ) {}

    public function isFree(): bool
    {
        return $this->plan === null;
    }

    public function dailyRemaining(): ?int
    {
        return $this->dailyLimit === null ? null : max(0, $this->dailyLimit - $this->dailyUsed);
    }

    public function monthlyRemaining(): ?int
    {
        return $this->monthlyLimit === null ? null : max(0, $this->monthlyLimit - $this->monthlyUsed);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'free' => $this->isFree(),
            'daily' => [
                'used' => $this->dailyUsed,
                'limit' => $this->dailyLimit,
                'remaining' => $this->dailyRemaining(),
                'resets_at' => $this->dayResetsAt->toIso8601String(),
            ],
            'monthly' => [
                'used' => $this->monthlyUsed,
                'limit' => $this->monthlyLimit,
                'remaining' => $this->monthlyRemaining(),
                'period_start' => $this->periodStart->toIso8601String(),
                'resets_at' => $this->periodEnd->toIso8601String(),
            ],
        ];
    }
}
