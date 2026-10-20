<?php

namespace App\Modules\Mailing\Models;

use App\Models\User;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial SMTP do workspace. Revogar é apagar: o relay deixa de aceitar na próxima conexão.
 */
class SmtpCredential extends Model
{
    use BelongsToTenant, HasUlids;

    public const USERNAME_PREFIX = 'mdhb_smtp_';

    public const MAX_PER_TENANT = 10;

    protected $fillable = [
        'tenant_id',
        'name',
        'username',
        'password_hash',
        'last_four',
        'last_used_at',
        'created_by',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
