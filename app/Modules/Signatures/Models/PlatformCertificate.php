<?php

namespace App\Modules\Signatures\Models;

use App\Platform\Casts\CertificateEncrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * e-CNPJ da Moduhub enviado pelo admin. Não é de workspace: vale para a plataforma inteira.
 * Só a linha atual guarda o .pfx e a senha; as aposentadas ficam como histórico.
 */
class PlatformCertificate extends Model
{
    use HasUlids;

    public const RETIRED_REPLACED = 'replaced';

    public const RETIRED_REMOVED = 'removed';

    protected $guarded = ['id'];

    protected $hidden = [
        'pfx',
        'password',
    ];

    protected $casts = [
        'pfx' => CertificateEncrypted::class,
        'password' => CertificateEncrypted::class,
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function isCurrent(): bool
    {
        return $this->retired_at === null;
    }
}
