<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento recebido do Asaas. Sem tenant: o webhook chega fora de qualquer workspace e o tenant é
 * descoberto pelo cliente da cobrança. Guardado pelo id do evento porque a entrega é "pelo menos uma vez".
 */
class BillingWebhookEvent extends Model
{
    protected $fillable = [
        'gateway_event_id',
        'event',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
