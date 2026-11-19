<?php

namespace App\Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mailing\Exceptions\RelayRejected;
use App\Modules\Mailing\Http\Requests\RelayAuthRequest;
use App\Modules\Mailing\Http\Requests\RelayMessageRequest;
use App\Modules\Mailing\Services\Relay\SmtpRelayService;
use Illuminate\Http\JsonResponse;

/** Endpoints internos do servidor SMTP do relay. A resposta vira a linha SMTP que o cliente vê. */
class SmtpRelayController extends Controller
{
    public function __construct(
        private SmtpRelayService $relay,
    ) {}

    public function authenticate(RelayAuthRequest $request): JsonResponse
    {
        try {
            $credential = $this->relay->authenticate(
                $request->validated('username'),
                $request->validated('password'),
                $request->validated('remote_ip'),
            );
        } catch (RelayRejected $e) {
            return $this->rejected($e);
        }

        return response()->json(['ok' => true, 'tenant_id' => $credential->tenant_id]);
    }

    public function deliver(RelayMessageRequest $request): JsonResponse
    {
        try {
            $credential = $this->relay->authenticate(
                $request->validated('username'),
                $request->validated('password'),
                $request->validated('remote_ip'),
            );

            $messageId = $this->relay->relay($credential, $request->rawMessage(), $request->recipients());
        } catch (RelayRejected $e) {
            return $this->rejected($e);
        }

        return response()->json(['ok' => true, 'message_id' => $messageId]);
    }

    private function rejected(RelayRejected $e): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'smtp_code' => $e->smtpCode,
            'enhanced_code' => $e->enhancedCode,
            'message' => $e->getMessage(),
        ], 422);
    }
}
