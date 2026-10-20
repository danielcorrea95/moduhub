<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Services\BillingCycleService;
use Illuminate\Console\Command;

class RunBillingCycle extends Command
{
    protected $signature = 'billing:cycle';

    protected $description = 'Fecha ciclos (cobra excedente), encerra testes grátis e bloqueia atrasos além da carência.';

    public function handle(BillingCycleService $cycle): int
    {
        $count = $cycle->run();
        $this->info("{$count} assinatura(s) processada(s).");

        return self::SUCCESS;
    }
}
