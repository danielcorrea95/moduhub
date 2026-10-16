<?php

namespace App\Modules\Signatures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Http\Requests\ListDocumentsRequest;
use App\Modules\Signatures\Http\Requests\StoreDocumentRequest;
use App\Modules\Signatures\Models\Document;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentRepositoryContract $documents,
        protected DocumentServiceContract $service,
        protected SignerServiceContract $signerService,
    ) {}

    public function index(ListDocumentsRequest $request): Response
    {
        $documents = $this->documents
            ->paginateForListing($request->statusFilter(), $request->search())
            ->through(fn (Document $d) => [
                'id' => $d->getKey(),
                'name' => $d->name,
                'status' => $d->status,
                'page_count' => $d->page_count,
                'signers_count' => $d->signers_count,
                'fields_count' => $d->fields_count,
                'signers' => $d->signers->map(fn ($s) => [
                    'id' => $s->getKey(),
                    'name' => $s->name,
                    'status' => $s->status,
                ])->values(),
                'creator' => $d->creator ? [
                    'name' => $d->creator->name,
                    'email' => $d->creator->email,
                ] : null,
                'created_at' => $d->created_at,
                'sent_at' => $d->sent_at,
                'completed_at' => $d->completed_at,
            ]);

        return Inertia::render('Signatures/Index', [
            'documents' => $documents,
            'statusCounts' => $this->documents->countByStatus(),
            'filters' => [
                'status' => $request->statusFilter(),
                'search' => $request->search(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Document::class);

        return Inertia::render('Signatures/Create');
    }

    public function store(StoreDocumentRequest $request, CurrentTenant $tenant): RedirectResponse
    {
        $document = $this->service->createFromUpload(
            $tenant->get(),
            $request->user(),
            $request->file('file'),
            $request->attrs(),
        );

        $request->session()->flash('success', 'Documento criado. Adicione os signatários e envie.');

        return redirect()->route('signatures.show', $document->getKey());
    }

    public function show(Document $document): Response
    {
        $this->authorize('view', $document);

        $document = $this->documents->findWithRelations($document->getKey());

        return Inertia::render('Signatures/Show', [
            'document' => [
                'id' => $document->getKey(),
                'name' => $document->name,
                'status' => $document->status,
                'page_count' => $document->page_count,
                'file_size' => $document->file_size,
                'file_hash' => $document->file_hash,
                'sign_order' => $document->sign_order,
                'message' => $document->message,
                'external_id' => $document->external_id,
                'expires_at' => $document->expires_at,
                'sent_at' => $document->sent_at,
                'completed_at' => $document->completed_at,
                'created_at' => $document->created_at,
                'creator' => $document->creator ? [
                    'name' => $document->creator->name,
                    'email' => $document->creator->email,
                ] : null,
                'has_final_file' => $document->final_file_path !== null,
                // Campos explícitos: dados de auditoria do signatário não vão ao navegador, e o link
                // de assinatura (que carrega o token) só aparece enquanto a pessoa ainda pode assinar.
                'signers' => $document->signers->map(fn ($s) => [
                    'sign_url' => $this->signerService->isAwaitingSignature($s)
                        ? route('public-sign.show', $s->token)
                        : null,
                    'id' => $s->getKey(),
                    'order' => $s->order,
                    'name' => $s->name,
                    'role' => $s->role,
                    'email' => $s->email,
                    'cpf' => $s->cpf,
                    'cnpj' => $s->cnpj,
                    'phone' => $s->phone,
                    'auth_method' => $s->auth_method,
                    'status' => $s->status,
                    'viewed_at' => $s->viewed_at,
                    'signed_at' => $s->signed_at,
                    'rejected_at' => $s->rejected_at,
                    'rejection_reason' => $s->rejection_reason,
                ])->values(),
                'fields' => $document->fields,
                'events' => $document->events->map(fn ($e) => [
                    'id' => $e->getKey(),
                    'type' => $e->type,
                    'data' => $e->data,
                    'user' => $e->user ? ['name' => $e->user->name, 'email' => $e->user->email] : null,
                    'created_at' => $e->created_at,
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->service->delete($document);
        $request->session()->flash('success', 'Documento removido.');

        return redirect()->route('signatures.index');
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $disk = Storage::disk($document->file_disk);
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, ($document->name ?: 'documento') . '.pdf');
    }

    public function downloadWorking(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_if($document->final_file_path === null, 404);

        $disk = Storage::disk($document->file_disk);
        abort_unless($disk->exists($document->final_file_path), 404);

        return $disk->download(
            $document->final_file_path,
            ($document->name ?: 'documento') . '-com-assinaturas.pdf',
        );
    }

    public function send(Request $request, Document $document): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('send', $document);

        $this->service->send($document, $request->user(), $request);
        $request->session()->flash('success', 'Documento enviado para assinatura.');

        return back();
    }
}
