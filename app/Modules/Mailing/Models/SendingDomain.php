<?php

namespace App\Modules\Mailing\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Domínio do workspace verificado no SES. Enquanto não estiver `verified`, os e-mails do
 * workspace continuam saindo pelo remetente da Moduhub.
 */
class SendingDomain extends Model
{
    use BelongsToTenant, HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    /** Os registros sumiram do DNS ou o SES desistiu de verificar (72h sem achar). */
    public const STATUS_FAILED = 'failed';

    public const MAIL_FROM_SUBDOMAIN = 'bounce';

    protected $fillable = [
        'tenant_id',
        'domain',
        'status',
        'dkim_tokens',
        'dkim_status',
        'mail_from_status',
        'from_name',
        'from_local_part',
        'reply_to',
        'verified_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'dkim_tokens' => 'array',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function fromAddress(): string
    {
        return "{$this->from_local_part}@{$this->domain}";
    }

    public function mailFromDomain(): string
    {
        return self::MAIL_FROM_SUBDOMAIN.'.'.$this->domain;
    }
}
