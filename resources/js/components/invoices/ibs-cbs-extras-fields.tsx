import { Field } from '@/components/invoices/field';
import { addressFromDraft, emptyAddress, SimpleAddressFields, type SimpleAddressForm } from '@/components/invoices/service-extras-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { MunicipalityInput } from '@/components/municipality-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Trash2 } from 'lucide-react';

type ReimbursementKind = 'dfe' | 'fiscal_document' | 'other_document';

export interface ReimbursementForm {
    kind: ReimbursementKind;
    dfe_type: string;
    dfe_type_description: string;
    access_key: string;
    city_code: string;
    number: string;
    description: string;
    supplier_document: string;
    supplier_name: string;
    issued_on: string;
    competence_on: string;
    type: string;
    type_description: string;
    amount: string;
}

export interface IbsCbsExtrasForm {
    operation_type: string;
    government_entity: string;
    referenced_invoices: string;
    recipient_document: string;
    recipient_name: string;
    recipient_email: string;
    property_mode: 'none' | 'cib' | 'address';
    property_registration: string;
    property_cib: string;
    property_address: SimpleAddressForm;
    reimbursements: ReimbursementForm[];
}

type Loose = Record<string, unknown> | null | undefined;
const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));

function emptyReimbursement(): ReimbursementForm {
    return {
        kind: 'dfe',
        dfe_type: '1',
        dfe_type_description: '',
        access_key: '',
        city_code: '',
        number: '',
        description: '',
        supplier_document: '',
        supplier_name: '',
        issued_on: '',
        competence_on: '',
        type: '',
        type_description: '',
        amount: '',
    };
}

export function ibsCbsExtrasFromDraft(ibsCbs: Loose): IbsCbsExtrasForm {
    const recipient = (ibsCbs?.recipient ?? null) as Loose;
    const property = (ibsCbs?.property ?? null) as Loose;
    const items = Array.isArray(ibsCbs?.reimbursements) ? (ibsCbs.reimbursements as Loose[]) : [];

    return {
        operation_type: text(ibsCbs?.operation_type),
        government_entity: text(ibsCbs?.government_entity),
        referenced_invoices: Array.isArray(ibsCbs?.referenced_invoices) ? (ibsCbs.referenced_invoices as string[]).join('\n') : '',
        recipient_document: text(recipient?.document),
        recipient_name: text(recipient?.name),
        recipient_email: text(recipient?.email),
        property_mode: property?.cib ? 'cib' : property?.address ? 'address' : 'none',
        property_registration: text(property?.registration),
        property_cib: text(property?.cib),
        property_address: property?.address ? addressFromDraft(property.address as Loose) : emptyAddress(),
        reimbursements: items.map((item) => ({
            ...emptyReimbursement(),
            ...Object.fromEntries(Object.entries(item ?? {}).filter(([, v]) => typeof v === 'string' || typeof v === 'number').map(([k, v]) => [k, String(v)])),
            supplier_document: text((item?.supplier as Loose)?.document),
            supplier_name: text((item?.supplier as Loose)?.name),
        })) as ReimbursementForm[],
    };
}

export function ibsCbsExtrasPayload(form: IbsCbsExtrasForm) {
    return {
        operation_type: form.operation_type,
        government_entity: form.government_entity,
        referenced_invoices: form.referenced_invoices
            .split(/[\s,;]+/)
            .map((key) => key.trim())
            .filter(Boolean),
        recipient: form.recipient_document || form.recipient_name ? { document: form.recipient_document, name: form.recipient_name, email: form.recipient_email } : null,
        property:
            form.property_mode === 'none'
                ? null
                : {
                      registration: form.property_registration,
                      cib: form.property_mode === 'cib' ? form.property_cib : '',
                      address: form.property_mode === 'address' ? form.property_address : null,
                  },
        reimbursements: form.reimbursements.map((r) => ({
            kind: r.kind,
            dfe_type: r.kind === 'dfe' ? r.dfe_type : '',
            dfe_type_description: r.kind === 'dfe' && r.dfe_type === '9' ? r.dfe_type_description : '',
            access_key: r.kind === 'dfe' ? r.access_key : '',
            city_code: r.kind === 'fiscal_document' ? r.city_code : '',
            number: r.kind === 'dfe' ? '' : r.number,
            description: r.kind === 'dfe' ? '' : r.description,
            supplier: r.supplier_document || r.supplier_name ? { document: r.supplier_document, name: r.supplier_name } : null,
            issued_on: r.issued_on,
            competence_on: r.competence_on,
            type: r.type,
            type_description: r.type === '99' ? r.type_description : '',
            amount: r.amount,
        })),
    };
}

export function hasIbsCbsExtras(form: IbsCbsExtrasForm): boolean {
    return Boolean(
        form.operation_type || form.government_entity || form.referenced_invoices || form.recipient_document || form.property_mode !== 'none' || form.reimbursements.length,
    );
}

function OptionSelect({ id, value, onChange, options, empty }: { id: string; value: string; onChange: (v: string) => void; options: Record<string, string>; empty: string }) {
    return (
        <Select value={value || 'none'} onValueChange={(v) => onChange(v === 'none' ? '' : v)}>
            <SelectTrigger id={id}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">{empty}</SelectItem>
                {Object.entries(options).map(([key, label]) => (
                    <SelectItem key={key} value={key}>
                        {label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function IbsCbsExtrasFields({
    value,
    onChange,
    errors,
    options,
}: {
    value: IbsCbsExtrasForm;
    onChange: (patch: Partial<IbsCbsExtrasForm>) => void;
    errors: Record<string, string | undefined>;
    options: { operation: Record<string, string>; government: Record<string, string>; reimbursement: Record<string, string>; dfe: Record<string, string> };
}) {
    const err = (key: string) => errors[`values.ibs_cbs.${key}`];
    const updateItem = (index: number, patch: Partial<ReimbursementForm>) =>
        onChange({ reimbursements: value.reimbursements.map((r, i) => (i === index ? { ...r, ...patch } : r)) });

    return (
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="ibs_operation_type" label="Operação com ente governamental ou imóvel" error={err('operation_type')}>
                    <OptionSelect id="ibs_operation_type" value={value.operation_type} onChange={(v) => onChange({ operation_type: v })} options={options.operation} empty="Não se aplica" />
                </Field>
                <Field id="ibs_government_entity" label="Ente governamental" error={err('government_entity')}>
                    <OptionSelect id="ibs_government_entity" value={value.government_entity} onChange={(v) => onChange({ government_entity: v })} options={options.government} empty="Não se aplica" />
                </Field>
            </div>

            <Field id="ibs_referenced" label="NFS-e referenciadas" optional hint="Chaves de acesso de 50 dígitos, uma por linha" error={err('referenced_invoices') ?? err('referenced_invoices.0')}>
                <Textarea id="ibs_referenced" value={value.referenced_invoices} onChange={(e) => onChange({ referenced_invoices: e.target.value })} rows={2} className="font-mono text-xs" />
            </Field>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Destinatário diferente do tomador</p>
                <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                    <Field id="ibs_recipient_document" label="CPF ou CNPJ" error={err('recipient.document')}>
                        <Input id="ibs_recipient_document" value={value.recipient_document} onChange={(e) => onChange({ recipient_document: e.target.value })} />
                    </Field>
                    <Field id="ibs_recipient_name" label="Nome" error={err('recipient.name')}>
                        <Input id="ibs_recipient_name" value={value.recipient_name} onChange={(e) => onChange({ recipient_name: e.target.value })} maxLength={150} />
                    </Field>
                    <Field id="ibs_recipient_email" label="E-mail" optional error={err('recipient.email')} className="sm:col-span-2">
                        <Input id="ibs_recipient_email" type="email" value={value.recipient_email} onChange={(e) => onChange({ recipient_email: e.target.value })} />
                    </Field>
                </div>
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Imóvel (operações com bens imóveis, exceto obras)</p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="ibs_property_mode" label="Identificação">
                        <Select value={value.property_mode} onValueChange={(v) => onChange({ property_mode: v as IbsCbsExtrasForm['property_mode'] })}>
                            <SelectTrigger id="ibs_property_mode">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Não se aplica</SelectItem>
                                <SelectItem value="cib">CIB</SelectItem>
                                <SelectItem value="address">Endereço</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {value.property_mode !== 'none' && (
                        <Field id="ibs_property_registration" label="Inscrição imobiliária" optional error={err('property.registration')}>
                            <Input id="ibs_property_registration" value={value.property_registration} onChange={(e) => onChange({ property_registration: e.target.value })} maxLength={30} />
                        </Field>
                    )}
                    {value.property_mode === 'cib' && (
                        <Field id="ibs_property_cib" label="CIB" error={err('property.cib')}>
                            <Input id="ibs_property_cib" value={value.property_cib} onChange={(e) => onChange({ property_cib: e.target.value })} maxLength={8} />
                        </Field>
                    )}
                </div>
                {value.property_mode === 'address' && (
                    <SimpleAddressFields
                        idPrefix="ibs_property_address"
                        errorPrefix="values.ibs_cbs.property.address"
                        value={value.property_address}
                        onChange={(address) => onChange({ property_address: address })}
                        errors={errors}
                    />
                )}
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Reembolsos, repasses e ressarcimentos</p>
                <p className="-mt-2 text-xs text-muted-foreground">Valores de terceiros já tributados, que ficam fora da base do ISS, do IBS e da CBS.</p>
                {value.reimbursements.map((r, index) => {
                    const e = (name: string) => err(`reimbursements.${index}.${name}`);
                    const id = (name: string) => `reimb_${index}_${name}`;

                    return (
                        <fieldset key={index} className="grid gap-4 rounded-lg border bg-muted/20 p-4">
                            <div className="flex items-center justify-between">
                                <legend className="text-sm font-medium">Documento {index + 1}</legend>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onChange({ reimbursements: value.reimbursements.filter((_, i) => i !== index) })}
                                >
                                    <Trash2 /> Remover
                                </Button>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id={id('kind')} label="Documento" error={e('kind')}>
                                    <Select value={r.kind} onValueChange={(v) => updateItem(index, { kind: v as ReimbursementKind })}>
                                        <SelectTrigger id={id('kind')}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="dfe">Documento eletrônico nacional (chave)</SelectItem>
                                            <SelectItem value="fiscal_document">Outro documento fiscal</SelectItem>
                                            <SelectItem value="other_document">Documento não fiscal</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field id={id('type')} label="Tipo" error={e('type')}>
                                    <OptionSelect id={id('type')} value={r.type} onChange={(v) => updateItem(index, { type: v })} options={options.reimbursement} empty="Escolha" />
                                </Field>
                            </div>
                            {r.type === '99' && (
                                <Field id={id('type_description')} label="Descrição do tipo" error={e('type_description')}>
                                    <Input id={id('type_description')} value={r.type_description} onChange={(ev) => updateItem(index, { type_description: ev.target.value })} maxLength={150} />
                                </Field>
                            )}
                            {r.kind === 'dfe' && (
                                <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                                    <Field id={id('dfe_type')} label="Tipo de documento" error={e('dfe_type')}>
                                        <OptionSelect id={id('dfe_type')} value={r.dfe_type} onChange={(v) => updateItem(index, { dfe_type: v })} options={options.dfe} empty="Escolha" />
                                    </Field>
                                    <Field id={id('access_key')} label="Chave de acesso" error={e('access_key')}>
                                        <Input id={id('access_key')} value={r.access_key} onChange={(ev) => updateItem(index, { access_key: ev.target.value })} className="font-mono" maxLength={50} />
                                    </Field>
                                </div>
                            )}
                            {r.kind === 'dfe' && r.dfe_type === '9' && (
                                <Field id={id('dfe_type_description')} label="Qual documento" error={e('dfe_type_description')}>
                                    <Input id={id('dfe_type_description')} value={r.dfe_type_description} onChange={(ev) => updateItem(index, { dfe_type_description: ev.target.value })} />
                                </Field>
                            )}
                            {r.kind !== 'dfe' && (
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {r.kind === 'fiscal_document' && (
                                        <Field id={id('city_code')} label="Município emissor" error={e('city_code')}>
                                            <MunicipalityInput id={id('city_code')} value={r.city_code} onChange={(code) => updateItem(index, { city_code: code })} />
                                        </Field>
                                    )}
                                    <Field id={id('number')} label="Número" error={e('number')}>
                                        <Input id={id('number')} value={r.number} onChange={(ev) => updateItem(index, { number: ev.target.value })} />
                                    </Field>
                                    <Field id={id('description')} label="Descrição" error={e('description')}>
                                        <Input id={id('description')} value={r.description} onChange={(ev) => updateItem(index, { description: ev.target.value })} />
                                    </Field>
                                </div>
                            )}
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field id={id('issued_on')} label="Emissão" error={e('issued_on')}>
                                    <Input id={id('issued_on')} type="date" value={r.issued_on} onChange={(ev) => updateItem(index, { issued_on: ev.target.value })} />
                                </Field>
                                <Field id={id('competence_on')} label="Competência" error={e('competence_on')}>
                                    <Input id={id('competence_on')} type="date" value={r.competence_on} onChange={(ev) => updateItem(index, { competence_on: ev.target.value })} />
                                </Field>
                                <Field id={id('amount')} label="Valor (R$)" error={e('amount')}>
                                    <Input id={id('amount')} type="number" step="0.01" min="0.01" value={r.amount} onChange={(ev) => updateItem(index, { amount: ev.target.value })} />
                                </Field>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                                <Field id={id('supplier_document')} label="CPF/CNPJ do fornecedor" optional error={e('supplier.document')}>
                                    <Input id={id('supplier_document')} value={r.supplier_document} onChange={(ev) => updateItem(index, { supplier_document: ev.target.value })} />
                                </Field>
                                <Field id={id('supplier_name')} label="Nome do fornecedor" optional error={e('supplier.name')}>
                                    <Input id={id('supplier_name')} value={r.supplier_name} onChange={(ev) => updateItem(index, { supplier_name: ev.target.value })} maxLength={150} />
                                </Field>
                            </div>
                        </fieldset>
                    );
                })}
                <Button type="button" variant="outline" size="sm" className="justify-self-start" onClick={() => onChange({ reimbursements: [...value.reimbursements, emptyReimbursement()] })}>
                    <Plus /> Adicionar documento
                </Button>
            </div>
        </div>
    );
}
