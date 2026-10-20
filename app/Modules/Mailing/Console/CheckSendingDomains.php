<?php

namespace App\Modules\Mailing\Console;

use App\Modules\Mailing\Services\SendingDomainService;
use Illuminate\Console\Command;

class CheckSendingDomains extends Command
{
    protected $signature = 'mailing:check-domains';

    protected $description = 'Confere no SES o DNS dos domínios de envio dos workspaces';

    public function handle(SendingDomainService $service): int
    {
        $this->info("Domínios conferidos: {$service->checkAll()}");

        return self::SUCCESS;
    }
}
