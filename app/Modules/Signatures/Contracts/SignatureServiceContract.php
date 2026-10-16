<?php

namespace App\Modules\Signatures\Contracts;

use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\Request;

interface SignatureServiceContract
{
    public function markViewed(DocumentSigner $signer, Request $request): void;

    public function sendVerificationCode(DocumentSigner $signer, Request $request): void;

    public function sign(DocumentSigner $signer, string $code, Request $request, ?string $signatureImage = null): void;

    public function signWithIcp(DocumentSigner $signer, string $pkcs12Binary, string $password, Request $request): void;

    public function reject(DocumentSigner $signer, string $reason, Request $request): void;
}
