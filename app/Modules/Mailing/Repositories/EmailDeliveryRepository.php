<?php

namespace App\Modules\Mailing\Repositories;

use App\Modules\Mailing\Contracts\EmailDeliveryRepositoryContract;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailDeliveryEvent;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailDeliveryRepository implements EmailDeliveryRepositoryContract
{
    /** Filtro de status da listagem → condição. "Aberto" e "clicado" não são status, são marcas. */
    private const STATUS_FILTERS = ['delivered', 'opened', 'clicked', 'failed', 'complained', 'pending'];

    public function record(array $attributes): EmailDelivery
    {
        return EmailDelivery::query()->withoutTenantScope()->updateOrCreate(
            ['message_id' => $attributes['message_id'], 'recipient' => $attributes['recipient']],
            $attributes,
        );
    }

    public function findByMessageAndRecipient(string $messageId, string $recipient): ?EmailDelivery
    {
        return EmailDelivery::query()
            ->withoutTenantScope()
            ->where('message_id', $messageId)
            ->where('recipient', $recipient)
            ->first();
    }

    public function forMessage(string $messageId): Collection
    {
        return EmailDelivery::query()
            ->withoutTenantScope()
            ->where('message_id', $messageId)
            ->get();
    }

    public function save(EmailDelivery $delivery): EmailDelivery
    {
        $delivery->save();

        return $delivery;
    }

    public function recordEvent(EmailDelivery $delivery, string $type, DateTimeInterface $occurredAt, ?string $detail = null): bool
    {
        // insertOrIgnore não passa pelo Eloquent: id e tenant vão à mão.
        return EmailDeliveryEvent::query()->withoutTenantScope()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'tenant_id' => $delivery->tenant_id,
            'email_delivery_id' => $delivery->getKey(),
            'type' => $type,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.v'),
            'detail' => $detail === null ? null : Str::limit($detail, 495),
            'created_at' => now(),
        ]) > 0;
    }

    public function paginateForListing(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $status = in_array($filters['status'] ?? null, self::STATUS_FILTERS, true) ? $filters['status'] : null;
        $search = $filters['search'] ?? null;

        return EmailDelivery::query()
            ->whereBetween('sent_at', [$filters['from'], $filters['to']])
            ->when($status !== null, fn (Builder $q) => $this->whereStatus($q, $status))
            ->when($filters['provider'] ?? null, fn (Builder $q, string $provider) => $q->where('provider', $provider))
            ->when($search !== null, function (Builder $q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('recipient', 'like', $term)->orWhere('subject', 'like', $term));
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findWithEvents(string $id): ?EmailDelivery
    {
        return EmailDelivery::query()->with('events')->find($id);
    }

    public function totalsBetween(DateTimeInterface $from, DateTimeInterface $to): EmailDelivery
    {
        return $this->totals()->whereBetween('sent_at', [$from, $to])->first() ?? new EmailDelivery;
    }

    public function totalsByProviderBetween(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return $this->totals()
            ->addSelect('provider')
            ->whereBetween('sent_at', [$from, $to])
            ->groupBy('provider')
            ->orderByDesc('sent')
            ->get();
    }

    public function totalsByDayBetween(DateTimeInterface $from, DateTimeInterface $to, string $utcOffset): Collection
    {
        // Offset fixo em vez de nome de fuso: CONVERT_TZ com nome exige as tabelas de fuso do MySQL.
        return $this->totals()
            ->selectRaw("DATE(CONVERT_TZ(sent_at, '+00:00', ?)) as day", [$utcOffset])
            ->whereBetween('sent_at', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    private function totals(): Builder
    {
        return EmailDelivery::query()->selectRaw(
            'COUNT(*) as sent,
            SUM(delivered_at IS NOT NULL) as delivered,
            SUM(opened_at IS NOT NULL) as opened,
            SUM(clicked_at IS NOT NULL) as clicked,
            SUM(status IN (?, ?)) as bounced,
            SUM(complained_at IS NOT NULL) as complained,
            SUM(status IN (?, ?)) as pending',
            [EmailDelivery::STATUS_BOUNCED, EmailDelivery::STATUS_REJECTED, EmailDelivery::STATUS_SENT, EmailDelivery::STATUS_DELAYED],
        );
    }

    private function whereStatus(Builder $query, string $status): void
    {
        match ($status) {
            'delivered' => $query->whereNotNull('delivered_at'),
            'opened' => $query->whereNotNull('opened_at'),
            'clicked' => $query->whereNotNull('clicked_at'),
            'failed' => $query->whereIn('status', [EmailDelivery::STATUS_BOUNCED, EmailDelivery::STATUS_REJECTED]),
            'complained' => $query->whereNotNull('complained_at'),
            'pending' => $query->whereIn('status', [EmailDelivery::STATUS_SENT, EmailDelivery::STATUS_DELAYED]),
        };
    }
}
