<?php

namespace App\Modules\Invoices\Models;

use App\Models\User;
use App\Modules\Invoices\Models\Concerns\ArchivesXml;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma NFS-e do workspace, desde a DPS assinada até a nota gerada (ou rejeitada) pela Sefin Nacional.
 */
class Invoice extends Model
{
    use ArchivesXml, BelongsToTenant, HasUlids;

    /** DPS enviada sem resposta conclusiva (falha de rede, Sefin fora do ar). Sincronizar resolve. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** Substituída por outra NFS-e: a Sefin registra o cancelamento por substituição (e105102) sozinha. */
    public const STATUS_REPLACED = 'replaced';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ISSUED, self::STATUS_REJECTED, self::STATUS_CANCELLED, self::STATUS_REPLACED];

    /** Campo tribISSQN da DPS. */
    public const ISS_TAXABLE = 1;

    public const ISS_IMMUNE = 2;

    public const ISS_EXPORT = 3;

    public const ISS_NOT_LEVIED = 4;

    public const ISS_TAXATION_LABELS = [
        self::ISS_TAXABLE => 'Operação tributável',
        self::ISS_IMMUNE => 'Imunidade',
        self::ISS_EXPORT => 'Exportação de serviço',
        self::ISS_NOT_LEVIED => 'Não incidência',
    ];

    /** tpImunidade, obrigatório quando tribISSQN = 2. */
    public const IMMUNITY_LABELS = [
        0 => 'Imunidade (tipo não informado)',
        1 => 'Patrimônio, renda ou serviços uns dos outros (art. 150, VI, a)',
        2 => 'Templos de qualquer culto (art. 150, VI, b)',
        3 => 'Partidos, sindicatos, educação e assistência social sem fins lucrativos (art. 150, VI, c)',
        4 => 'Livros, jornais, periódicos e papel (art. 150, VI, d)',
        5 => 'Fonogramas e videofonogramas musicais brasileiros (art. 150, VI, e)',
    ];

    /** Quem retém o ISS quando iss_withheld (tpRetISSQN 2 ou 3). */
    public const WITHHOLDER_TAKER = 'taker';

    public const WITHHOLDER_INTERMEDIARY = 'intermediary';

    /** cNaoNIF: estrangeiro sem NIF. O código 0 é só para nota de importação. */
    public const NIF_ABSENCE_LABELS = [
        '1' => 'Dispensado do NIF',
        '2' => 'Não exigência do NIF',
    ];

    /** mdPrestacao do grupo de comércio exterior. */
    public const PROVISION_MODE_LABELS = [
        '1' => 'Transfronteiriço',
        '2' => 'Consumo no Brasil',
        '3' => 'Presença comercial no exterior',
        '4' => 'Movimento temporário de pessoas físicas',
    ];

    /** tpRetPisCofins: o que foi retido entre PIS, COFINS e CSLL. */
    public const PIS_COFINS_WITHHOLDING_LABELS = [
        '0' => 'PIS/COFINS/CSLL não retidos',
        '1' => 'PIS/COFINS retidos',
        '2' => 'PIS/COFINS não retidos',
        '3' => 'PIS/COFINS/CSLL retidos',
        '4' => 'PIS/COFINS retidos, CSLL não retido',
        '5' => 'PIS retido, COFINS/CSLL não retidos',
        '6' => 'COFINS retido, PIS/CSLL não retidos',
        '7' => 'PIS não retido, COFINS/CSLL retidos',
        '8' => 'PIS/COFINS não retidos, CSLL retido',
        '9' => 'COFINS não retido, PIS/CSLL retidos',
    ];

    /** CST de PIS/COFINS aceitos no leiaute (TSTipoCST). */
    public const PIS_COFINS_CST = [
        '00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '49', '50', '51', '52', '53', '54', '55', '56',
        '60', '61', '62', '63', '64', '65', '66', '67', '70', '71', '72', '73', '74', '75', '98', '99',
    ];

    /** tpOper do IBS/CBS: operações com entes governamentais e outros serviços sobre bens imóveis. */
    public const IBS_CBS_OPERATION_LABELS = [
        '1' => 'Fornecimento com pagamento posterior',
        '2' => 'Recebimento do pagamento com fornecimento já realizado',
        '3' => 'Fornecimento com pagamento já realizado',
        '4' => 'Recebimento do pagamento com fornecimento posterior',
        '5' => 'Fornecimento e recebimento do pagamento concomitantes',
    ];

    public const GOVERNMENT_ENTITY_LABELS = ['1' => 'União', '2' => 'Estado', '3' => 'Distrito Federal', '4' => 'Município'];

    /** tpReeRepRes. */
    public const REIMBURSEMENT_TYPE_LABELS = [
        '01' => 'Repasse de remuneração por intermediação de imóveis a outros corretores',
        '02' => 'Repasse a fornecedor em fornecimento intermediado por agência de turismo',
        '03' => 'Reembolso a agência de propaganda por valores pagos a terceiros (produção)',
        '04' => 'Reembolso a agência de propaganda por valores pagos a terceiros (veiculação)',
        '99' => 'Outros reembolsos ou ressarcimentos por conta e ordem de terceiro',
    ];

    /** tipoChaveDFe. */
    public const DFE_TYPE_LABELS = ['1' => 'NFS-e', '2' => 'NF-e', '3' => 'CT-e', '9' => 'Outro'];

    /** tpSusp. */
    public const ISS_SUSPENSION_LABELS = [
        '1' => 'Decisão judicial',
        '2' => 'Processo administrativo',
    ];

    /** Documento que comprova a dedução (choice de TCDocDedRed). */
    public const DEDUCTION_DOC_NFSE = 'nfse';

    public const DEDUCTION_DOC_NFE = 'nfe';

    public const DEDUCTION_DOC_MUNICIPAL_NFSE = 'municipal_nfse';

    public const DEDUCTION_DOC_PAPER = 'paper_invoice';

    public const DEDUCTION_DOC_FISCAL = 'fiscal_document';

    public const DEDUCTION_DOC_OTHER = 'other_document';

    public const DEDUCTION_DOCUMENT_LABELS = [
        self::DEDUCTION_DOC_NFSE => 'NFS-e nacional (chave de acesso)',
        self::DEDUCTION_DOC_NFE => 'NF-e (chave de acesso)',
        self::DEDUCTION_DOC_MUNICIPAL_NFSE => 'NFS-e do padrão municipal anterior',
        self::DEDUCTION_DOC_PAPER => 'NF ou NFS em papel',
        self::DEDUCTION_DOC_FISCAL => 'Outro documento fiscal',
        self::DEDUCTION_DOC_OTHER => 'Documento não fiscal',
    ];

    /** tpDedRed. */
    public const DEDUCTION_REASON_OTHER = '99';

    public const DEDUCTION_REASON_LABELS = [
        '1' => 'Alimentação e bebidas/frigobar',
        '2' => 'Materiais',
        '3' => 'Produção externa',
        '4' => 'Reembolso de despesas',
        '5' => 'Repasse consorciado',
        '6' => 'Repasse plano de saúde',
        '7' => 'Serviços',
        '8' => 'Subempreitada de mão de obra',
        '9' => 'Profissional parceiro',
        self::DEDUCTION_REASON_OTHER => 'Outras deduções',
    ];

    /** Campo cMotivo do evento de cancelamento (e101101). */
    public const CANCEL_ISSUE_ERROR = 1;

    public const CANCEL_SERVICE_NOT_PROVIDED = 2;

    public const CANCEL_OTHER = 9;

    public const CANCEL_REASON_LABELS = [
        self::CANCEL_ISSUE_ERROR => 'Erro na emissão',
        self::CANCEL_SERVICE_NOT_PROVIDED => 'Serviço não prestado',
        self::CANCEL_OTHER => 'Outros',
    ];

    /** Campo cMotivo do grupo subst da DPS. */
    public const SUBSTITUTION_OTHER = '99';

    public const SUBSTITUTION_REASON_LABELS = [
        '01' => 'Desenquadramento do Simples Nacional',
        '02' => 'Enquadramento no Simples Nacional',
        '03' => 'Inclusão retroativa de imunidade/isenção',
        '04' => 'Exclusão retroativa de imunidade/isenção',
        '05' => 'Rejeição da NFS-e pelo tomador ou intermediário',
        self::SUBSTITUTION_OTHER => 'Outros',
    ];

    protected $fillable = [
        'tenant_id',
        'issuer_id',
        'creator_id',
        'external_id',
        'competence_date',
        'taker_document',
        'taker_name',
        'taker',
        'intermediary',
        'service',
        'amount',
        'intermediary_received',
        'unconditional_discount',
        'conditional_discount',
        'deduction_amount',
        'deduction_percent',
        'deduction_documents',
        'iss_rate',
        'iss_withheld',
        'iss_withholder',
        'iss_taxation',
        'immunity_type',
        'federal_taxes',
        'municipal_benefit',
        'iss_suspension',
        'approximate_taxes',
        'ibs_cbs',
        'notify_taker',
    ];

    protected $hidden = [
        'dps_xml_path',
        'nfse_xml_path',
        'cancellation_xml_path',
    ];

    protected $casts = [
        'taker' => 'array',
        'intermediary' => 'array',
        'service' => 'array',
        'rejection' => 'array',
        'approximate_taxes' => 'array',
        'ibs_cbs' => 'array',
        'federal_taxes' => 'array',
        'municipal_benefit' => 'array',
        'iss_suspension' => 'array',
        'notify_taker' => 'boolean',
        'is_overage' => 'boolean',
        'cancellation_review' => 'array',
        'taker_manifestation' => 'array',
        'blocked_at' => 'datetime',
        'taker_emailed_at' => 'datetime',
        'deduction_documents' => 'array',
        'amount' => 'decimal:2',
        'intermediary_received' => 'decimal:2',
        'unconditional_discount' => 'decimal:2',
        'conditional_discount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'deduction_percent' => 'decimal:2',
        'immunity_type' => 'integer',
        'iss_rate' => 'decimal:2',
        'iss_withheld' => 'boolean',
        'iss_taxation' => 'integer',
        'dps_number' => 'integer',
        'cancel_reason_code' => 'integer',
        'competence_date' => 'date',
        'emitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'replaced_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** Nota que esta substitui. */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_invoice_id');
    }

    /** Substitutas desta nota, inclusive tentativas rejeitadas. */
    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_invoice_id');
    }

    /** Substituta que de fato gerou NFS-e (as rejeitadas ficam só no histórico). */
    public function replacedBy(): ?self
    {
        return $this->replacements->first(fn (self $r) => $r->isIssued() || $r->isCancelled() || $r->isReplaced());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReplaced(): bool
    {
        return $this->status === self::STATUS_REPLACED;
    }

    public function isReplacement(): bool
    {
        return $this->replaces_invoice_id !== null;
    }

    public function hasNfseXml(): bool
    {
        return $this->hasArchivedXml('nfse_xml');
    }

    protected function archivedXml(): array
    {
        return [
            'dps_xml' => 'dps_xml_path',
            'nfse_xml' => 'nfse_xml_path',
            'cancellation_xml' => 'cancellation_xml_path',
        ];
    }
}
