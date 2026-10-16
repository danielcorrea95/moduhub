<?php

namespace App\Modules\Signatures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Signatures\Contracts\SignerRepositoryContract;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Http\Requests\RemindSignerRequest;
use App\Modules\Signatures\Http\Requests\ReorderSignersRequest;
use App\Modules\Signatures\Http\Requests\StoreSignerRequest;
use App\Modules\Signatures\Http\Requests\UpdateSignerRequest;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignerController extends Controller
{
    public function __construct(
        protected SignerRepositoryContract $repo,
        protected SignerServiceContract $service,
    ) {}

    public function store(StoreSignerRequest $request, Document $document): RedirectResponse
    {
        $this->service->add($document, $request->user(), $request->validated());

        $request->session()->flash('success', 'Signatário adicionado.');

        return back();
    }

    public function update(UpdateSignerRequest $request, Document $document, DocumentSigner $signer): RedirectResponse
    {
        $this->ensureBelongs($document, $signer);

        $this->service->update($signer, $request->user(), $request->validated());

        $request->session()->flash('success', 'Signatário atualizado.');

        return back();
    }

    public function destroy(Request $request, Document $document, DocumentSigner $signer): RedirectResponse
    {
        $this->authorize('manageSigners', $document);
        $this->ensureBelongs($document, $signer);

        $this->service->remove($signer, $request->user());

        $request->session()->flash('success', 'Signatário removido.');

        return back();
    }

    public function reorder(ReorderSignersRequest $request, Document $document): RedirectResponse
    {
        $this->service->reorder($document, $request->user(), $request->input('order', []));

        $request->session()->flash('success', 'Ordem atualizada.');

        return back();
    }

    public function remind(RemindSignerRequest $request, Document $document, DocumentSigner $signer): RedirectResponse
    {
        $this->ensureBelongs($document, $signer);

        $this->service->remind($signer, $request->user());

        $request->session()->flash('success', "Link reenviado para {$signer->email}.");

        return back();
    }

    protected function ensureBelongs(Document $document, DocumentSigner $signer): void
    {
        abort_if($signer->document_id !== $document->getKey(), 404);
    }
}
