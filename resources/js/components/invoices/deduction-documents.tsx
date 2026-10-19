import { Field } from '@/components/invoices/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatMoney } from '@/lib/fiscal';
import { Plus, Trash2 } from 'lucide-react';
import { MunicipalityInput } from '@/components/municipality-input';

export type DeductionDocumentKind = 'nfse' | 'nfe' | 'municipal_nfse' | 'paper_invoice' | 'fiscal_document' | 'other_document';

export interface DeductionDocumentForm {
    kind: DeductionDocumentKind;
    access_key: string;
    city_code: string;
    number: string;
    verification_code: string;
    model: string;
    series: string;
    reason: string;
    reason_description: string;
    issued_on: string;
    deductible_amount: string;
    amount: string;
    supplier_document: string;
    supplier_name: string;
}

export type DeductionDocumentDraft = Partial<Omit<DeductionDocumentForm, 'supplier_document' | 'supplier_name'>> & {
    supplier?: { document?: string | null; name?: string | null } | null;
};

const text = (value: string | number | null | undefined) => (value === null || value === undefined ? '' : String(value));

export function emptyDeductionDocument(): DeductionDocumentForm {
    return {
        kind: 'nfse',
        access_key: '',
        city_code: '',
        number: '',
        verification_code: '',
        model: '',
        series: '',
        reason: '',
        reason_description: '',
        issued_on: '',
        deductible_amount: '',
        amount: '',
        supplier_document: '',
        supplier_name: '',
    };
}

export function deductionDocumentFromDraft(draft: DeductionDocumentDraft): DeductionDocumentForm {
    const empty = emptyDeductionDocument();

    return {
        ...empty,
        kind: draft.kind ?? empty.kind,
        access_key: text(draft.access_key),
        city_code: text(draft.city_code),
        number: text(draft.number),
        verification_code: text(draft.verification_code),
        model: text(draft.model),
        series: text(draft.series),
        reason: text(draft.reason),
        reason_description: text(draft.reason_description),
        issued_on: text(draft.issued_on),
        deductible_amount: text(draft.deductible_amount),
        amount: text(draft.amount),
        supplier_document: text(draft.supplier?.document),
        supplier_name: text(draft.supplier?.name),
    };
}

/** Só os campos do tipo de documento escolhido seguem para a API. */
export function deductionDocumentPayload(doc: DeductionDocumentForm) {
    const has = (...kinds: DeductionDocumentKind[]) => kinds.includes(doc.kind);

    return {
        kind: doc.kind,
        access_key: has('nfse', 'nfe') ? doc.access_key : '',
        city_code: has('municipal_nfse') ? doc.city_code : '',
        number: has('municipal_nfse', 'paper_invoice', 'fiscal_document', 'other_document') ? doc.number : '',
        verification_code: has('municipal_nfse') ? doc.verification_code : '',
        model: has('paper_invoice') ? doc.model : '',
        series: has('paper_invoice') ? doc.series : '',
        reason: doc.reason,
        reason_description: doc.reason === '99' ? doc.reason_description : '',
        issued_on: doc.issued_on,
        deductible_amount: doc.deductible_amount,
        amount: doc.amount,
        supplier: doc.supplier_document || doc.supplier_name ? { document: doc.supplier_document, name: doc.supplier_name } : null,
    };
}

export function DeductionDocuments({
    value,
    onChange,
    errors,
    kinds,
    reasons,
}: {
    value: DeductionDocumentForm[];
    onChange: (documents: DeductionDocumentForm[]) => void;
    errors: Record<string, string | undefined>;
    kinds: Record<string, string>;
    reasons: Record<string, string>;
}) {
    const update = (index: number, patch: Partial<DeductionDocumentForm>) => onChange(value.map((doc, i) => (i === index ? { ...doc, ...patch } : doc)));
    const remove = (index: number) => onChange(value.filter((_, i) => i !== index));
    const total = value.reduce((sum, doc) => sum + (Number(doc.amount) || 0), 0);

    return (
        <div className="grid gap-4">
            {errors['values.deduction.documents'] && <p className="text-sm text-destructive">{errors['values.deduction.documents']}</p>}

            {value.map((doc, index) => {
                const err = (name: string) => errors[`values.deduction.documents.${index}.${name}`];
                const id = (name: string) => `deduction_${index}_${name}`;

                return (
                    <fieldset key={index} className="grid gap-4 rounded-lg border bg-muted/20 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <legend className="text-sm font-medium">Documento {index + 1}</legend>
                            <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)} aria-label={`Remover documento ${index + 1}`}>
                                <Trash2 /> Remover
                            </Button>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id={id('kind')} label="Tipo de documento" error={err('kind')}>
                                <Select value={doc.kind} onValueChange={(v) => update(index, { kind: v as DeductionDocumentKind })}>
                                    <SelectTrigger id={id('kind')}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(kinds).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field id={id('reason')} label="Tipo de dedução" error={err('reason')}>
                                <Select value={doc.reason} onValueChange={(v) => update(index, { reason: v })}>
                                    <SelectTrigger id={id('reason')} aria-invalid={Boolean(err('reason'))}>
                                        <SelectValue placeholder="Escolha" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(reasons).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        </div>

                        {doc.reason === '99' && (
                            <Field id={id('reason_description')} label="Descrição da dedução" error={err('reason_description')}>
                                <Input
                                    id={id('reason_description')}
                                    value={doc.reason_description}
                                    onChange={(e) => update(index, { reason_description: e.target.value })}
                                    maxLength={150}
                                />
                            </Field>
                        )}

                        {(doc.kind === 'nfse' || doc.kind === 'nfe') && (
                            <Field
                                id={id('access_key')}
                                label="Chave de acesso"
                                hint={doc.kind === 'nfse' ? '50 dígitos' : '44 dígitos'}
                                error={err('access_key')}
                            >
                                <Input
                                    id={id('access_key')}
                                    value={doc.access_key}
                                    onChange={(e) => update(index, { access_key: e.target.value })}
                                    inputMode="numeric"
                                    className="font-mono"
                                />
                            </Field>
                        )}

                        {doc.kind === 'municipal_nfse' && (
                            <div className="grid gap-4 sm:grid-cols-[1fr_9rem_9rem]">
                                <Field id={id('city_code')} label="Município emissor" error={err('city_code')}>
                                    <MunicipalityInput id={id('city_code')} value={doc.city_code} onChange={(code) => update(index, { city_code: code })} />
                                </Field>
                                <Field id={id('number')} label="Número" error={err('number')}>
                                    <Input id={id('number')} value={doc.number} onChange={(e) => update(index, { number: e.target.value })} inputMode="numeric" />
                                </Field>
                                <Field id={id('verification_code')} label="Cód. verificação" error={err('verification_code')}>
                                    <Input
                                        id={id('verification_code')}
                                        value={doc.verification_code}
                                        onChange={(e) => update(index, { verification_code: e.target.value })}
                                        maxLength={9}
                                    />
                                </Field>
                            </div>
                        )}

                        {doc.kind === 'paper_invoice' && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field id={id('number')} label="Número" error={err('number')}>
                                    <Input id={id('number')} value={doc.number} onChange={(e) => update(index, { number: e.target.value })} inputMode="numeric" />
                                </Field>
                                <Field id={id('model')} label="Modelo" error={err('model')}>
                                    <Input id={id('model')} value={doc.model} onChange={(e) => update(index, { model: e.target.value })} inputMode="numeric" />
                                </Field>
                                <Field id={id('series')} label="Série" error={err('series')}>
                                    <Input id={id('series')} value={doc.series} onChange={(e) => update(index, { series: e.target.value })} maxLength={15} />
                                </Field>
                            </div>
                        )}

                        {(doc.kind === 'fiscal_document' || doc.kind === 'other_document') && (
                            <Field id={id('number')} label="Número ou identificação do documento" error={err('number')}>
                                <Input id={id('number')} value={doc.number} onChange={(e) => update(index, { number: e.target.value })} maxLength={255} />
                            </Field>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field id={id('issued_on')} label="Emitido em" error={err('issued_on')}>
                                <Input id={id('issued_on')} type="date" value={doc.issued_on} onChange={(e) => update(index, { issued_on: e.target.value })} />
                            </Field>
                            <Field id={id('deductible_amount')} label="Valor dedutível (R$)" hint="Total do documento que pode ser deduzido" error={err('deductible_amount')}>
                                <Input
                                    id={id('deductible_amount')}
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={doc.deductible_amount}
                                    onChange={(e) => update(index, { deductible_amount: e.target.value })}
                                />
                            </Field>
                            <Field id={id('amount')} label="Deduzido nesta nota (R$)" error={err('amount')}>
                                <Input
                                    id={id('amount')}
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={doc.amount}
                                    onChange={(e) => update(index, { amount: e.target.value })}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                            <Field id={id('supplier_document')} label="CPF/CNPJ do fornecedor" optional error={err('supplier.document')}>
                                <Input
                                    id={id('supplier_document')}
                                    value={doc.supplier_document}
                                    onChange={(e) => update(index, { supplier_document: e.target.value })}
                                />
                            </Field>
                            <Field id={id('supplier_name')} label="Nome do fornecedor" optional error={err('supplier.name')}>
                                <Input id={id('supplier_name')} value={doc.supplier_name} onChange={(e) => update(index, { supplier_name: e.target.value })} />
                            </Field>
                        </div>
                    </fieldset>
                );
            })}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button type="button" variant="outline" size="sm" onClick={() => onChange([...value, emptyDeductionDocument()])} disabled={value.length >= 1000}>
                    <Plus /> Adicionar documento
                </Button>
                {value.length > 0 && <p className="text-sm text-muted-foreground">Total deduzido: {formatMoney(total)}</p>}
            </div>
        </div>
    );
}
