<?php

namespace App\Modules\Invoices\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * NFS-e emitida por terceiros em que o workspace aparece: como tomador, intermediário ou como
 * prestador de uma nota que o tomador emitiu. Chega pela distribuição do ADN.
 */
class ReceivedInvoice extends Model
{
    use BelongsToTenant, HasUlids;

    public const ROLE_TAKER = 'taker';

    public const ROLE_INTERMEDIARY = 'intermediary';

    public const ROLE_PROVIDER = 'provider';

    public const ROLE_LABELS = [
        self::ROLE_TAKER => 'Tomador',
        self::ROLE_INTERMEDIARY => 'Intermediário',
        self::ROLE_PROVIDER => 'Prestador (nota emitida pelo tomador)',
    ];

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REPLACED = 'replaced';

    public const STATUSES = [self::STATUS_ISSUED, self::STATUS_CANCELLED, self::STATUS_REPLACED];

    /** cMotivo da rejeição por quem recebeu a nota. */
    public const REJECTION_REASON_LABELS = [
        '1' => 'NFS-e em duplicidade',
        '2' => 'NFS-e já emitida pelo tomador',
        '3' => 'Não ocorrência do fato gerador',
        '4' => 'Erro quanto à responsabilidade tributária',
        '5' => 'Erro no valor do serviço, das deduções, no serviço prestado ou na data do fato gerador',
        '9' => 'Outros',
    ];

    protected $fillable = [
        'tenant_id',
        'environment',
        'access_key',
        'role',
        'status',
        'provider_document',
        'provider_name',
        'taker_document',
        'taker_name',
        'nfse_number',
        'competence_date',
        'issued_at',
        'amount',
        'iss_amount',
        'description',
        'manifestation',
        'nfse_xml',
    ];

    protected $hidden = ['nfse_xml'];

    protected $casts = [
        'competence_date' => 'date',
        'issued_at' => 'datetime',
        'amount' => 'decimal:2',
        'iss_amount' => 'decimal:2',
        'manifestation' => 'array',
    ];

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isManifested(): bool
    {
        return ! empty($this->manifestation);
    }
}
