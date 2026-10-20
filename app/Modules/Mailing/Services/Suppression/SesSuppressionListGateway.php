<?php

namespace App\Modules\Mailing\Services\Suppression;

use App\Modules\Mailing\Contracts\SuppressionListGatewayContract;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;

class SesSuppressionListGateway implements SuppressionListGatewayContract
{
    public function __construct(
        private SesV2Client $ses,
    ) {}

    public function remove(string $email): void
    {
        try {
            $this->ses->deleteSuppressedDestination(['EmailAddress' => $email]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'NotFoundException') {
                throw $e;
            }
        }
    }
}
