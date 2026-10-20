<?php

namespace App\Modules\Mailing\Services\Suppression;

use App\Models\Tenant;
use App\Modules\Mailing\Contracts\EmailSuppressionRepositoryContract;
use App\Modules\Mailing\Contracts\SuppressionListGatewayContract;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailSuppression;
use Illuminate\Support\Str;

/**
 * Lista de bloqueio do workspace. O SES já descarta o envio para quem está na lista da conta; esta
 * aqui mostra ao cliente por que o e-mail não chegou e é a que o relay e a API consultam antes de enviar.
 */
class EmailSuppressionService
{
    public function __construct(
        private EmailSuppressionRepositoryContract $suppressions,
        private SuppressionListGatewayContract $sesList,
    ) {}

    public function suppress(EmailDelivery $delivery, string $reason, ?string $detail = null): void
    {
        if ($delivery->tenant_id === null) {
            return;
        }

        $existing = $this->suppressions->find($delivery->tenant_id, $delivery->recipient);

        // Spam pesa mais que devolução: um bounce posterior não troca o motivo.
        if ($existing?->reason === EmailSuppression::REASON_COMPLAINT && $reason === EmailSuppression::REASON_BOUNCE) {
            return;
        }

        $this->suppressions->put($delivery->tenant_id, $delivery->recipient, [
            'reason' => $reason,
            'detail' => $detail === null ? null : Str::limit($detail, 495),
            'email_delivery_id' => $delivery->getKey(),
        ]);
    }

    public function isSuppressed(Tenant $tenant, string $email): bool
    {
        return $this->suppressions->find($tenant->getKey(), Str::lower(trim($email))) !== null;
    }

    /**
     * Libera um endereço que devolveu (caixa recriada, erro de digitação corrigido). Tira também
     * da lista da conta SES, senão o próximo envio seria descartado do mesmo jeito.
     */
    public function release(EmailSuppression $suppression): void
    {
        if (! $suppression->isRemovable()) {
            throw new \DomainException('Endereço marcado como spam não pode ser liberado pelo workspace.');
        }

        $this->sesList->remove($suppression->email);
        $this->suppressions->delete($suppression);
    }
}
