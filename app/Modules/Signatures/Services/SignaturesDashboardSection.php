<?php

namespace App\Modules\Signatures\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Contracts\DocumentRepositoryContract;
use App\Modules\Signatures\Models\Document;
use App\Platform\Contracts\DashboardSectionContract;
use App\Platform\Dashboard\DashboardSection;
use Carbon\CarbonImmutable;

/** Assinaturas na tela inicial: o movimento do mês e o que ainda espera alguém assinar. */
class SignaturesDashboardSection implements DashboardSectionContract
{
    public function __construct(
        private DocumentRepositoryContract $documents,
    ) {}

    public function section(Tenant $tenant, User $user): ?DashboardSection
    {
        if (! $tenant->hasModule(Module::SIGNATURES)) {
            return null;
        }

        $now = CarbonImmutable::now('America/Sao_Paulo');
        [$from, $to] = [$now->startOfMonth()->utc(), $now->startOfMonth()->addMonth()->utc()];

        return new DashboardSection(
            key: Module::SIGNATURES,
            data: [
                'statusCounts' => $this->documents->countByStatus(),
                'month' => [
                    'sent' => $this->documents->countSentBetween($tenant->getKey(), $from, $to),
                    'completed' => $this->documents->countCompletedBetween($tenant->getKey(), $from, $to),
                ],
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
            ],
        );
    }
}
