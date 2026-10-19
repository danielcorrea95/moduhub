<?php

namespace App\Platform\Casts;

use App\Platform\Security\CertificateEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Igual ao cast `encrypted` do Laravel, mas com a chave dos certificados em vez da APP_KEY.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class CertificateEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(CertificateEncrypter::class)->decrypt((string) $value);
    }

    public function set(Model $model, string $key, #[\SensitiveParameter] mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(CertificateEncrypter::class)->encrypt((string) $value);
    }
}
