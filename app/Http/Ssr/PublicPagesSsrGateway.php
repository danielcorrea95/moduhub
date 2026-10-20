<?php

namespace App\Http\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * SSR só para as páginas que buscadores e robôs de IA leem. O painel fica de fora: não precisa de
 * indexação e assim nenhuma tela logada depende do processo Node. Se o Node estiver fora do ar, a
 * página sai renderizada no navegador, como antes.
 */
class PublicPagesSsrGateway extends HttpGateway
{
    /** Componentes Inertia renderizados no servidor (prefixo). */
    public const PUBLIC_COMPONENTS = ['Welcome', 'Products/', 'Docs/'];

    /** Página nunca espera mais que isso pelo Node; passou, sai sem SSR. */
    private const TIMEOUT_SECONDS = 2;

    public function dispatch(array $page): ?Response
    {
        if (! $this->isPublic((string) ($page['component'] ?? '')) || ! $this->shouldDispatch()) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->post($this->getUrl('/render'), $page)->throw()->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return null;
        }

        return $response === null ? null : new Response(implode("\n", $response['head']), $response['body']);
    }

    private function isPublic(string $component): bool
    {
        foreach (self::PUBLIC_COMPONENTS as $prefix) {
            if ($component === $prefix || (str_ends_with($prefix, '/') && str_starts_with($component, $prefix))) {
                return true;
            }
        }

        return false;
    }
}
