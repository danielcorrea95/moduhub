<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantSubscription extends Model
{
    use HasUlids, BelongsToTenant;

    public const STATUS_TRIAL = 'trial';
    /** Assinou depois do teste grátis e a primeira mensalidade ainda não foi paga. */
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    /** Bloqueado: teste acabou sem assinatura ou a carência do atraso passou. */
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    public const BILLING_PIX = 'PIX';
    public const BILLING_CREDIT_CARD = 'CREDIT_CARD';

    protected $fillable = [
        'tenant_id',
        'status',
        'billing_type',
        'gateway_subscription_id',
        'card_brand',
        'card_last4',
        'card_token',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'past_due_since',
        'suspended_at',
    ];

    protected $hidden = [
        'card_token',
    ];

    protected $casts = [
        'card_token' => 'encrypted',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'past_due_since' => 'date',
        'suspended_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TenantSubscriptionItem::class);
    }

    /** Os planos do carrinho, um por produto. Carregue com `plans.modules` para as cotas. */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'tenant_subscription_items')
            ->withPivot('price_cents')
            ->withTimestamps()
            ->orderBy('plans.sort_order')
            ->orderBy('plans.id');
    }

    /** O plano contratado para o produto (módulo), ou nulo se o produto não está no carrinho. */
    public function planFor(string $moduleKey): ?Plan
    {
        return $this->plans->first(fn (Plan $plan) => $plan->modules->contains('key', $moduleKey));
    }

    /**
     * Cota e excedente do produto no plano contratado: o pivot plan_module, com included_quota,
     * daily_quota e overage_cents. Nulo sem o produto.
     */
    public function termsFor(string $moduleKey): ?Pivot
    {
        return $this->planFor($moduleKey)?->modules->firstWhere('key', $moduleKey)?->pivot;
    }

    /** Mensalidade: soma dos preços de cada item quando foi contratado. */
    public function priceCents(): int
    {
        return (int) $this->plans->sum('pivot.price_cents');
    }

    public function planNames(): string
    {
        return $this->plans->pluck('name')->join(', ', ' e ');
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function hasGatewaySubscription(): bool
    {
        return $this->gateway_subscription_id !== null;
    }

    /**
     * Workspace só lê: não cria nem altera nada nos módulos até pagar. Cancelada continua liberada até
     * o fim do ciclo já pago.
     */
    public function isBlocked(): bool
    {
        return match ($this->status) {
            self::STATUS_SUSPENDED, self::STATUS_INCOMPLETE => true,
            self::STATUS_CANCELLED => $this->current_period_end === null || $this->current_period_end->isPast(),
            default => false,
        };
    }
}
