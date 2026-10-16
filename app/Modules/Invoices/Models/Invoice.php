<?php

namespace App\Modules\Invoices\Models;

use App\Models\User;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma NFS-e do workspace, desde a DPS assinada até a nota gerada (ou rejeitada) pela Sefin Nacional.
 */
class Invoice extends Model
{
    use HasUlids, BelongsToTenant;

    /** DPS enviada sem resposta conclusiva (falha de rede, Sefin fora do ar). Sincronizar resolve. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ISSUED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /** Campo tribISSQN da DPS. */
    public const ISS_TAXABLE = 1;
    public const ISS_IMMUNE = 2;
    public const ISS_EXPORT = 3;
    public const ISS_NOT_LEVIED = 4;

    public const ISS_TAXATION_LABELS = [
        self::ISS_TAXABLE => 'Operação tributável',
        self::ISS_IMMUNE => 'Imunidade',
        self::ISS_EXPORT => 'Exportação de serviço',
        self::ISS_NOT_LEVIED => 'Não incidência',
    ];

    /** Campo cMotivo do evento de cancelamento (e101101). */
    public const CANCEL_ISSUE_ERROR = 1;
    public const CANCEL_SERVICE_NOT_PROVIDED = 2;
    public const CANCEL_OTHER = 9;

    public const CANCEL_REASON_LABELS = [
        self::CANCEL_ISSUE_ERROR => 'Erro na emissão',
        self::CANCEL_SERVICE_NOT_PROVIDED => 'Serviço não prestado',
        self::CANCEL_OTHER => 'Outros',
    ];

    protected $fillable = [
        'tenant_id',
        'issuer_id',
        'creator_id',
        'external_id',
        'competence_date',
        'taker_document',
        'taker_name',
        'taker',
        'service',
        'amount',
        'iss_rate',
        'iss_withheld',
        'iss_taxation',
        'approximate_taxes',
    ];

    protected $hidden = [
        'dps_xml',
        'nfse_xml',
        'cancellation_xml',
    ];

    protected $casts = [
        'taker' => 'array',
        'service' => 'array',
        'rejection' => 'array',
        'approximate_taxes' => 'array',
        'amount' => 'decimal:2',
        'iss_rate' => 'decimal:2',
        'iss_withheld' => 'boolean',
        'iss_taxation' => 'integer',
        'dps_number' => 'integer',
        'cancel_reason_code' => 'integer',
        'competence_date' => 'date',
        'emitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasNfseXml(): bool
    {
        return $this->nfse_xml !== null;
    }
}
