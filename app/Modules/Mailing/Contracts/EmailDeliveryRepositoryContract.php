<?php

namespace App\Modules\Mailing\Contracts;

use App\Modules\Mailing\Models\EmailDelivery;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EmailDeliveryRepositoryContract
{
    /** @param  array<string, mixed>  $attributes */
    public function record(array $attributes): EmailDelivery;

    /** Busca fora do tenant: o evento do SES chega sem sessão de workspace. */
    public function findByMessageAndRecipient(string $messageId, string $recipient): ?EmailDelivery;

    /**
     * Todos os destinatários de uma mensagem, fora do tenant. Abertura e clique vêm por mensagem,
     * sem dizer qual destinatário abriu.
     *
     * @return Collection<int, EmailDelivery>
     */
    public function forMessage(string $messageId): Collection;

    public function save(EmailDelivery $delivery): EmailDelivery;

    /** Grava o evento no histórico. Devolve false quando o SNS reentregou um evento já gravado. */
    public function recordEvent(EmailDelivery $delivery, string $type, DateTimeInterface $occurredAt, ?string $detail = null): bool;

    /**
     * @param  array{status?: ?string, provider?: ?string, search?: ?string, from: DateTimeInterface, to: DateTimeInterface}  $filters
     */
    public function paginateForListing(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Mensagem do workspace atual, com o histórico. */
    public function findWithEvents(string $id): ?EmailDelivery;

    /** Uma linha com os totais do período (sent, delivered, opened, clicked, bounced, complained, pending). */
    public function totalsBetween(DateTimeInterface $from, DateTimeInterface $to): EmailDelivery;

    /** @return Collection<int, EmailDelivery> uma linha por provedor, com os mesmos totais */
    public function totalsByProviderBetween(DateTimeInterface $from, DateTimeInterface $to): Collection;

    /** @return Collection<int, EmailDelivery> uma linha por dia (coluna day, no fuso informado) */
    public function totalsByDayBetween(DateTimeInterface $from, DateTimeInterface $to, string $utcOffset): Collection;
}
