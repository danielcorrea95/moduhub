<?php

namespace App\Modules\Signatures\Models;

use App\Models\User;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEvent extends Model
{
    use HasUlids, BelongsToTenant;

    public $timestamps = false;

    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_SENT = 'sent';
    public const TYPE_REMINDED = 'reminded';
    public const TYPE_VIEWED = 'viewed';
    public const TYPE_CODE_SENT = 'code_sent';
    public const TYPE_CODE_VERIFIED = 'code_verified';
    public const TYPE_SIGNED = 'signed';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_DOWNLOADED = 'downloaded';
    public const TYPE_FINALIZED = 'finalized';
    public const TYPE_COPIES_SENT = 'copies_sent';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'signer_id',
        'user_id',
        'type',
        'data',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class, 'signer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
