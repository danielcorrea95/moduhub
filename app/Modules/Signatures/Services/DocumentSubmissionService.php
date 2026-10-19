<?php

namespace App\Modules\Signatures\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Contracts\DocumentServiceContract;
use App\Modules\Signatures\Contracts\DocumentSubmissionServiceContract;
use App\Modules\Signatures\Contracts\SignerServiceContract;
use App\Modules\Signatures\Models\Document;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentSubmissionService implements DocumentSubmissionServiceContract
{
    public function __construct(
        protected DocumentServiceContract $documents,
        protected SignerServiceContract $signers,
    ) {}

    public function submit(Tenant $tenant, ?User $actor, UploadedFile $pdf, array $attrs, array $signers, bool $send): Document
    {
        return app(CurrentTenant::class)->run($tenant, fn () => $this->submitWithinTenant($tenant, $actor, $pdf, $attrs, $signers, $send));
    }

    protected function submitWithinTenant(Tenant $tenant, ?User $actor, UploadedFile $pdf, array $attrs, array $signers, bool $send): Document
    {
        $storedPaths = [];

        try {
            // Tudo ou nada: se um signatário ou o envio falhar, não sobra rascunho pela metade.
            // Os e-mails do envio só saem depois que send() passou de todas as validações.
            return DB::transaction(function () use ($tenant, $actor, $pdf, $attrs, $signers, $send, &$storedPaths) {
                $document = $this->documents->createFromUpload($tenant, $actor, $pdf, $attrs);
                $storedPaths[] = $document->file_path;

                foreach ($signers as $signer) {
                    $this->signers->add($document, $actor, $signer);
                }

                if ($send) {
                    $document = $this->documents->send($document, $actor);
                    $storedPaths[] = $document->final_file_path;
                }

                return $document->load('signers');
            });
        } catch (\Throwable $e) {
            // O rollback desfaz o banco, mas não o disco.
            Storage::disk('documents')->delete(array_filter($storedPaths));

            throw $e;
        }
    }
}
