<?php

namespace App\Modules\Mailing\Contracts;

use App\Models\Tenant;
use App\Models\TenantModuleUsage;
use App\Models\TenantSubscription;

interface EmailUsageRepositoryContract
{
    /** Assinatura atual com plano e módulos, para ler as cotas do `mailing`. */
    public function subscriptionFor(Tenant $tenant): ?TenantSubscription;

    /**
     * Linha do dia, criada se faltar, travada até o fim da transação. Serializa envios
     * simultâneos do mesmo workspace sem travar os outros.
     */
    public function lockDay(Tenant $tenant, string $date): TenantModuleUsage;

    /** Mensagens contadas entre as duas datas, inclusive. */
    public function sumBetween(Tenant $tenant, string $from, string $to): int;

    public function countOn(Tenant $tenant, string $date): int;

    public function add(TenantModuleUsage $day, int $messages): void;
}
