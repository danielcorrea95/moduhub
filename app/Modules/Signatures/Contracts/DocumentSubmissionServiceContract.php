<?php

namespace App\Modules\Signatures\Contracts;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Models\Document;
use Illuminate\Http\UploadedFile;

interface DocumentSubmissionServiceContract
{
    /**
     * Cria o documento com seus signatários numa só operação e, se pedido, já envia para assinatura.
     *
     * @param  array<int, array<string, mixed>>  $signers
     */
    public function submit(Tenant $tenant, ?User $actor, UploadedFile $pdf, array $attrs, array $signers, bool $send): Document;
}
