<?php

namespace App\Modules\Billing\Repositories;

use App\Modules\Billing\Contracts\BillingPaymentRepositoryContract;
use App\Modules\Billing\Models\BillingPayment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BillingPaymentRepository implements BillingPaymentRepositoryContract
{
    public function latest(int $limit): Collection
    {
        return BillingPayment::query()
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function findForGateway(?string $id, string $gatewayPaymentId): ?BillingPayment
    {
        if ($id !== null && Str::isUlid($id)) {
            $payment = BillingPayment::query()->find($id);
            if ($payment !== null) {
                return $payment;
            }
        }

        return BillingPayment::query()->where('gateway_payment_id', $gatewayPaymentId)->first();
    }

    public function oldestOverdueDueDate(): ?CarbonInterface
    {
        $date = BillingPayment::query()->where('status', BillingPayment::STATUS_OVERDUE)->min('due_date');

        return $date !== null ? CarbonImmutable::parse($date) : null;
    }

    public function hasPaidSubscriptionPayment(string $subscriptionId): bool
    {
        return BillingPayment::query()
            ->where('tenant_subscription_id', $subscriptionId)
            ->where('kind', BillingPayment::KIND_SUBSCRIPTION)
            ->where('status', BillingPayment::STATUS_PAID)
            ->exists();
    }

    public function overageFor(CarbonInterface $periodStart): ?BillingPayment
    {
        return BillingPayment::query()
            ->where('kind', BillingPayment::KIND_OVERAGE)
            ->where('period_start', $periodStart)
            ->first();
    }

    public function overagesNotSent(): Collection
    {
        return BillingPayment::query()
            ->where('kind', BillingPayment::KIND_OVERAGE)
            ->whereNull('gateway_payment_id')
            ->get();
    }
}
