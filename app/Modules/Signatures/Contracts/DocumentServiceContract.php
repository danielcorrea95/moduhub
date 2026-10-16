<?php

namespace App\Modules\Signatures\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentEvent;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

interface DocumentServiceContract
{
    public function createFromUpload(Tenant $tenant, ?User $user, UploadedFile $pdf, array $attrs): Document;

    public function delete(Document $document): void;

    public function send(Document $document, ?User $actor, ?Request $request = null): Document;

    public function regenerateWorkingPdf(Document $document): string;

    public function notifySigner(DocumentSigner $signer, Document $document, ?User $sender = null): void;

    public function recordEvent(
        Document $document,
        string $type,
        ?User $user = null,
        array $data = [],
        ?string $signerId = null,
        ?Request $request = null,
    ): DocumentEvent;
}
