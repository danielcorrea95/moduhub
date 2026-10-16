<?php

namespace App\Modules\Signatures\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSigner extends Model
{
    use HasUlids, BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_REJECTED = 'rejected';

    public const AUTH_EMAIL = 'email';
    public const AUTH_SMS = 'sms';
    public const AUTH_EMAIL_AND_SMS = 'email_and_sms';
    public const AUTH_ICP = 'icp';

    public const TYPE_ELECTRONIC = 'electronic';
    public const TYPE_ICP = 'icp';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'order',
        'name',
        'email',
        'cpf',
        'cnpj',
        'phone',
        'auth_method',
        'status',
        'token',
        'verification_code',
        'verification_sent_at',
        'verification_attempts',
        'signature_type',
        'viewed_at',
        'signed_at',
        'rejected_at',
        'rejection_reason',
        'signature_image_path',
        'icp_certificate_subject',
        'icp_certificate_serial',
        'icp_certificate_issuer',
        'icp_certificate_valid_from',
        'icp_certificate_valid_to',
        'ip_address',
        'user_agent',
        'geolocation',
    ];

    protected $hidden = ['verification_code'];

    protected $casts = [
        'verification_sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'icp_certificate_valid_from' => 'datetime',
        'icp_certificate_valid_to' => 'datetime',
        'geolocation' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentSignatureField::class, 'signer_id');
    }
}
