<?php

namespace App\Modules\Invoices\Models;

use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Database\Eloquent\Model;

/**
 * Situação do convênio de um município com o Sistema Nacional NFS-e, num ambiente. Dado público
 * do ADN, igual para todos os workspaces: não usa BelongsToTenant.
 */
class MunicipalAgreement extends Model
{
    protected $table = 'nfse_municipal_agreements';

    protected $fillable = [
        'city_code',
        'environment',
        'status',
        'national_environment',
        'national_emitter',
        'rfb_standard_issuance',
        'national_assessment',
        'credit_use',
        'message',
        'checked_at',
    ];

    protected $casts = [
        'national_environment' => 'boolean',
        'national_emitter' => 'boolean',
        'rfb_standard_issuance' => 'boolean',
        'national_assessment' => 'boolean',
        'credit_use' => 'boolean',
        'checked_at' => 'datetime',
    ];

    /** Dá para emitir pela Sefin Nacional: convênio ativo e emissores públicos nacionais liberados. */
    public function allowsNationalIssuance(): bool
    {
        return $this->status === 'active' && $this->national_emitter === true;
    }

    /** national_emitter, adn_only, inactive ou not_found (as situações da listagem). */
    public function situation(): string
    {
        return match (true) {
            $this->status === 'active' && $this->national_emitter === true => 'national_emitter',
            $this->status === 'active' => 'adn_only',
            default => $this->status,
        };
    }

    public function cityName(): ?string
    {
        return Municipalities::name($this->city_code);
    }

    public function state(): ?string
    {
        return Municipalities::uf($this->city_code);
    }
}
