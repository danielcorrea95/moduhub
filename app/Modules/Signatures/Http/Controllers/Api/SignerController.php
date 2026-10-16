<?php

namespace App\Modules\Signatures\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Http\Requests\Api\StoreSignerApiRequest;
use App\Modules\Signatures\Http\Resources\SignerResource;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\JsonResponse;

class SignerController extends Controller
{
    public function __construct(protected SignerServiceContract $service) {}

    public function store(StoreSignerApiRequest $request, Document $document): JsonResponse
    {
        $signer = $this->service->add($document, null, $request->validated());

        return SignerResource::make($signer)->response()->setStatusCode(201);
    }

    public function destroy(Document $document, DocumentSigner $signer): JsonResponse
    {
        $this->ensureBelongs($document, $signer);

        $this->service->remove($signer, null);

        return response()->json(null, 204);
    }

    public function remind(Document $document, DocumentSigner $signer): JsonResponse
    {
        $this->ensureBelongs($document, $signer);

        $this->service->remind($signer, null);

        return response()->json(['message' => "Link reenviado para {$signer->email}."]);
    }

    protected function ensureBelongs(Document $document, DocumentSigner $signer): void
    {
        abort_if($signer->document_id !== $document->getKey(), 404);
    }
}
