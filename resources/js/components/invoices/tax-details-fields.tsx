import { Field } from '@/components/invoices/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatMoney } from '@/lib/fiscal';

/** Retenções federais em alíquota: os valores saem do valor do serviço (o servidor faz a mesma conta). */
export interface TaxDetailsForm {
    pis_cofins_cst: string;
    pis_rate: string;
    cofins_rate: string;
    withholding: string;
    irrf_rate: string;
    csll_rate: string;
    inss_rate: string;
    benefit_number: string;
    benefit_type: 'none' | 'amount' | 'percent';
    benefit_value: string;
    suspension_type: string;
    suspension_process: string;
}

type Money = string | number | null | undefined;

export interface TaxDetailsDraft {
    federal_taxes?: {
        pis_cofins?: { cst?: string; pis_rate?: Money; cofins_rate?: Money; withholding?: string | null } | null;
        irrf?: { rate?: Money } | null;
        csll?: { rate?: Money } | null;
        inss?: { rate?: Money } | null;
    } | null;
    municipal_benefit?: { number?: string; reduction_amount?: Money; reduction_percent?: Money } | null;
    iss_suspension?: { type?: string; process_number?: string } | null;
}

const text = (value: Money) => (value === null || value === undefined ? '' : String(value));

export function taxDetailsFromDraft(draft: TaxDetailsDraft | undefined): TaxDetailsForm {
    const fed = draft?.federal_taxes;
    const bm = draft?.municipal_benefit;

    return {
        pis_cofins_cst: text(fed?.pis_cofins?.cst),
        pis_rate: text(fed?.pis_cofins?.pis_rate),
        cofins_rate: text(fed?.pis_cofins?.cofins_rate),
        withholding: text(fed?.pis_cofins?.withholding),
        irrf_rate: text(fed?.irrf?.rate),
        csll_rate: text(fed?.csll?.rate),
        inss_rate: text(fed?.inss?.rate),
        benefit_number: text(bm?.number),
        benefit_type: bm?.reduction_percent != null ? 'percent' : bm?.reduction_amount != null ? 'amount' : 'none',
        benefit_value: bm?.reduction_percent != null ? text(bm.reduction_percent) : text(bm?.reduction_amount),
        suspension_type: text(draft?.iss_suspension?.type),
        suspension_process: text(draft?.iss_suspension?.process_number),
    };
}

export function taxDetailsPayload(form: TaxDetailsForm) {
    const rate = (value: string) => (value === '' ? null : { rate: value });

    return {
        federal_taxes: {
            pis_cofins: form.pis_cofins_cst
                ? { cst: form.pis_cofins_cst, pis_rate: form.pis_rate, cofins_rate: form.cofins_rate, withholding: form.withholding }
                : null,
            irrf: rate(form.irrf_rate),
            csll: rate(form.csll_rate),
            inss: rate(form.inss_rate),
        },
        municipal_benefit: form.benefit_number
            ? {
                  number: form.benefit_number,
                  reduction_amount: form.benefit_type === 'amount' ? form.benefit_value : '',
                  reduction_percent: form.benefit_type === 'percent' ? form.benefit_value : '',
              }
            : null,
        iss_suspension: form.suspension_type ? { type: form.suspension_type, process_number: form.suspension_process } : null,
    };
}

export function hasTaxDetails(form: TaxDetailsForm): boolean {
    return Boolean(form.pis_cofins_cst || form.irrf_rate || form.csll_rate || form.inss_rate || form.benefit_number || form.suspension_type);
}

function RateInput({ id, value, onChange }: { id: string; value: string; onChange: (value: string) => void }) {
    return <Input id={id} type="number" step="0.01" min="0" max="100" value={value} onChange={(e) => onChange(e.target.value)} />;
}

export function TaxDetailsFields({
    value,
    onChange,
    amount,
    errors,
    withholdingOptions,
    suspensionOptions,
}: {
    value: TaxDetailsForm;
    onChange: (patch: Partial<TaxDetailsForm>) => void;
    amount: string;
    errors: Record<string, string | undefined>;
    withholdingOptions: Record<string, string>;
    suspensionOptions: Record<string, string>;
}) {
    const computed = (rate: string) => (rate !== '' && amount !== '' ? formatMoney((Number(amount) * Number(rate)) / 100) : null);
    const err = (key: string) => errors[`values.${key}`];

    return (
        <div className="grid gap-6">
            <div className="grid gap-4">
                <p className="text-sm font-medium">Retenções federais</p>
                <p className="-mt-2 text-xs text-muted-foreground">
                    Informe as alíquotas: os valores retidos são calculados sobre o valor do serviço. Só se aplicam quando o tomador é obrigado a reter.
                </p>
                <div className="grid gap-4 sm:grid-cols-3">
                    {(
                        [
                            ['irrf_rate', 'IRRF (%)', 'irrf'],
                            ['csll_rate', 'CSLL (%)', 'csll'],
                            ['inss_rate', 'INSS (%)', 'inss'],
                        ] as const
                    ).map(([key, label, tax]) => (
                        <Field
                            key={key}
                            id={key}
                            label={label}
                            hint={computed(value[key]) ? `Retido: ${computed(value[key])}` : undefined}
                            error={err(`federal_taxes.${tax}.rate`) ?? err(`federal_taxes.${tax}.amount`)}
                        >
                            <RateInput id={key} value={value[key]} onChange={(v) => onChange({ [key]: v })} />
                        </Field>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-[8rem_1fr_1fr]">
                    <Field id="pis_cofins_cst" label="CST PIS/COFINS" hint="Ex.: 01" error={err('federal_taxes.pis_cofins.cst')}>
                        <Input
                            id="pis_cofins_cst"
                            value={value.pis_cofins_cst}
                            onChange={(e) => onChange({ pis_cofins_cst: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                            inputMode="numeric"
                        />
                    </Field>
                    <Field id="pis_rate" label="PIS (%)" hint={computed(value.pis_rate) ?? undefined} error={err('federal_taxes.pis_cofins.pis_rate')}>
                        <RateInput id="pis_rate" value={value.pis_rate} onChange={(v) => onChange({ pis_rate: v })} />
                    </Field>
                    <Field id="cofins_rate" label="COFINS (%)" hint={computed(value.cofins_rate) ?? undefined} error={err('federal_taxes.pis_cofins.cofins_rate')}>
                        <RateInput id="cofins_rate" value={value.cofins_rate} onChange={(v) => onChange({ cofins_rate: v })} />
                    </Field>
                </div>
                {value.pis_cofins_cst && (
                    <Field id="withholding" label="Retenção de PIS/COFINS/CSLL" error={err('federal_taxes.pis_cofins.withholding')} className="sm:max-w-md">
                        <Select value={value.withholding} onValueChange={(v) => onChange({ withholding: v })}>
                            <SelectTrigger id="withholding">
                                <SelectValue placeholder="Escolha" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(withholdingOptions).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                )}
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Benefício municipal</p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="benefit_number" label="Número do benefício" hint="14 dígitos, dado pela prefeitura" error={err('municipal_benefit.number')}>
                        <Input
                            id="benefit_number"
                            value={value.benefit_number}
                            onChange={(e) => onChange({ benefit_number: e.target.value.replace(/\D/g, '').slice(0, 14) })}
                            inputMode="numeric"
                        />
                    </Field>
                    <Field id="benefit_type" label="Redução da base">
                        <Select value={value.benefit_type} onValueChange={(v) => onChange({ benefit_type: v as TaxDetailsForm['benefit_type'] })}>
                            <SelectTrigger id="benefit_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sem redução</SelectItem>
                                <SelectItem value="amount">Valor (R$)</SelectItem>
                                <SelectItem value="percent">Percentual (%)</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {value.benefit_type !== 'none' && (
                        <Field
                            id="benefit_value"
                            label={value.benefit_type === 'percent' ? 'Redução (%)' : 'Redução (R$)'}
                            error={err('municipal_benefit.reduction_amount') ?? err('municipal_benefit.reduction_percent')}
                        >
                            <Input id="benefit_value" type="number" step="0.01" min="0.01" value={value.benefit_value} onChange={(e) => onChange({ benefit_value: e.target.value })} />
                        </Field>
                    )}
                </div>
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Exigibilidade suspensa do ISS</p>
                <div className="grid gap-4 sm:grid-cols-[14rem_1fr]">
                    <Field id="suspension_type" label="Motivo" error={err('iss_suspension.type')}>
                        <Select value={value.suspension_type || 'none'} onValueChange={(v) => onChange({ suspension_type: v === 'none' ? '' : v })}>
                            <SelectTrigger id="suspension_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Não suspensa</SelectItem>
                                {Object.entries(suspensionOptions).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    {value.suspension_type && (
                        <Field id="suspension_process" label="Número do processo" hint="Só os dígitos" error={err('iss_suspension.process_number')}>
                            <Input
                                id="suspension_process"
                                value={value.suspension_process}
                                onChange={(e) => onChange({ suspension_process: e.target.value.replace(/\D/g, '').slice(0, 30) })}
                                inputMode="numeric"
                            />
                        </Field>
                    )}
                </div>
            </div>
        </div>
    );
}
