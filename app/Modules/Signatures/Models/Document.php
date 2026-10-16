<?php

namespace App\Modules\Signatures\Models;

use App\Models\User;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUlids, SoftDeletes, BelongsToTenant;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_SIGNED = 'partially_signed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Grupos de status usados como filtro na listagem do painel.
     * "Em andamento" junta enviado e parcialmente assinado: para quem envia, ambos significam "esperando alguém".
     */
    public const LISTING_FILTERS = [
        'in_progress' => [self::STATUS_SENT, self::STATUS_PARTIALLY_SIGNED],
        'completed' => [self::STATUS_COMPLETED],
        'rejected' => [self::STATUS_REJECTED],
        'draft' => [self::STATUS_DRAFT],
    ];

    public const SIGN_ORDER_PARALLEL = 'parallel';
    public const SIGN_ORDER_SEQUENTIAL = 'sequential';

    protected $fillable = [
        'tenant_id',
        'creator_id',
        'name',
        'status',
        'file_disk',
        'file_path',
        'file_hash',
        'file_size',
        'page_count',
        'final_file_path',
        'final_file_hash',
        'sign_order',
        'message',
        'external_id',
        'metadata',
        'expires_at',
        'sent_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class)->orderBy('order')->chaperone();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentSignatureField::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class)->orderByDesc('created_at');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }
}
