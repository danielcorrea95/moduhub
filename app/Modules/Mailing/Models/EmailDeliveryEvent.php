<?php

namespace App\Modules\Mailing\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Um evento do SES para um destinatário: o histórico que aparece no detalhe da mensagem.
 */
class EmailDeliveryEvent extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_SENT = 'sent';

    public const TYPE_DELAYED = 'delayed';

    public const TYPE_DELIVERED = 'delivered';

    public const TYPE_BOUNCED = 'bounced';

    public const TYPE_REJECTED = 'rejected';

    public const TYPE_COMPLAINED = 'complained';

    public const TYPE_OPENED = 'opened';

    public const TYPE_CLICKED = 'clicked';

    protected $fillable = [
        'tenant_id',
        'email_delivery_id',
        'type',
        'occurred_at',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
