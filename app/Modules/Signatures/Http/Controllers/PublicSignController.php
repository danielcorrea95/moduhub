<?php

namespace App\Modules\Signatures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\SignatureRepositoryContract;
use App\Modules\Signatures\Contracts\SignatureServiceContract;
use App\Modules\Signatures\Http\Requests\RejectSignatureRequest;
use App\Modules\Signatures\Http\Requests\SendVerificationCodeRequest;
use App\Modules\Signatures\Http\Requests\SignWithIcpRequest;
use App\Modules\Signatures\Http\Requests\VerifyAndSignRequest;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSignController extends Controller
{
    public function __construct(
        protected SignatureRepositoryContract $repo,
        protected SignatureServiceContract $service,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);

        if ($signer === null) {
            return Inertia::render('PublicSign/Invalid');
        }

        $document = $signer->document;

        if ($signer->status === DocumentSigner::STATUS_SIGNED) {
            return Inertia::render('PublicSign/AlreadySigned', [
                'signer' => $this->signerPayload($signer),
                'document' => $this->documentPayload($document),
                'tenant' => $this->tenantPayload($document),
            ]);
        }

        if ($signer->status === DocumentSigner::STATUS_REJECTED
            || $document->status === Document::STATUS_REJECTED) {
            return Inertia::render('PublicSign/Rejected', [
                'signer' => $this->signerPayload($signer),
                'document' => $this->documentPayload($document),
                'tenant' => $this->tenantPayload($document),
            ]);
        }

        if ($document->expires_at !== null && $document->expires_at->isPast()) {
            return Inertia::render('PublicSign/Expired', [
                'document' => $this->documentPayload($document),
                'tenant' => $this->tenantPayload($document),
            ]);
        }

        if (! in_array($document->status, [Document::STATUS_SENT, Document::STATUS_PARTIALLY_SIGNED], true)) {
            return Inertia::render('PublicSign/Invalid');
        }

        $this->service->markViewed($signer, $request);

        $pendingBefore = 0;
        if ($document->sign_order === Document::SIGN_ORDER_SEQUENTIAL) {
            $pendingBefore = $this->repo->pendingSignersBefore($document, $signer->order);
        }

        return Inertia::render('PublicSign/Show', [
            'signer' => $this->signerPayload($signer),
            'document' => $this->documentPayload($document),
            'tenant' => $this->tenantPayload($document),
            'waiting_predecessors' => $pendingBefore,
            'pdf_url' => route('public-sign.pdf', $token),
            'code_sent' => $signer->verification_sent_at !== null
                && $signer->verification_sent_at->diffInMinutes(now()) < 15,
        ]);
    }

    public function pdf(string $token): StreamedResponse
    {
        $signer = $this->repo->findSignerByToken($token);
        abort_if($signer === null, 404);

        $document = $signer->document;
        abort_if($document === null || $document->final_file_path === null, 404);

        $disk = Storage::disk($document->file_disk);
        abort_unless($disk->exists($document->final_file_path), 404);

        return $disk->response($document->final_file_path, 'documento.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sendCode(SendVerificationCodeRequest $request, string $token): RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);
        abort_if($signer === null, 404);

        $this->service->sendVerificationCode($signer, $request);
        $request->session()->flash('success', 'Enviamos um código para ' . $this->maskEmail($signer->email) . '.');

        return redirect()->route('public-sign.show', $token);
    }

    public function verifyAndSign(VerifyAndSignRequest $request, string $token): RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);
        abort_if($signer === null, 404);

        $signatureImage = $request->input('signature_image');
        $this->service->sign(
            $signer,
            $request->string('code')->toString(),
            $request,
            is_string($signatureImage) && $signatureImage !== '' ? $signatureImage : null,
        );

        return redirect()->route('public-sign.thanks', $token);
    }

    public function verifyAndSignIcp(SignWithIcpRequest $request, string $token): RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);
        abort_if($signer === null, 404);

        $pkcs12Binary = file_get_contents($request->file('certificate')->getRealPath());
        if ($pkcs12Binary === false) {
            abort(422);
        }

        $this->service->signWithIcp(
            $signer,
            $pkcs12Binary,
            $request->string('password')->toString(),
            $request,
        );

        return redirect()->route('public-sign.thanks', $token);
    }

    public function reject(RejectSignatureRequest $request, string $token): RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);
        abort_if($signer === null, 404);

        $this->service->reject($signer, $request->string('reason')->toString(), $request);

        return redirect()->route('public-sign.show', $token);
    }

    public function thanks(string $token): Response|RedirectResponse
    {
        $signer = $this->repo->findSignerByToken($token);

        if ($signer === null) {
            return Inertia::render('PublicSign/Invalid');
        }

        return Inertia::render('PublicSign/Thanks', [
            'signer' => $this->signerPayload($signer),
            'document' => $this->documentPayload($signer->document),
            'tenant' => $this->tenantPayload($signer->document),
        ]);
    }

    protected function signerPayload(DocumentSigner $signer): array
    {
        return [
            'id' => $signer->getKey(),
            'name' => $signer->name,
            'email' => $signer->email,
            'cpf' => $signer->cpf,
            'order' => $signer->order,
            'status' => $signer->status,
            'auth_method' => $signer->auth_method,
            'signed_at' => $signer->signed_at,
            'rejected_at' => $signer->rejected_at,
            'rejection_reason' => $signer->rejection_reason,
        ];
    }

    protected function documentPayload(?Document $document): ?array
    {
        if ($document === null) {
            return null;
        }

        return [
            'id' => $document->getKey(),
            'name' => $document->name,
            'status' => $document->status,
            'sign_order' => $document->sign_order,
            'message' => $document->message,
            'page_count' => $document->page_count,
            'file_hash' => $document->file_hash,
            'expires_at' => $document->expires_at,
            'sent_at' => $document->sent_at,
            'completed_at' => $document->completed_at,
        ];
    }

    protected function tenantPayload(?Document $document): ?array
    {
        $tenant = $document?->tenant;
        if ($tenant === null) {
            return null;
        }

        return [
            'name' => $tenant->name,
            'branding' => $tenant->branding ?? [],
        ];
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + ['', ''];
        if ($local === '' || $domain === '') {
            return $email;
        }
        $visible = max(1, (int) floor(strlen($local) / 3));
        return substr($local, 0, $visible) . str_repeat('•', max(2, strlen($local) - $visible)) . '@' . $domain;
    }
}
