<?php

namespace App\Modules\Signatures\Http\Middleware;

use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A página pública de assinatura age em nome do workspace dono do documento. Quem abre o link pode estar
 * logado no Moduhub em outro workspace, e aí a sessão já pôs esse outro tenant no contexto: sem a troca,
 * as relações do documento (signatários, eventos) seriam filtradas pelo workspace errado e viriam vazias.
 */
class EnterSignerTenant
{
    public function __construct(
        protected SignatureRepositoryContract $signatures,
        protected CurrentTenant $currentTenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->signatures->findSignerByToken((string) $request->route('token'))?->document?->tenant;

        if ($tenant !== null) {
            $this->currentTenant->set($tenant);
        }

        return $next($request);
    }
}
