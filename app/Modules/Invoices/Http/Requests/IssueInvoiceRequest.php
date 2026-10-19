<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\IbgeCityCode;
use App\Modules\Invoices\Rules\IbsCbsCodes;
use App\Modules\Invoices\Rules\ValidTaxDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dados de uma NFS-e, iguais no painel e na API. Os limites de tamanho seguem o XSD v1.01.
 */
class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', Invoice::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $service = (array) $this->input('service', []);
        $values = (array) $this->input('values', []);

        // Código de tributação nacional costuma vir com máscara (01.07.01).
        $service['national_tax_code'] = $this->digits($service['national_tax_code'] ?? null);
        $service['municipal_tax_code'] = $this->digits($service['municipal_tax_code'] ?? null);
        $service['nbs_code'] = $this->digits($service['nbs_code'] ?? null);
        $service['city_code'] = $this->digits($service['city_code'] ?? null);
        $service['result_country'] = $this->country($service['result_country'] ?? null);
        foreach (['construction', 'event', 'additional_info'] as $group) {
            $service[$group] = $this->prune($service[$group] ?? null);
        }
        foreach (['construction', 'event'] as $group) {
            if (isset($service[$group]['address']['postal_code']) && ! $this->isForeignAddress($service[$group]['address'])) {
                $service[$group]['address']['postal_code'] = $this->digits($service[$group]['address']['postal_code']);
            }
        }
        if (isset($service['foreign_trade']) && is_array($service['foreign_trade'])) {
            $trade = $this->blankToNull($service['foreign_trade']);
            $service['foreign_trade'] = array_filter($trade, fn ($v) => $v !== null) === [] ? null : $trade;
        }

        $values['iss_rate'] = ($values['iss_rate'] ?? '') === '' ? null : $values['iss_rate'];
        $values['immunity_type'] = ($values['immunity_type'] ?? '') === '' ? null : $values['immunity_type'];
        foreach (['approximate_taxes', 'discounts', 'deduction'] as $group) {
            if (isset($values[$group]) && is_array($values[$group])) {
                $values[$group] = $this->blankToNull($values[$group]);
            }
        }
        // Tributos aproximados todos em branco = não informados: vale o padrão do emitente.
        if (is_array($values['approximate_taxes'] ?? null) && array_filter($values['approximate_taxes'], fn ($v) => $v !== null) === []) {
            $values['approximate_taxes'] = null;
        }
        if (isset($values['deduction']['documents']) && is_array($values['deduction']['documents'])) {
            $values['deduction']['documents'] = array_map(
                fn ($doc) => is_array($doc) ? $this->normalizeDeductionDocument($doc) : $doc,
                array_values($values['deduction']['documents']),
            );
            if ($values['deduction']['documents'] === []) {
                $values['deduction']['documents'] = null;
            }
        }
        $values['federal_taxes'] = $this->normalizeFederalTaxes($values['federal_taxes'] ?? null);
        $values['intermediary_received'] = ($values['intermediary_received'] ?? '') === '' ? null : $values['intermediary_received'];
        foreach (['municipal_benefit', 'iss_suspension'] as $group) {
            if (isset($values[$group]) && is_array($values[$group])) {
                $clean = $this->blankToNull($values[$group]);
                foreach (['number', 'process_number'] as $digits) {
                    if (array_key_exists($digits, $clean)) {
                        $clean[$digits] = $this->digits($clean[$digits]);
                    }
                }
                $values[$group] = array_filter($clean, fn ($v) => $v !== null) === [] ? null : $clean;
            }
        }
        if (isset($values['ibs_cbs']) && is_array($values['ibs_cbs'])) {
            $codes = $values['ibs_cbs'];
            foreach (['cst', 'class_code', 'operation_code'] as $key) {
                $codes[$key] = $this->digits($codes[$key] ?? null);
            }
            $codes['final_consumer'] = ($codes['final_consumer'] ?? '') === '' ? null : $codes['final_consumer'];
            foreach (['operation_type', 'government_entity', 'referenced_invoices', 'property', 'reimbursements'] as $extra) {
                $codes[$extra] = $this->prune($codes[$extra] ?? null);
            }
            if (isset($codes['referenced_invoices'])) {
                $codes['referenced_invoices'] = array_map(fn ($key) => $this->digits($key) ?? '', (array) $codes['referenced_invoices']);
            }
            if (isset($codes['property']['address']['postal_code']) && ! $this->isForeignAddress($codes['property']['address'])) {
                $codes['property']['address']['postal_code'] = $this->digits($codes['property']['address']['postal_code']);
            }
            foreach ((array) ($codes['reimbursements'] ?? []) as $i => $item) {
                if (is_array($item)) {
                    foreach (['type'] as $code) {
                        if (isset($item[$code]) && ctype_digit((string) $item[$code])) {
                            $item[$code] = str_pad((string) $item[$code], 2, '0', STR_PAD_LEFT);
                        }
                    }
                    if (isset($item['supplier']['document'])) {
                        $item['supplier']['document'] = $this->digits($item['supplier']['document'], alphanumeric: true);
                    }
                    $codes['reimbursements'][$i] = $item;
                }
            }
            $recipient = $codes['recipient'] ?? null;
            $recipient = is_array($recipient) ? $this->normalizePerson($recipient) : null;
            $codes['recipient'] = $recipient !== null && $this->hasIdentity($recipient) ? $recipient : null;
            $values['ibs_cbs'] = array_filter($codes, fn ($v) => $v !== null) === [] ? null : $codes;
        }

        $intermediary = $this->input('intermediary');
        $intermediary = is_array($intermediary) ? $this->normalizePerson($intermediary) : null;

        $this->merge([
            'taker' => $this->normalizePerson((array) $this->input('taker', [])),
            // O painel manda o grupo do intermediário vazio quando não há intermediário.
            'intermediary' => $intermediary !== null && $this->hasIdentity($intermediary) ? $intermediary : null,
            'service' => $service,
            'values' => $values,
            'external_id' => $this->filled('external_id') ? trim((string) $this->input('external_id')) : null,
            'competence_date' => $this->filled('competence_date') ? $this->input('competence_date') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'external_id' => 'nullable|string|max:255',
            // Sem o campo, vale a configuração do emitente.
            'notify_taker' => 'nullable|boolean',
            'competence_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',

            ...$this->personRules('taker', required: true),
            ...($this->input('values.ibs_cbs.recipient') === null
                ? ['values.ibs_cbs.recipient' => 'nullable']
                : $this->personRules('values.ibs_cbs.recipient', required: false)),
            ...($this->input('intermediary') === null
                ? ['intermediary' => 'nullable']
                : $this->personRules('intermediary', required: false)),

            'service' => 'present|array:national_tax_code,municipal_tax_code,nbs_code,description,city_code,result_country,foreign_trade,construction,event,additional_info',
            // Sem código, vale o serviço padrão do emitente (conferido no InvoiceService).
            'service.national_tax_code' => 'nullable|digits:6',
            'service.municipal_tax_code' => 'nullable|digits:3',
            'service.nbs_code' => 'nullable|digits:9',
            'service.description' => 'nullable|string|max:2000',
            'service.city_code' => ['nullable', 'digits:7', new IbgeCityCode],
            // cPaisResult: onde o resultado do serviço exportado se verificou.
            'service.result_country' => 'nullable|required_if:values.iss_taxation,'.Invoice::ISS_EXPORT.'|alpha|size:2|not_in:BR',
            'service.foreign_trade' => 'nullable|required_if:values.iss_taxation,'.Invoice::ISS_EXPORT.'|array:provision_mode,relationship,currency,foreign_amount,provider_support,taker_support,temporary_goods,send_to_mdic',
            'service.foreign_trade.provision_mode' => 'required_with:service.foreign_trade|nullable|in:'.implode(',', array_keys(Invoice::PROVISION_MODE_LABELS)),
            'service.foreign_trade.relationship' => 'nullable|in:0,1,2,3,4,5,6,9',
            'service.foreign_trade.currency' => 'required_with:service.foreign_trade|nullable|digits:3',
            'service.foreign_trade.foreign_amount' => 'required_with:service.foreign_trade|nullable|numeric|min:0.01|max:999999999999.99',
            'service.foreign_trade.provider_support' => 'nullable|digits:2',
            'service.foreign_trade.taker_support' => 'nullable|digits:2',
            'service.foreign_trade.temporary_goods' => 'nullable|in:0,1,2,3',
            'service.foreign_trade.send_to_mdic' => 'nullable|boolean',

            // Obra de construção civil: identificada pelo código da obra, pelo CIB ou pelo endereço.
            ...$this->whenPresent('service.construction', [
                'service.construction' => 'array:registration,work_code,cib,address',
                'service.construction.registration' => 'nullable|string|max:30',
                'service.construction.work_code' => 'nullable|required_without_all:service.construction.cib,service.construction.address|prohibits:service.construction.cib,service.construction.address|string|max:30',
                'service.construction.cib' => 'nullable|prohibits:service.construction.address|alpha_num:ascii|size:8',
                ...$this->simpleAddressRules('service.construction.address'),
            ]),

            // Evento: nome, período e o identificador do evento ou o endereço.
            ...$this->whenPresent('service.event', [
                'service.event' => 'array:name,starts_on,ends_on,event_id,address',
                'service.event.name' => 'required|string|max:255',
                'service.event.starts_on' => 'required|date_format:Y-m-d',
                'service.event.ends_on' => 'required|date_format:Y-m-d|after_or_equal:service.event.starts_on',
                'service.event.event_id' => 'nullable|required_without:service.event.address|prohibits:service.event.address|string|max:30',
                ...$this->simpleAddressRules('service.event.address'),
            ]),

            'service.additional_info' => 'nullable|array:technical_document,reference,order,order_items,notes',
            'service.additional_info.technical_document' => 'nullable|string|max:40',
            'service.additional_info.reference' => 'nullable|string|max:255',
            'service.additional_info.order' => 'nullable|string|max:60',
            'service.additional_info.order_items' => 'nullable|array|max:99',
            'service.additional_info.order_items.*' => 'string|max:60',
            'service.additional_info.notes' => 'nullable|string|max:2000',

            'values' => 'required|array:amount,intermediary_received,iss_rate,iss_withheld,iss_withholder,iss_taxation,immunity_type,discounts,deduction,approximate_taxes,ibs_cbs,federal_taxes,municipal_benefit,iss_suspension',
            'values.amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'values.intermediary_received' => 'nullable|numeric|min:0.01|max:999999999999.99',
            'values.iss_rate' => 'nullable|numeric|min:0|max:5',
            'values.iss_withheld' => 'nullable|boolean',
            'values.iss_withholder' => 'nullable|in:'.Invoice::WITHHOLDER_TAKER.','.Invoice::WITHHOLDER_INTERMEDIARY,
            'values.iss_taxation' => 'nullable|integer|in:'.implode(',', array_keys(Invoice::ISS_TAXATION_LABELS)),
            'values.immunity_type' => 'nullable|required_if:values.iss_taxation,'.Invoice::ISS_IMMUNE.'|integer|in:'.implode(',', array_keys(Invoice::IMMUNITY_LABELS)),
            'values.discounts' => 'nullable|array:unconditional,conditional',
            'values.discounts.unconditional' => 'nullable|numeric|min:0|lt:values.amount',
            'values.discounts.conditional' => 'nullable|numeric|min:0|lt:values.amount',
            'values.deduction' => 'nullable|array:percent,amount,documents',
            // Uma forma só: percentual, valor ou documentos (choice de TCInfoDedRed).
            'values.deduction.percent' => 'nullable|prohibits:values.deduction.amount,values.deduction.documents|numeric|min:0.01|max:100',
            'values.deduction.amount' => 'nullable|prohibits:values.deduction.documents|numeric|min:0.01|lt:values.amount',
            'values.deduction.documents' => 'nullable|array|min:1|max:1000',
            ...$this->deductionDocumentRules(),
            'values.approximate_taxes' => 'nullable|array:federal,state,municipal',
            'values.approximate_taxes.federal' => 'nullable|numeric|min:0|max:100',
            'values.approximate_taxes.state' => 'nullable|numeric|min:0|max:100',
            'values.approximate_taxes.municipal' => 'nullable|numeric|min:0|max:100',
            // Tributos federais (tribFed). Cada retenção aceita o valor ou a alíquota sobre o valor do serviço.
            'values.federal_taxes' => 'nullable|array:pis_cofins,irrf,csll,inss',
            'values.federal_taxes.pis_cofins' => 'nullable|array:cst,base,pis_rate,cofins_rate,pis_amount,cofins_amount,withholding',
            'values.federal_taxes.pis_cofins.cst' => 'required_with:values.federal_taxes.pis_cofins|in:'.implode(',', Invoice::PIS_COFINS_CST),
            'values.federal_taxes.pis_cofins.base' => 'nullable|numeric|min:0|max:999999999999.99',
            'values.federal_taxes.pis_cofins.pis_rate' => 'nullable|numeric|min:0|max:99.99',
            'values.federal_taxes.pis_cofins.cofins_rate' => 'nullable|numeric|min:0|max:99.99',
            'values.federal_taxes.pis_cofins.pis_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'values.federal_taxes.pis_cofins.cofins_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'values.federal_taxes.pis_cofins.withholding' => 'nullable|in:'.implode(',', array_keys(Invoice::PIS_COFINS_WITHHOLDING_LABELS)),
            ...collect(['irrf', 'csll', 'inss'])->flatMap(fn (string $tax) => [
                "values.federal_taxes.{$tax}" => 'nullable|array:rate,amount',
                "values.federal_taxes.{$tax}.rate" => "nullable|prohibits:values.federal_taxes.{$tax}.amount|numeric|min:0|max:100",
                "values.federal_taxes.{$tax}.amount" => 'nullable|numeric|min:0|lt:values.amount',
            ])->all(),
            // Benefício municipal (BM): número de 14 dígitos dado pela prefeitura; a redução é opcional.
            'values.municipal_benefit' => 'nullable|array:number,reduction_amount,reduction_percent',
            'values.municipal_benefit.number' => 'required_with:values.municipal_benefit|digits:14',
            'values.municipal_benefit.reduction_amount' => 'nullable|prohibits:values.municipal_benefit.reduction_percent|numeric|min:0.01|lt:values.amount',
            'values.municipal_benefit.reduction_percent' => 'nullable|numeric|min:0.01|max:100',
            'values.iss_suspension' => 'nullable|array:type,process_number',
            'values.iss_suspension.type' => 'required_with:values.iss_suspension|in:'.implode(',', array_keys(Invoice::ISS_SUSPENSION_LABELS)),
            'values.iss_suspension.process_number' => 'required_with:values.iss_suspension|digits_between:1,30',
            // Sem o grupo, valem os códigos padrão do emitente.
            'values.ibs_cbs' => 'nullable|array:cst,class_code,operation_code,final_consumer,operation_type,government_entity,referenced_invoices,recipient,property,reimbursements',
            'values.ibs_cbs.operation_type' => 'nullable|in:'.implode(',', array_keys(Invoice::IBS_CBS_OPERATION_LABELS)),
            'values.ibs_cbs.government_entity' => 'nullable|in:'.implode(',', array_keys(Invoice::GOVERNMENT_ENTITY_LABELS)),
            'values.ibs_cbs.referenced_invoices' => 'nullable|array|max:99',
            'values.ibs_cbs.referenced_invoices.*' => 'digits:50',
            ...$this->whenPresent('values.ibs_cbs.property', [
                'values.ibs_cbs.property' => 'array:registration,cib,address',
                'values.ibs_cbs.property.registration' => 'nullable|string|max:30',
                'values.ibs_cbs.property.cib' => 'nullable|required_without:values.ibs_cbs.property.address|prohibits:values.ibs_cbs.property.address|alpha_num:ascii|size:8',
                ...$this->simpleAddressRules('values.ibs_cbs.property.address'),
            ]),
            'values.ibs_cbs.reimbursements' => 'nullable|array|max:1000',
            ...$this->reimbursementRules(),
            ...IbsCbsCodes::rules('values.ibs_cbs.cst', 'values.ibs_cbs.class_code', 'values.ibs_cbs.operation_code', $this->input('values.ibs_cbs.cst')),
            'values.ibs_cbs.final_consumer' => 'nullable|boolean',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $values = (array) $this->input('values', []);
            if (($values['iss_withholder'] ?? null) === Invoice::WITHHOLDER_INTERMEDIARY && $this->input('intermediary') === null) {
                $validator->errors()->add('values.iss_withholder', 'Informe o intermediário para reter o ISS por ele.');
            }
            if (($values['intermediary_received'] ?? null) !== null && $this->input('intermediary') === null) {
                $validator->errors()->add('values.intermediary_received', 'O valor recebido pelo intermediário exige o grupo do intermediário.');
            }

            $documents = $values['deduction']['documents'] ?? null;
            if (is_array($documents) && is_numeric($values['amount'] ?? null)) {
                $total = array_sum(array_map(fn ($d) => is_array($d) && is_numeric($d['amount'] ?? null) ? (float) $d['amount'] : 0, $documents));
                if ($total >= (float) $values['amount']) {
                    $validator->errors()->add('values.deduction.documents', 'A soma das deduções precisa ser menor que o valor do serviço.');
                }
            }
        }];
    }

    /**
     * Regras por documento, com as chaves concretas: cada tipo de documento exige campos diferentes,
     * e o fornecedor tem as mesmas regras de pessoa do tomador.
     */
    protected function deductionDocumentRules(): array
    {
        $documents = $this->input('values.deduction.documents');
        if (! is_array($documents)) {
            return [];
        }

        $rules = [];
        foreach (array_keys($documents) as $i) {
            $key = "values.deduction.documents.{$i}";
            $kind = $this->input("{$key}.kind");
            $is = fn (string ...$kinds) => in_array($kind, $kinds, true);

            $rules += [
                $key => 'array:kind,access_key,city_code,number,verification_code,model,series,reason,reason_description,issued_on,deductible_amount,amount,supplier',
                "{$key}.kind" => 'required|in:'.implode(',', array_keys(Invoice::DEDUCTION_DOCUMENT_LABELS)),
                "{$key}.access_key" => match (true) {
                    $is(Invoice::DEDUCTION_DOC_NFSE) => 'required|digits:50',
                    $is(Invoice::DEDUCTION_DOC_NFE) => 'required|digits:44',
                    default => 'prohibited',
                },
                "{$key}.city_code" => $is(Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE) ? ['required', 'digits:7', new IbgeCityCode] : 'prohibited',
                "{$key}.number" => match (true) {
                    $is(Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE) => 'required|digits_between:1,15',
                    $is(Invoice::DEDUCTION_DOC_PAPER) => 'required|digits_between:1,7',
                    $is(Invoice::DEDUCTION_DOC_FISCAL, Invoice::DEDUCTION_DOC_OTHER) => 'required|string|max:255',
                    default => 'prohibited',
                },
                "{$key}.verification_code" => $is(Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE) ? 'required|alpha_num:ascii|max:9' : 'prohibited',
                "{$key}.model" => $is(Invoice::DEDUCTION_DOC_PAPER) ? 'required|digits_between:1,15' : 'prohibited',
                "{$key}.series" => $is(Invoice::DEDUCTION_DOC_PAPER) ? 'required|alpha_num:ascii|max:15' : 'prohibited',
                "{$key}.reason" => 'required|in:'.implode(',', array_keys(Invoice::DEDUCTION_REASON_LABELS)),
                "{$key}.reason_description" => "nullable|required_if:{$key}.reason,".Invoice::DEDUCTION_REASON_OTHER.'|string|max:150',
                "{$key}.issued_on" => 'required|date_format:Y-m-d|before_or_equal:today',
                "{$key}.deductible_amount" => 'required|numeric|min:0.01|max:999999999999.99',
                "{$key}.amount" => "required|numeric|min:0.01|lte:{$key}.deductible_amount",
            ];

            if ($this->input("{$key}.supplier") === null) {
                $rules["{$key}.supplier"] = 'nullable';
            } else {
                $rules += $this->personRules("{$key}.supplier", required: false);
            }
        }

        return $rules;
    }

    /**
     * Regras de um grupo opcional só valem quando ele veio: "obrigatório sem o outro" dispararia mesmo com o grupo ausente.
     *
     * @param  array<string, mixed>  $rules
     */
    protected function whenPresent(string $key, array $rules): array
    {
        return $this->input($key) === null ? [$key => 'nullable'] : $rules;
    }

    /**
     * Endereço simplificado (TCEnderObraEvento / TCEnderecoSimples): CEP, ou código postal + cidade +
     * estado no exterior, sem código de município.
     */
    protected function simpleAddressRules(string $key): array
    {
        $country = $this->input("{$key}.country");
        $foreign = is_string($country) && $country !== '' && strtoupper($country) !== 'BR';

        return [
            $key => 'nullable|array:country,postal_code,street,number,complement,district,city,state',
            "{$key}.country" => 'nullable|alpha|size:2',
            "{$key}.postal_code" => ["required_with:{$key}", 'nullable', $foreign ? 'max:11' : 'digits:8'],
            "{$key}.street" => "required_with:{$key}|nullable|string|max:255",
            "{$key}.number" => "required_with:{$key}|nullable|string|max:60",
            "{$key}.complement" => 'nullable|string|max:156',
            "{$key}.district" => "required_with:{$key}|nullable|string|max:60",
            "{$key}.city" => $foreign ? "required_with:{$key}|string|max:60" : 'prohibited',
            "{$key}.state" => $foreign ? "required_with:{$key}|string|max:60" : 'prohibited',
        ];
    }

    /** Documentos de reembolso, repasse ou ressarcimento (gReeRepRes), com regras por tipo de documento. */
    protected function reimbursementRules(): array
    {
        $items = $this->input('values.ibs_cbs.reimbursements');
        if (! is_array($items)) {
            return [];
        }

        $rules = [];
        foreach (array_keys($items) as $i) {
            $key = "values.ibs_cbs.reimbursements.{$i}";
            $kind = $this->input("{$key}.kind");

            $rules += [
                $key => 'array:kind,dfe_type,dfe_type_description,access_key,city_code,number,description,supplier,issued_on,competence_on,type,type_description,amount',
                "{$key}.kind" => 'required|in:dfe,fiscal_document,other_document',
                "{$key}.dfe_type" => $kind === 'dfe' ? 'required|in:'.implode(',', array_keys(Invoice::DFE_TYPE_LABELS)) : 'prohibited',
                "{$key}.dfe_type_description" => $kind === 'dfe' ? "nullable|required_if:{$key}.dfe_type,9|string|max:255" : 'prohibited',
                "{$key}.access_key" => $kind === 'dfe' ? 'required|string|max:50' : 'prohibited',
                "{$key}.city_code" => $kind === 'fiscal_document' ? ['required', 'digits:7', new IbgeCityCode] : 'prohibited',
                "{$key}.number" => $kind === 'dfe' ? 'prohibited' : 'required|string|max:255',
                "{$key}.description" => $kind === 'dfe' ? 'prohibited' : 'required|string|max:255',
                "{$key}.supplier" => 'nullable|array:document,nif,nif_absence_reason,name',
                "{$key}.supplier.document" => ['nullable', 'string', new ValidTaxDocument],
                "{$key}.supplier.nif" => 'nullable|string|max:40',
                "{$key}.supplier.nif_absence_reason" => 'nullable|in:'.implode(',', array_keys(Invoice::NIF_ABSENCE_LABELS)),
                "{$key}.supplier.name" => "required_with:{$key}.supplier|string|max:150",
                "{$key}.issued_on" => 'required|date_format:Y-m-d',
                "{$key}.competence_on" => 'required|date_format:Y-m-d',
                "{$key}.type" => 'required|in:'.implode(',', array_keys(Invoice::REIMBURSEMENT_TYPE_LABELS)),
                "{$key}.type_description" => "nullable|required_if:{$key}.type,99|string|max:150",
                "{$key}.amount" => 'required|numeric|min:0.01|lt:values.amount',
            ];
        }

        return $rules;
    }

    /** Tira campos vazios recursivamente; um grupo que fica vazio vira null. */
    protected function prune(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }
        if (! is_array($value)) {
            return $value;
        }

        $list = array_is_list($value);
        $value = array_filter(array_map(fn ($v) => $this->prune($v), $value), fn ($v) => $v !== null && $v !== []);

        return $value === [] ? null : ($list ? array_values($value) : $value);
    }

    /** Grupos vazios (o painel manda todos os campos) somem; o que sobra segue para validação. */
    protected function normalizeFederalTaxes(mixed $taxes): ?array
    {
        if (! is_array($taxes)) {
            return null;
        }

        foreach (['pis_cofins', 'irrf', 'csll', 'inss'] as $group) {
            if (! isset($taxes[$group]) || ! is_array($taxes[$group])) {
                continue;
            }
            $clean = $this->blankToNull($taxes[$group]);
            if ($group === 'pis_cofins' && isset($clean['cst']) && ctype_digit((string) $clean['cst'])) {
                $clean['cst'] = str_pad((string) $clean['cst'], 2, '0', STR_PAD_LEFT);
            }
            $taxes[$group] = array_filter($clean, fn ($v) => $v !== null) === [] ? null : $clean;
        }

        return array_filter($taxes, fn ($v) => $v !== null) === [] ? null : $taxes;
    }

    /** @param  array<string, mixed>  $doc */
    protected function normalizeDeductionDocument(array $doc): array
    {
        $doc = $this->blankToNull($doc);
        foreach (['access_key', 'city_code', 'model'] as $digits) {
            $doc[$digits] = $this->digits($doc[$digits] ?? null);
        }
        $kind = $doc['kind'] ?? null;
        // Número de NFS-e municipal e de NF em papel são numéricos no leiaute; os demais são texto livre.
        $doc['number'] = in_array($kind, [Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE, Invoice::DEDUCTION_DOC_PAPER], true)
            ? $this->digits($doc['number'] ?? null)
            : $this->text($doc['number'] ?? null);
        // Código de verificação pode diferenciar maiúsculas: só tira espaços.
        $doc['verification_code'] = $this->text($doc['verification_code'] ?? null);
        $doc['series'] = $this->digits($doc['series'] ?? null, alphanumeric: true);
        $doc['reason'] = $this->text($doc['reason'] ?? null);

        $supplier = $doc['supplier'] ?? null;
        $supplier = is_array($supplier) ? $this->normalizePerson($supplier) : null;
        $doc['supplier'] = $supplier !== null && $this->hasIdentity($supplier) ? $supplier : null;

        // Só os campos do tipo escolhido seguem; o painel manda todos.
        return array_filter($doc, fn ($v) => $v !== null);
    }

    public function attributes(): array
    {
        return [
            'competence_date' => 'data de competência',
            ...$this->personAttributes('taker', 'do tomador'),
            ...$this->personAttributes('intermediary', 'do intermediário'),
            'service.national_tax_code' => 'código de tributação nacional',
            'service.municipal_tax_code' => 'código de tributação municipal',
            'service.nbs_code' => 'código NBS',
            'service.description' => 'descrição do serviço',
            'service.city_code' => 'município da prestação',
            'service.result_country' => 'país do resultado do serviço',
            'service.foreign_trade' => 'dados de comércio exterior',
            'service.foreign_trade.provision_mode' => 'modo de prestação',
            'service.foreign_trade.currency' => 'moeda',
            'service.foreign_trade.foreign_amount' => 'valor em moeda estrangeira',
            'values.amount' => 'valor do serviço',
            'values.iss_rate' => 'alíquota do ISS',
            'values.immunity_type' => 'tipo de imunidade',
            'values.discounts.unconditional' => 'desconto incondicionado',
            'values.discounts.conditional' => 'desconto condicionado',
            'values.deduction.percent' => 'percentual de dedução',
            'values.deduction.amount' => 'valor de dedução',
            'values.deduction.documents' => 'documentos de dedução',
            'values.deduction.documents.*.access_key' => 'chave de acesso',
            'values.deduction.documents.*.city_code' => 'município da NFS-e',
            'values.deduction.documents.*.number' => 'número do documento',
            'values.deduction.documents.*.verification_code' => 'código de verificação',
            'values.deduction.documents.*.model' => 'modelo',
            'values.deduction.documents.*.series' => 'série',
            'values.deduction.documents.*.reason' => 'tipo de dedução',
            'values.deduction.documents.*.reason_description' => 'descrição da dedução',
            'values.deduction.documents.*.issued_on' => 'data de emissão do documento',
            'values.deduction.documents.*.deductible_amount' => 'valor dedutível do documento',
            'values.deduction.documents.*.amount' => 'valor deduzido',
        ];
    }

    public function messages(): array
    {
        return [
            'taker.document.required_without_all' => 'Informe o CPF/CNPJ do tomador ou, se estrangeiro, o NIF ou o motivo de não ter NIF.',
            'intermediary.document.required_without_all' => 'Informe o CPF/CNPJ do intermediário ou, se estrangeiro, o NIF ou o motivo de não ter NIF.',
            'service.result_country.required_if' => 'Na exportação de serviço, informe o país onde o resultado do serviço se verificou.',
            'service.result_country.not_in' => 'Na exportação, o país do resultado não pode ser o Brasil.',
            'service.foreign_trade.required_if' => 'Na exportação de serviço, informe os dados de comércio exterior.',
            'values.immunity_type.required_if' => 'Informe o tipo de imunidade do ISSQN.',
            'values.deduction.documents.*.reason_description.required_if' => 'Descreva a dedução quando o tipo é "Outras deduções".',
            'values.deduction.documents.*.amount.lte' => 'O valor deduzido não pode passar do valor dedutível do documento.',
            ...IbsCbsCodes::messages('values.ibs_cbs.cst', 'values.ibs_cbs.class_code', 'values.ibs_cbs.operation_code'),
        ];
    }

    /** Payload validado no formato que o InvoiceService espera. */
    public function payload(): array
    {
        return $this->validated();
    }

    /**
     * Tomador e intermediário têm o mesmo leiaute (TCInfoPessoa): CPF/CNPJ ou NIF de estrangeiro,
     * endereço nacional (código IBGE + CEP) ou no exterior (país, código postal, cidade e estado).
     */
    protected function personRules(string $key, bool $required): array
    {
        $present = $required ? 'required' : 'nullable';

        return [
            $key => $present.'|array:document,nif,nif_absence_reason,name,email,phone,municipal_registration,address',
            "{$key}.document" => [
                'nullable',
                "required_without_all:{$key}.nif,{$key}.nif_absence_reason",
                "prohibits:{$key}.nif,{$key}.nif_absence_reason",
                'string',
                new ValidTaxDocument,
            ],
            "{$key}.nif" => "nullable|prohibits:{$key}.nif_absence_reason|string|max:40",
            "{$key}.nif_absence_reason" => 'nullable|in:'.implode(',', array_keys(Invoice::NIF_ABSENCE_LABELS)),
            "{$key}.name" => "required_with:{$key}|nullable|string|max:300",
            "{$key}.email" => 'nullable|email|max:80',
            "{$key}.phone" => 'nullable|string|min:6|max:20',
            "{$key}.municipal_registration" => 'nullable|string|max:15',
            "{$key}.address" => 'nullable|array:country,postal_code,street,number,complement,district,city_code,city,state',
            "{$key}.address.country" => 'nullable|alpha|size:2',
            "{$key}.address.postal_code" => ["required_with:{$key}.address", 'nullable', $this->isForeign($key) ? 'max:11' : 'digits:8'],
            "{$key}.address.street" => "required_with:{$key}.address|nullable|string|max:255",
            "{$key}.address.number" => "required_with:{$key}.address|nullable|string|max:60",
            "{$key}.address.complement" => 'nullable|string|max:156',
            "{$key}.address.district" => "required_with:{$key}.address|nullable|string|max:60",
            "{$key}.address.city_code" => $this->isForeign($key)
                ? ['prohibited']
                : ["required_with:{$key}.address", 'nullable', 'digits:7', new IbgeCityCode],
            "{$key}.address.city" => $this->isForeign($key) ? "required_with:{$key}.address|string|max:60" : 'prohibited',
            "{$key}.address.state" => $this->isForeign($key) ? "required_with:{$key}.address|string|max:60" : 'prohibited',
        ];
    }

    protected function personAttributes(string $key, string $suffix): array
    {
        return [
            "{$key}.document" => "CPF/CNPJ {$suffix}",
            "{$key}.nif" => "NIF {$suffix}",
            "{$key}.name" => "nome {$suffix}",
            "{$key}.email" => "e-mail {$suffix}",
            "{$key}.phone" => "telefone {$suffix}",
            "{$key}.address.country" => "país {$suffix}",
            "{$key}.address.postal_code" => $this->isForeign($key) ? "código postal {$suffix}" : "CEP {$suffix}",
            "{$key}.address.street" => "logradouro {$suffix}",
            "{$key}.address.number" => "número {$suffix}",
            "{$key}.address.district" => "bairro {$suffix}",
            "{$key}.address.city_code" => "município {$suffix}",
            "{$key}.address.city" => "cidade {$suffix}",
            "{$key}.address.state" => "estado ou província {$suffix}",
        ];
    }

    /** Endereço no exterior: país informado e diferente do Brasil. */
    protected function isForeign(string $key): bool
    {
        $country = $this->input("{$key}.address.country");

        return is_string($country) && $country !== '' && $country !== 'BR';
    }

    protected function normalizePerson(array $person): array
    {
        $person['document'] = $this->digits($person['document'] ?? null, alphanumeric: true);
        $person['nif'] = $this->text($person['nif'] ?? null);
        $person['nif_absence_reason'] = $this->text($person['nif_absence_reason'] ?? null);
        $person['phone'] = $this->digits($person['phone'] ?? null);
        $person['municipal_registration'] = $this->digits($person['municipal_registration'] ?? null, alphanumeric: true);

        $address = $person['address'] ?? null;
        if (is_array($address)) {
            $address['country'] = $this->country($address['country'] ?? null);
            $foreign = $address['country'] !== null && $address['country'] !== 'BR';
            $address['postal_code'] = $foreign ? $this->text($address['postal_code'] ?? null) : $this->digits($address['postal_code'] ?? null);
            $address = $this->blankToNull($address);
            // O painel manda o grupo de endereço sempre; vazio, ele simplesmente não vai para a DPS.
            $filled = array_filter($address, fn ($v, $k) => $v !== null && $k !== 'country', ARRAY_FILTER_USE_BOTH);
            $person['address'] = $filled === [] ? null : array_filter($address, fn ($v) => $v !== null);
        }

        return $person;
    }

    protected function isForeignAddress(array $address): bool
    {
        $country = strtoupper((string) ($address['country'] ?? ''));

        return $country !== '' && $country !== 'BR';
    }

    protected function hasIdentity(array $person): bool
    {
        return ($person['document'] ?? null) !== null
            || ($person['nif'] ?? null) !== null
            || ($person['nif_absence_reason'] ?? null) !== null
            || $this->text($person['name'] ?? null) !== null;
    }

    protected function country(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : strtoupper($value);
    }

    protected function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /** @param  array<string, mixed>  $values */
    protected function blankToNull(array $values): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $values);
    }

    protected function digits(mixed $value, bool $alphanumeric = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = strtoupper(preg_replace($alphanumeric ? '/[^A-Za-z0-9]/' : '/\D/', '', (string) $value) ?? '');

        return $clean === '' ? null : $clean;
    }
}
