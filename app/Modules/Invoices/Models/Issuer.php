<?php

namespace App\Modules\Invoices\Models;

use App\Platform\Casts\CertificateEncrypted;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Empresa que emite NFS-e em nome do workspace: dados cadastrais, enquadramento tributário
 * e o certificado digital A1 usado para assinar a DPS e autenticar na Sefin Nacional.
 */
class Issuer extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'invoice_issuers';

    /** Produção restrita é o ambiente de testes da Sefin Nacional: notas emitidas lá não têm validade fiscal. */
    public const ENV_RESTRICTED = 'restricted';

    public const ENV_PRODUCTION = 'production';

    public const ENVIRONMENTS = [self::ENV_RESTRICTED, self::ENV_PRODUCTION];

    /** Campo opSimpNac da DPS. */
    public const SIMPLES_NOT_OPTANT = 1;

    public const SIMPLES_MEI = 2;

    public const SIMPLES_ME_EPP = 3;

    /** Campo regApTribSN da DPS, só informado para ME/EPP optante. */
    public const SIMPLES_REGIME_ALL_IN_SN = 1;

    public const SIMPLES_REGIME_ISS_OUTSIDE_SN = 2;

    public const SIMPLES_REGIME_ALL_OUTSIDE_SN = 3;

    /** Campo regEspTrib da DPS. */
    public const SPECIAL_REGIME_NONE = 0;

    public const SPECIAL_REGIME_COOPERATIVE = 1;

    public const SPECIAL_REGIME_ESTIMATE = 2;

    public const SPECIAL_REGIME_MUNICIPAL_MICRO = 3;

    public const SPECIAL_REGIME_NOTARY = 4;

    public const SPECIAL_REGIME_SELF_EMPLOYED = 5;

    public const SPECIAL_REGIME_PROFESSIONAL_SOCIETY = 6;

    public const SIMPLES_NACIONAL_LABELS = [
        self::SIMPLES_NOT_OPTANT => 'Não optante pelo Simples Nacional',
        self::SIMPLES_MEI => 'Microempreendedor Individual (MEI)',
        self::SIMPLES_ME_EPP => 'ME ou EPP optante pelo Simples Nacional',
    ];

    public const SIMPLES_REGIME_LABELS = [
        self::SIMPLES_REGIME_ALL_IN_SN => 'Tributos federais e ISSQN apurados pelo Simples Nacional',
        self::SIMPLES_REGIME_ISS_OUTSIDE_SN => 'Tributos federais pelo Simples Nacional e ISSQN fora, conforme a legislação municipal',
        self::SIMPLES_REGIME_ALL_OUTSIDE_SN => 'Tributos federais e ISSQN fora do Simples Nacional',
    ];

    public const SPECIAL_REGIME_LABELS = [
        self::SPECIAL_REGIME_NONE => 'Nenhum',
        self::SPECIAL_REGIME_COOPERATIVE => 'Ato cooperado (cooperativa)',
        self::SPECIAL_REGIME_ESTIMATE => 'Estimativa',
        self::SPECIAL_REGIME_MUNICIPAL_MICRO => 'Microempresa municipal',
        self::SPECIAL_REGIME_NOTARY => 'Notário ou registrador',
        self::SPECIAL_REGIME_SELF_EMPLOYED => 'Profissional autônomo',
        self::SPECIAL_REGIME_PROFESSIONAL_SOCIETY => 'Sociedade de profissionais',
    ];

    public const ENVIRONMENT_LABELS = [
        self::ENV_RESTRICTED => 'Produção restrita (testes)',
        self::ENV_PRODUCTION => 'Produção',
    ];

    protected $fillable = [
        'tenant_id',
        'legal_name',
        'trade_name',
        'cnpj',
        'municipal_registration',
        'city_code',
        'city_name',
        'state',
        'simples_nacional',
        'simples_regime',
        'special_regime',
        'ibs_cbs_cst',
        'ibs_cbs_class_code',
        'ibs_cbs_operation_code',
        'default_service',
        'default_approximate_taxes',
        'email_taker',
        'danfse_stub',
        'phone',
        'email',
        'environment',
        'dps_series',
    ];

    protected $hidden = [
        'certificate_pfx',
        'certificate_password',
    ];

    protected $casts = [
        'certificate_pfx' => CertificateEncrypted::class,
        'certificate_password' => CertificateEncrypted::class,
        'simples_nacional' => 'integer',
        'simples_regime' => 'integer',
        'special_regime' => 'integer',
        'next_dps_number' => 'integer',
        'default_service' => 'array',
        'default_approximate_taxes' => 'array',
        'email_taker' => 'boolean',
        'danfse_stub' => 'boolean',
        'dfe_cursor' => 'array',
        'dfe_synced_at' => 'datetime',
        'certificate_valid_from' => 'datetime',
        'certificate_valid_until' => 'datetime',
        'certificate_uploaded_at' => 'datetime',
        'certificate_expiry_notice_days' => 'integer',
    ];

    /** O contador já definiu a classificação padrão do IBS/CBS deste emitente. */
    public function hasIbsCbsDefaults(): bool
    {
        return $this->ibs_cbs_cst !== null && $this->ibs_cbs_class_code !== null && $this->ibs_cbs_operation_code !== null;
    }

    public function hasCertificate(): bool
    {
        return $this->certificate_uploaded_at !== null;
    }

    public function certificateIsExpired(): bool
    {
        return $this->certificate_valid_until !== null && $this->certificate_valid_until->isPast();
    }

    public function certificateExpiresWithin(int $days): bool
    {
        return $this->certificate_valid_until !== null
            && ! $this->certificateIsExpired()
            && $this->certificate_valid_until->lte(now()->addDays($days));
    }

    public function isReadyToIssue(): bool
    {
        return $this->hasCertificate() && ! $this->certificateIsExpired();
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    public function isSimplesOptant(): bool
    {
        return in_array($this->simples_nacional, [self::SIMPLES_MEI, self::SIMPLES_ME_EPP], true);
    }
}
