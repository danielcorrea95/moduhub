<?php

namespace App\Modules\Mailing\Services\Identity;

use App\Modules\Mailing\Contracts\EmailIdentityGatewayContract;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\SesV2\SesV2Client;

class SesEmailIdentityGateway implements EmailIdentityGatewayContract
{
    public function __construct(
        private SesV2Client $ses,
        private ?string $configurationSet = null,
    ) {}

    public function createDomainIdentity(string $domain, string $mailFromDomain): IdentityStatus
    {
        try {
            $this->ses->createEmailIdentity(array_filter([
                'EmailIdentity' => $domain,
                'DkimSigningAttributes' => ['NextSigningKeyLength' => 'RSA_2048_BIT'],
                // Assim os eventos do domínio do cliente também chegam em /webhooks/ses.
                'ConfigurationSetName' => $this->configurationSet,
            ]));
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'AlreadyExistsException') {
                throw $e;
            }
        }

        $this->ses->putEmailIdentityMailFromAttributes([
            'EmailIdentity' => $domain,
            'MailFromDomain' => $mailFromDomain,
            // Sem o MX do subdomínio, o SES usa o MAIL FROM dele em vez de recusar o envio.
            'BehaviorOnMxFailure' => 'USE_DEFAULT_VALUE',
        ]);

        return $this->status($domain);
    }

    public function status(string $domain): IdentityStatus
    {
        return $this->toStatus($this->ses->getEmailIdentity(['EmailIdentity' => $domain]));
    }

    public function deleteDomainIdentity(string $domain): void
    {
        try {
            $this->ses->deleteEmailIdentity(['EmailIdentity' => $domain]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'NotFoundException') {
                throw $e;
            }
        }
    }

    private function toStatus(Result $result): IdentityStatus
    {
        return new IdentityStatus(
            verifiedForSending: (bool) $result->get('VerifiedForSendingStatus'),
            dkimTokens: array_values($result->search('DkimAttributes.Tokens') ?? []),
            dkimStatus: $result->search('DkimAttributes.Status'),
            mailFromStatus: $result->search('MailFromAttributes.MailFromDomainStatus'),
        );
    }
}
