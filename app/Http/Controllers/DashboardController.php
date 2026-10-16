<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Tenancy\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantRepositoryContract $tenants,
        protected DocumentRepositoryContract $documents,
    ) {}

    public function __invoke(CurrentTenant $current): Response
    {
        $tenant = $current->get();

        $hasSignatures = $tenant !== null
            && in_array(Module::SIGNATURES, $this->tenants->activeModuleKeys($tenant), true);

        return Inertia::render('Dashboard', [
            // Sem o módulo não consultamos nada de assinaturas: a página trata `null` como "não contratado".
            'signatures' => $hasSignatures ? [
                'statusCounts' => $this->documents->countByStatus(),
                'inProgress' => $this->documents->latestInProgress()->map(fn (Document $d) => [
                    'id' => $d->getKey(),
                    'name' => $d->name,
                    'status' => $d->status,
                    'sent_at' => $d->sent_at,
                    'created_at' => $d->created_at,
                    'signers' => $d->signers->map(fn ($s) => [
                        'id' => $s->getKey(),
                        'name' => $s->name,
                        'status' => $s->status,
                    ])->values(),
                ])->values(),
            ] : null,
        ]);
    }
}
