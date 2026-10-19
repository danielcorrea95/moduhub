<?php

namespace App\Modules\Invoices\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Documento recebido da distribuição do ADN (NFS-e ou evento), guardado como veio.
 */
class DistributedDocument extends Model
{
    use BelongsToTenant, HasUlids;

    public const TYPE_NFSE = 'NFSE';

    public const TYPE_EVENT = 'EVENTO';

    protected $table = 'nfse_documents';

    protected $fillable = [
        'tenant_id',
        'issuer_id',
        'environment',
        'nsu',
        'access_key',
        'document_type',
        'event_type',
        'xml',
        'generated_at',
    ];

    protected $hidden = ['xml'];

    protected $casts = [
        'nsu' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function isEvent(): bool
    {
        return $this->document_type === self::TYPE_EVENT;
    }
}
