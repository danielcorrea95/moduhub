<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey || ! $apiKey->hasScope($scope)) {
            return response()->json([
                'message' => "A chave de API não tem o escopo [{$scope}].",
                'required_scope' => $scope,
            ], 403);
        }

        return $next($request);
    }
}
