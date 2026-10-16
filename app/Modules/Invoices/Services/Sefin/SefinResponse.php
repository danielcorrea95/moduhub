<?php

namespace App\Modules\Invoices\Services\Sefin;

final readonly class SefinResponse
{
    /**
     * @param  list<array{code: string|null, message: string, complement: string|null}>  $errors
     * @param  list<array{code: string|null, message: string, complement: string|null}>  $alerts
     */
    public function __construct(
        public int $status,
        public bool $successful,
        public ?string $accessKey = null,
        /** XML devolvido (NFS-e ou evento), já descompactado. */
        public ?string $xml = null,
        public array $errors = [],
        public array $alerts = [],
    ) {}

    public function errorSummary(): string
    {
        return collect($this->errors)
            ->map(fn (array $e) => trim(($e['code'] ? $e['code'].' - ' : '').$e['message'].($e['complement'] ? ' ('.$e['complement'].')' : '')))
            ->implode('; ');
    }
}
