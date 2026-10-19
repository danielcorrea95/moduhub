<?php

namespace App\Modules\Invoices\Console;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\DocumentSyncService;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

class SyncDistributedDocuments extends Command
{
    protected $signature = 'invoices:sync-documents {--tenant= : só o emitente deste workspace (id)}';

    protected $description = 'Busca no ADN as NFS-e recebidas e os eventos de terceiros, para cada emitente com certificado válido';

    public function handle(DocumentSyncService $sync, CurrentTenant $tenants): int
    {
        // Sem sessão: percorre os emitentes de todos os workspaces, cada um com o próprio certificado
        // e dentro do próprio workspace.
        $issuers = Issuer::query()
            ->withoutTenantScope()
            ->whereNotNull('certificate_uploaded_at')
            ->where('certificate_valid_until', '>', now())
            ->when($this->option('tenant'), fn ($q, $tenant) => $q->where('tenant_id', $tenant))
            ->get();

        foreach ($issuers as $issuer) {
            $totals = $tenants->run($issuer->tenant, fn () => $sync->sync($issuer));
            $line = "{$issuer->legal_name} ({$issuer->environment}): {$totals['documents']} documentos, {$totals['received']} notas recebidas, {$totals['events']} eventos";
            $totals['error'] ? $this->warn("{$line}. Erro: {$totals['error']}") : $this->info($line);
        }

        return self::SUCCESS;
    }
}
