<?php

namespace App\Modules\Invoices\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha da trilha de auditoria do certificado do emitente.
 */
class CertificateEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'invoice_certificate_events';

    public $timestamps = false;

    public const TYPE_UPLOADED = 'uploaded';

    public const TYPE_REPLACED = 'replaced';

    public const TYPE_REMOVED = 'removed';

    public const TYPE_USED = 'used';

    public const TYPE_EXPIRY_NOTICE = 'expiry_notice';

    public const TYPE_LABELS = [
        self::TYPE_UPLOADED => 'Certificado enviado',
        self::TYPE_REPLACED => 'Certificado substituído',
        self::TYPE_REMOVED => 'Certificado removido',
        self::TYPE_USED => 'Chave privada usada',
        self::TYPE_EXPIRY_NOTICE => 'Aviso de vencimento enviado',
    ];

    /** Para que a chave privada foi aberta. */
    public const PURPOSE_DPS_SIGNATURE = 'dps_signature';

    public const PURPOSE_CANCELLATION_SIGNATURE = 'cancellation_signature';

    public const PURPOSE_EVENT_SIGNATURE = 'event_signature';

    public const PURPOSE_SEFIN_AUTHENTICATION = 'sefin_authentication';

    public const PURPOSE_ADN_AUTHENTICATION = 'adn_authentication';

    public const PURPOSE_LABELS = [
        self::PURPOSE_DPS_SIGNATURE => 'Assinatura da DPS (emissão)',
        self::PURPOSE_CANCELLATION_SIGNATURE => 'Assinatura do pedido de cancelamento',
        self::PURPOSE_EVENT_SIGNATURE => 'Assinatura de evento (manifestação ou análise fiscal)',
        self::PURPOSE_SEFIN_AUTHENTICATION => 'Autenticação na Sefin Nacional',
        self::PURPOSE_ADN_AUTHENTICATION => 'Autenticação no ADN',
    ];

    protected $fillable = [
        'tenant_id',
        'issuer_id',
        'type',
        'purpose',
        'certificate_fingerprint',
        'certificate_serial',
        'actor_type',
        'actor_id',
        'actor_label',
        'ip_address',
        'user_agent',
        'data',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class);
    }
}
