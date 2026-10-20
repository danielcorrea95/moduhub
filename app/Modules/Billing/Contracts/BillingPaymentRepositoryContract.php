<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Models\BillingPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Cobranças do workspace atual. */
interface BillingPaymentRepositoryContract
{
    /** @return Collection<int, BillingPayment> mais recentes primeiro */
    public function latest(int $limit): Collection;

    /** Pelo nosso id (externalReference das cobranças que criamos) ou pelo id no Asaas. */
    public function findForGateway(?string $id, string $gatewayPaymentId): ?BillingPayment;

    /** Vencimento da cobrança em atraso mais antiga, ou nulo sem atraso. */
    public function oldestOverdueDueDate(): ?CarbonInterface;

    public function hasPaidSubscriptionPayment(string $subscriptionId): bool;

    public function overageFor(CarbonInterface $periodStart): ?BillingPayment;

    /** @return Collection<int, BillingPayment> excedentes registrados aqui que não chegaram ao Asaas */
    public function overagesNotSent(): Collection;
}
