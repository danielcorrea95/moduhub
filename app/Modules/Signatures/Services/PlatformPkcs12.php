<?php

namespace App\Modules\Signatures\Services;

/** O .pfx da plataforma com a senha, de onde quer que tenha vindo (painel ou servidor). */
final readonly class PlatformPkcs12
{
    public const ORIGIN_PANEL = 'panel';

    public const ORIGIN_SERVER = 'server';

    public function __construct(
        #[\SensitiveParameter] public string $binary,
        #[\SensitiveParameter] public string $password,
        public string $origin,
    ) {}

    /** dump(), print_r e os relatórios de erro mostram o objeto sem o arquivo e a senha. */
    public function __debugInfo(): array
    {
        return ['binary' => '[oculto]', 'password' => '[oculta]', 'origin' => $this->origin];
    }
}
