<?php

namespace App\Modules\Signatures\Contracts;

use App\Modules\Signatures\Models\PlatformCertificate;
use Illuminate\Support\Collection;

interface PlatformCertificateRepositoryContract
{
    /** O certificado enviado pelo admin que está valendo, se houver. */
    public function current(): ?PlatformCertificate;

    /**
     * Envios, trocas e remoções, do mais recente para o mais antigo.
     *
     * @return Collection<int, PlatformCertificate>
     */
    public function history(int $limit): Collection;
}
