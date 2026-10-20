<?php

namespace App\Modules\Mailing\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Situação de um e-mail para um destinatário, conforme os eventos que o SES publica.
 */
class EmailDelivery extends Model
{
    use BelongsToTenant, HasUlids;

    public const STATUS_SENT = 'sent';

    public const STATUS_DELAYED = 'delayed';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLAINED = 'complained';

    public const STATUS_LABELS = [
        self::STATUS_SENT => 'Enviado',
        self::STATUS_DELAYED => 'Entrega atrasada',
        self::STATUS_DELIVERED => 'Entregue',
        self::STATUS_BOUNCED => 'Devolvido',
        self::STATUS_REJECTED => 'Recusado pelo SES',
        self::STATUS_COMPLAINED => 'Marcado como spam',
    ];

    /** Que parte do sistema mandou, pelo nome da classe do Mailable. */
    public const ORIGIN_LABELS = [
        'DocumentReadyMail' => 'Convite para assinar',
        'DocumentCompletedMail' => 'Documento assinado',
        'SignatureVerificationCodeMail' => 'Código de verificação',
        'InvoiceMail' => 'NFS-e',
        'TenantInvitationMail' => 'Convite de membro',
        'CertificateExpiringMail' => 'Aviso de certificado',
        'SendingDomainTestMail' => 'Teste de envio',
    ];

    /**
     * Os eventos chegam fora de ordem (a reclamação pode vir antes da entrega). Um status só
     * substitui outro de peso menor.
     */
    public const STATUS_WEIGHT = [
        self::STATUS_SENT => 0,
        self::STATUS_DELAYED => 1,
        self::STATUS_DELIVERED => 2,
        self::STATUS_BOUNCED => 3,
        self::STATUS_REJECTED => 3,
        self::STATUS_COMPLAINED => 4,
    ];

    protected $fillable = [
        'tenant_id',
        'message_id',
        'recipient',
        'from_address',
        'provider',
        'mailable',
        'subject',
        'status',
        'bounce_type',
        'detail',
        'sent_at',
        'delivered_at',
        'bounced_at',
        'complained_at',
        'opened_at',
        'open_count',
        'clicked_at',
        'click_count',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmailDeliveryEvent::class)->orderBy('occurred_at');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function originLabel(): string
    {
        return self::ORIGIN_LABELS[class_basename((string) $this->mailable)] ?? 'Outro';
    }

    public function outranks(string $status): bool
    {
        return self::STATUS_WEIGHT[$this->status] > self::STATUS_WEIGHT[$status];
    }
}
