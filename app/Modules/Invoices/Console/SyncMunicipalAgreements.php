<?php

namespace App\Modules\Invoices\Console;

use App\Modules\Invoices\Models\Issuer;
use App\Modules\Invoices\Services\MunicipalAgreementService;
use Illuminate\Console\Command;
use RuntimeException;

class SyncMunicipalAgreements extends Command
{
    protected $signature = 'invoices:sync-agreements
        {--environment=* : production, restricted ou ambos (padrão)}
        {--uf=* : só estes estados, ex.: --uf=SP --uf=RS}';

    protected $description = 'Atualiza os convênios dos municípios com o Sistema Nacional NFS-e, consultando o ADN';

    public function handle(MunicipalAgreementService $service): int
    {
        $environments = $this->option('environment') ?: Issuer::ENVIRONMENTS;
        $ufs = array_map('strtoupper', (array) $this->option('uf'));

        foreach ($environments as $environment) {
            if (! in_array($environment, Issuer::ENVIRONMENTS, true)) {
                $this->error("Ambiente desconhecido: {$environment}");

                return self::INVALID;
            }

            $this->info("Ambiente {$environment}".($ufs ? ' ('.implode(', ', $ufs).')' : ''));
            $bar = $this->output->createProgressBar();

            try {
                $totals = $service->sync($environment, $ufs, fn () => $bar->advance());
            } catch (RuntimeException $e) {
                $this->newLine();
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $bar->finish();
            $this->newLine();
            $this->table(['Status', 'Municípios'], collect($totals)->map(fn ($n, $status) => [$status, $n])->values()->all());
        }

        return self::SUCCESS;
    }
}
