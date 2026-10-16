<?php

namespace App\Modules\Signatures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(
        protected DocumentRepositoryContract $documents,
    ) {}

    public function show(string $short): Response
    {
        $document = $this->documents->findByShortId($short);

        if ($document === null) {
            return Inertia::render('Verify/NotFound', ['short' => $short]);
        }

        return Inertia::render('Verify/Show', [
            'document' => $this->documentPayload($document),
            'signers' => $document->signers->map(fn ($s) => $this->signerPayload($s))->values(),
            'events' => $document->events->map(fn ($e) => $this->eventPayload($e))->values(),
            'tenant' => $this->tenantPayload($document),
            'short' => strtoupper(substr($document->getKey(), -12)),
            'can_download' => $this->canDownload($document),
        ]);
    }

    public function download(string $short): StreamedResponse|RedirectResponse
    {
        $document = $this->documents->findByShortId($short);
        abort_if($document === null, 404);
        abort_unless($this->canDownload($document), 404);

        $disk = Storage::disk($document->file_disk);
        abort_unless($disk->exists($document->final_file_path), 404);

        return $disk->response($document->final_file_path, 'documento-assinado.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function canDownload(Document $document): bool
    {
        return in_array($document->status, [
            Document::STATUS_COMPLETED,
            Document::STATUS_PARTIALLY_SIGNED,
        ], true) && $document->final_file_path !== null;
    }

    protected function documentPayload(Document $document): array
    {
        $hasIcp = $document->signers->contains(fn ($s) => $s->signature_type === DocumentSigner::TYPE_ICP);

        return [
            'id' => $document->getKey(),
            'name' => $document->name,
            'status' => $document->status,
            'sign_order' => $document->sign_order,
            'page_count' => $document->page_count,
            'file_hash' => $document->file_hash,
            'final_file_hash' => $document->final_file_hash,
            'created_at' => $document->created_at,
            'sent_at' => $document->sent_at,
            'completed_at' => $document->completed_at,
            'expires_at' => $document->expires_at,
            'has_icp_signature' => $hasIcp,
        ];
    }

    protected function signerPayload(DocumentSigner $signer): array
    {
        return [
            'id' => $signer->getKey(),
            'order' => $signer->order,
            'name' => $signer->name,
            'email' => $this->maskEmail($signer->email),
            'cpf' => $signer->cpf ? $this->maskCpf($signer->cpf) : null,
            'cnpj' => $signer->cnpj,
            'auth_method' => $signer->auth_method,
            'signature_type' => $signer->signature_type,
            'status' => $signer->status,
            'signed_at' => $signer->signed_at,
            'rejected_at' => $signer->rejected_at,
            'rejection_reason' => $signer->rejection_reason,
            'ip_address' => $signer->ip_address,
            'icp_certificate_subject' => $signer->icp_certificate_subject,
            'icp_certificate_issuer' => $signer->icp_certificate_issuer,
            'icp_certificate_valid_to' => $signer->icp_certificate_valid_to,
        ];
    }

    protected function eventPayload(DocumentEvent $event): array
    {
        return [
            'id' => $event->getKey(),
            'type' => $event->type,
            'created_at' => $event->created_at,
            'ip_address' => $event->ip_address,
        ];
    }

    protected function tenantPayload(Document $document): ?array
    {
        $tenant = $document->tenant;
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

    protected function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11) {
            return $cpf;
        }
        return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
    }
}
