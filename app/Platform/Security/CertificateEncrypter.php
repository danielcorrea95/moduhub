<?php

namespace App\Platform\Security;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Cifra os certificados digitais guardados no banco com a CERTIFICATES_ENCRYPTION_KEY, e não com a APP_KEY.
 */
#[Singleton]
final class CertificateEncrypter
{
    private ?Encrypter $encrypter = null;

    public function encrypt(#[\SensitiveParameter] string $value): string
    {
        return $this->encrypter()->encryptString($value);
    }

    public function decrypt(string $payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        $key = $this->parseKey((string) config('certificates.key'));

        if ($key === '') {
            throw new RuntimeException('Defina CERTIFICATES_ENCRYPTION_KEY no .env para guardar e ler certificados digitais.');
        }

        if ($key === $this->parseKey((string) config('app.key'))) {
            throw new RuntimeException('CERTIFICATES_ENCRYPTION_KEY precisa ser diferente da APP_KEY.');
        }

        $previous = array_map($this->parseKey(...), (array) config('certificates.previous_keys'));

        return $this->encrypter = (new Encrypter($key, (string) config('certificates.cipher')))->previousKeys($previous);
    }

    private function parseKey(#[\SensitiveParameter] string $key): string
    {
        return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key;
    }
}
