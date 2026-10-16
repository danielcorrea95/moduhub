<?php

namespace App\Modules\Signatures\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureField extends Model
{
    use HasUlids, BelongsToTenant;

    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_INITIAL = 'initial';
    public const TYPE_DATE = 'date';
    public const TYPE_NAME = 'name';
    public const TYPE_CPF = 'cpf';
    public const TYPE_EMAIL = 'email';
    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'signer_id',
        'type',
        'page',
        'x',
        'y',
        'width',
        'height',
        'required',
        'value',
    ];

    protected $casts = [
        'required' => 'boolean',
        'x' => 'float',
        'y' => 'float',
        'width' => 'float',
        'height' => 'float',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class, 'signer_id');
    }
}
