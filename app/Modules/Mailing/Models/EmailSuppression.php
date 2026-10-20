<?php

namespace App\Modules\Mailing\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Endereço que o workspace não deve mais receber envios: devolveu de vez ou marcou como spam.
 * Insistir derruba a reputação do domínio do cliente e da conta SES inteira.
 */
class EmailSuppression extends Model
{
    use BelongsToTenant, HasUlids;

    public const REASON_BOUNCE = 'bounce';

    public const REASON_COMPLAINT = 'complaint';

    protected $fillable = [
        'tenant_id',
        'email',
        'reason',
        'detail',
        'email_delivery_id',
    ];

    /** Quem marcou como spam só sai da lista se pedir; o workspace não pode tirar sozinho. */
    public function isRemovable(): bool
    {
        return $this->reason === self::REASON_BOUNCE;
    }
}
