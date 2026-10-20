<?php

namespace App\Modules\Mailing\Services\Identity;

final readonly class IdentityStatus
{
    public const SUCCESS = 'SUCCESS';

    public const PENDING = 'PENDING';

    public const FAILED = 'FAILED';

    public const TEMPORARY_FAILURE = 'TEMPORARY_FAILURE';

    /**
     * @param  list<string>  $dkimTokens
     */
    public function __construct(
        public bool $verifiedForSending,
        public array $dkimTokens,
        public ?string $dkimStatus,
        public ?string $mailFromStatus,
    ) {}
}
