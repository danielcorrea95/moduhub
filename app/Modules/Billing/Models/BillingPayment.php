<?php

namespace App\Modules\Billing\Models;

use App\Models\TenantSubscription;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma cobrança no Asaas: mensalidade gerada pela assinatura ou excedente do ciclo, criado por nós. */
class BillingPayment extends Model
{
    use BelongsToTenant, HasUlids;

    public const KIND_SUBSCRIPTION = 'subscription';
    public const KIND_OVERAGE = 'overage';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CHARGEBACK = 'chargeback';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'kind',
        'gateway_payment_id',
        'billing_type',
        'status',
        'value_cents',
        'due_date',
        'paid_at',
        'invoice_url',
        'description',
        'period_start',
        'period_end',
        'lines',
        'failure_reason',
    ];

    protected $casts = [
        'value_cents' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'lines' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE], true);
    }
}
