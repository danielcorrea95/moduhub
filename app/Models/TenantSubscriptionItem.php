<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um produto no carrinho da assinatura: o plano escolhido e o preço dele quando foi contratado. */
class TenantSubscriptionItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'plan_id',
        'price_cents',
    ];

    protected $casts = [
        'price_cents' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
