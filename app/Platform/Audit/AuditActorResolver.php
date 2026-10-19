<?php

namespace App\Platform\Audit;

use App\Models\ApiKey;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Descobre o ator da ação em curso. É o único ponto que olha o request por conta própria: a auditoria
 * acontece no fundo dos serviços (quando a chave é aberta), longe do controller que poderia passar o ator.
 */
class AuditActorResolver
{
    public function __construct(
        protected Application $app,
        protected AuthFactory $auth,
    ) {}

    public function current(): AuditActor
    {
        // Em job ou comando o request é o vazio do console: sem chave nem usuário, quem age é o sistema.
        $request = $this->app->make(Request::class);
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey instanceof ApiKey) {
            return new AuditActor(AuditActor::TYPE_API_KEY, $apiKey->getKey(), "API: {$apiKey->name}", $request->ip(), $request->userAgent());
        }

        $user = $this->auth->guard()->user();
        if ($user !== null) {
            return new AuditActor(AuditActor::TYPE_USER, (string) $user->getAuthIdentifier(), $user->name ?? $user->email ?? null, $request->ip(), $request->userAgent());
        }

        return new AuditActor(AuditActor::TYPE_SYSTEM, null, 'Sistema');
    }
}
