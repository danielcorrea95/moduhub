<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Contracts\MunicipalAgreementRepositoryContract;
use App\Modules\Invoices\Contracts\MunicipalAgreementsContract;
use App\Modules\Invoices\Services\Adn\AgreementCheck;
use App\Modules\Invoices\Services\Nfse\Municipalities;

/**
 * Mantém a tabela de convênios em dia varrendo o ADN município a município (o ADN não tem endpoint
 * que liste todos). São ~5.600 consultas de ~0,1 s por ambiente; roda agendado, fora do horário útil.
 */
class MunicipalAgreementService
{
    private const BATCH = 200;

    public function __construct(
        protected MunicipalAgreementsContract $adn,
        protected MunicipalAgreementRepositoryContract $agreements,
    ) {}

    /**
     * @param  list<string>  $ufs  vazio = todos os municípios
     * @param  (callable(string, AgreementCheck): void)|null  $progress
     * @return array<string, int> quantos municípios ficaram em cada status
     */
    public function sync(string $environment, array $ufs = [], ?callable $progress = null): array
    {
        $totals = array_fill_keys([AgreementCheck::ACTIVE, AgreementCheck::INACTIVE, AgreementCheck::NOT_FOUND, AgreementCheck::UNKNOWN], 0);
        $batch = [];

        $this->adn->check($environment, Municipalities::codes($ufs), function (string $code, AgreementCheck $check) use ($environment, $progress, &$totals, &$batch) {
            $totals[$check->status]++;
            $progress && $progress($code, $check);

            // Sem resposta do ADN, a última situação conhecida continua valendo.
            if (! $check->isKnown()) {
                return;
            }

            $batch[] = [
                'city_code' => $code,
                'environment' => $environment,
                'status' => $check->status,
                'national_environment' => $check->nationalEnvironment,
                'national_emitter' => $check->nationalEmitter,
                'rfb_standard_issuance' => $check->rfbStandardIssuance,
                'national_assessment' => $check->nationalAssessment,
                'credit_use' => $check->creditUse,
                'message' => $check->message === null ? null : mb_substr($check->message, 0, 255),
                'checked_at' => now(),
            ];

            if (count($batch) >= self::BATCH) {
                $this->agreements->upsert($batch);
                $batch = [];
            }
        });

        $this->agreements->upsert($batch);

        return $totals;
    }
}
