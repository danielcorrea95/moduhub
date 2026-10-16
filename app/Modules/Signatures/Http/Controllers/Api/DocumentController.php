<?php

namespace App\Modules\Signatures\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\DocumentSubmissionServiceContract;
use App\Modules\Signatures\Http\Requests\Api\ListDocumentsApiRequest;
use App\Modules\Signatures\Http\Requests\Api\StoreDocumentApiRequest;
use App\Modules\Signatures\Http\Resources\DocumentResource;
use App\Modules\Signatures\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentRepositoryContract $documents,
        protected DocumentServiceContract $service,
        protected DocumentSubmissionServiceContract $submission,
    ) {}

    public function index(ListDocumentsApiRequest $request): AnonymousResourceCollection
    {
        $page = $this->documents->paginateForListing(
            $request->validated('status'),
            $request->validated('search'),
            (int) ($request->validated('per_page') ?? 20),
            $request->validated('external_id'),
        );

        return DocumentResource::collection($page);
    }

    public function store(StoreDocumentApiRequest $request): JsonResponse
    {
        $document = $this->submission->submit(
            $request->attributes->get('tenant'),
            null,
            $request->pdf(),
            $request->attrs(),
            $request->signers(),
            $request->shouldSend(),
        );

        return DocumentResource::make($document)->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        return DocumentResource::make($this->documents->findWithRelations($document->getKey()));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->service->delete($document);

        return response()->json(null, 204);
    }

    public function send(Request $request, Document $document): DocumentResource
    {
        $sent = $this->service->send($document, null, $request);

        return DocumentResource::make($sent->load('signers'));
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $signed = $request->query('type', 'original') === 'signed';
        $path = $signed ? $document->final_file_path : $document->file_path;

        $disk = Storage::disk($document->file_disk);
        abort_if($path === null || ! $disk->exists($path), 404, 'Arquivo ainda não disponível.');

        $suffix = $signed ? '-com-assinaturas' : '';

        return $disk->download($path, ($document->name ?: 'documento').$suffix.'.pdf');
    }
}
