import { FormSection } from '@/components/form-section';
import {
    DeductionDocuments,
    deductionDocumentFromDraft,
    deductionDocumentPayload,
    emptyDeductionDocument,
    type DeductionDocumentDraft,
    type DeductionDocumentForm,
} from '@/components/invoices/deduction-documents';
import { countryName, Field } from '@/components/invoices/field';
import {
    hasIbsCbsExtras,
    IbsCbsExtrasFields,
    ibsCbsExtrasFromDraft,
    ibsCbsExtrasPayload,
    type IbsCbsExtrasForm,
} from '@/components/invoices/ibs-cbs-extras-fields';
import { ServiceExtrasFields, serviceExtrasFromDraft, serviceExtrasPayload, type ServiceExtrasForm } from '@/components/invoices/service-extras-fields';
import {
    hasTaxDetails,
    TaxDetailsFields,
    taxDetailsFromDraft,
    taxDetailsPayload,
    type TaxDetailsDraft,
    type TaxDetailsForm,
} from '@/components/invoices/tax-details-fields';
import { PersonFields, personFromDraft, personPayload, type PersonDraft, type PersonForm } from '@/components/invoices/person-fields';
import { MunicipalityInput } from '@/components/municipality-input';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatAccessKey, formatCnpj } from '@/lib/fiscal';
import { cn, formatDateTime } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Building2, CircleCheck, CircleX, Send, TriangleAlert } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

interface IssuerSummary {
    legal_name: string;
    cnpj: string;
    city_code: string;
    city_name: string | null;
    environment: 'restricted' | 'production';
    simples_nacional: number;
    next_dps_number: number;
    ready_to_issue: boolean;
    default_service: { national_tax_code: string | null; municipal_tax_code: string | null; nbs_code: string | null; description: string | null } | null;
    email_taker: boolean;
    agreement_situation: 'national_emitter' | 'adn_only' | 'inactive' | 'not_found' | null;
    ibs_cbs: IbsCbsCodes | null;
}

interface IbsCbsCodes {
    cst: string;
    class_code: string;
    operation_code: string;
}

interface SefinMessage {
    code: string | null;
    message: string;
    complement: string | null;
}

type Money = string | number | null;

/** Campos (parciais) de uma nota existente no formato do formulário. */
interface Draft {
    external_id: string | null;
    competence_date: string | null;
    taker: PersonDraft;
    intermediary: PersonDraft | null;
    service: Partial<Omit<InvoiceForm['service'], 'foreign_trade'>> & {
        foreign_trade?: Partial<Record<keyof InvoiceForm['service']['foreign_trade'], string | number | boolean | null>> | null;
    };
    values: {
        amount: string;
        iss_rate: string | null;
        iss_withheld: boolean;
        iss_withholder?: string | null;
        intermediary_received?: string | null;
        iss_taxation: number;
        immunity_type?: number | null;
        discounts?: { unconditional: Money; conditional: Money } | null;
        deduction?: { percent: Money; amount: Money; documents?: DeductionDocumentDraft[] | null } | null;
        approximate_taxes: Partial<Record<'federal' | 'state' | 'municipal', Money>> | null;
        ibs_cbs?: (IbsCbsCodes & { final_consumer: boolean | null }) | null;
    } & TaxDetailsDraft;
}

/** Nota emitida que a nova DPS vai substituir. */
interface ReplaceTarget {
    id: string;
    nfse_number: string | null;
    access_key: string | null;
    issued_at: string | null;
    reason_code: string | null;
    reason: string | null;
}

/** Nota rejeitada usada como rascunho para corrigir e reenviar. */
interface RetryOf {
    id: string;
    dps_number: number;
    rejection: SefinMessage[];
    draft: Draft;
    replaces: ReplaceTarget | null;
}

interface Props {
    issuer: IssuerSummary | null;
    retryOf: RetryOf | null;
    replacing: (ReplaceTarget & { draft: Draft }) | null;
    options: {
        iss_taxation: Record<string, string>;
        environment: Record<string, string>;
        substitution_reason: Record<string, string>;
        immunity: Record<string, string>;
        nif_absence: Record<string, string>;
        provision_mode: Record<string, string>;
        deduction_document: Record<string, string>;
        deduction_reason: Record<string, string>;
        pis_cofins_withholding: Record<string, string>;
        iss_suspension: Record<string, string>;
        ibs_cbs_operation: Record<string, string>;
        government_entity: Record<string, string>;
        reimbursement_type: Record<string, string>;
        dfe_type: Record<string, string>;
    };
    [key: string]: unknown;
}

type DeductionType = 'none' | 'amount' | 'percent' | 'documents';

interface InvoiceForm {
    external_id: string;
    notify_taker: boolean;
    competence_date: string;
    taker: PersonForm;
    has_intermediary: boolean;
    service_extras: ServiceExtrasForm;
    intermediary: PersonForm;
    service: {
        national_tax_code: string;
        municipal_tax_code: string;
        nbs_code: string;
        description: string;
        city_code: string;
        result_country: string;
        foreign_trade: { provision_mode: string; currency: string; foreign_amount: string };
    };
    values: {
        amount: string;
        iss_rate: string;
        iss_withheld: boolean;
        iss_withholder: 'taker' | 'intermediary';
        iss_taxation: string;
        immunity_type: string;
        discounts: { unconditional: string; conditional: string };
        deduction_type: DeductionType;
        deduction_value: string;
        deduction_documents: DeductionDocumentForm[];
        approximate_taxes: { federal: string; state: string; municipal: string };
        ibs_cbs: IbsCbsCodes & { final_consumer: boolean };
        tax_details: TaxDetailsForm;
        ibs_cbs_extras: IbsCbsExtrasForm;
        intermediary_received: string;
    };
    substitution: { reason_code: string; reason: string };
}

const SIMPLES_MEI = 2;
const ISS_IMMUNE = '2';
const ISS_EXPORT = '3';

interface ServiceCodeCheck {
    status: 'parametrized' | 'not_parametrized' | 'unknown';
    service_code: string | null;
    rate: number | null;
    message: string | null;
}

/**
 * Pergunta ao ADN, via backend, se o município parametrizou o código de serviço na competência.
 * Só avisa: a validação que bloqueia a emissão acontece no servidor.
 */
function useServiceCodeCheck(params: { national: string; municipal: string; city: string; competence: string }, enabled: boolean) {
    const [check, setCheck] = useState<ServiceCodeCheck | null>(null);
    const national = params.national.replace(/\D/g, '');
    const municipal = params.municipal.replace(/\D/g, '');

    useEffect(() => {
        setCheck(null);
        if (!enabled || national.length !== 6 || (municipal !== '' && municipal.length !== 3)) return;

        const controller = new AbortController();
        const timer = setTimeout(() => {
            axios
                .get<ServiceCodeCheck>(route('invoices.service-code-check'), {
                    params: { national_tax_code: national, municipal_tax_code: municipal || undefined, city_code: params.city || undefined, competence_date: params.competence || undefined },
                    signal: controller.signal,
                })
                .then((response) => setCheck(response.data))
                .catch(() => setCheck(null));
        }, 500);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [enabled, national, municipal, params.city, params.competence]);

    return check;
}

function formatRate(rate: number): string {
    return `${rate.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
}

function today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const text = (value: string | number | boolean | null | undefined) => (value === null || value === undefined ? '' : String(value));

function initialData(issuer: IssuerSummary | null, draft: Draft | undefined, target: ReplaceTarget | null): InvoiceForm {
    const taxes = draft?.values.approximate_taxes ?? {};
    const trade = draft?.service.foreign_trade ?? {};
    const deduction = draft?.values.deduction;
    const deductionType: DeductionType = deduction?.percent != null
        ? 'percent'
        : deduction?.amount != null
          ? 'amount'
          : deduction?.documents?.length
            ? 'documents'
            : 'none';

    return {
        external_id: text(draft?.external_id),
        notify_taker: issuer?.email_taker ?? true,
        competence_date: draft?.competence_date ?? today(),
        taker: personFromDraft(draft?.taker),
        has_intermediary: Boolean(draft?.intermediary),
        service_extras: serviceExtrasFromDraft(draft?.service as Record<string, unknown> | undefined),
        intermediary: personFromDraft(draft?.intermediary),
        service: {
            national_tax_code: text(draft?.service.national_tax_code),
            municipal_tax_code: text(draft?.service.municipal_tax_code),
            nbs_code: text(draft?.service.nbs_code),
            description: text(draft?.service.description),
            city_code: text(draft?.service.city_code) || (issuer?.city_code ?? ''),
            result_country: text(draft?.service.result_country),
            foreign_trade: {
                provision_mode: text(trade.provision_mode) || '1',
                currency: text(trade.currency),
                foreign_amount: text(trade.foreign_amount),
            },
        },
        values: {
            amount: text(draft?.values.amount),
            iss_rate: text(draft?.values.iss_rate),
            iss_withheld: draft?.values.iss_withheld ?? false,
            iss_withholder: draft?.values.iss_withholder === 'intermediary' ? 'intermediary' : 'taker',
            iss_taxation: text(draft?.values.iss_taxation) || '1',
            immunity_type: text(draft?.values.immunity_type),
            discounts: { unconditional: text(draft?.values.discounts?.unconditional), conditional: text(draft?.values.discounts?.conditional) },
            deduction_type: deductionType,
            deduction_value: deductionType === 'percent' ? text(deduction?.percent) : text(deduction?.amount),
            deduction_documents: (deduction?.documents ?? []).map(deductionDocumentFromDraft),
            approximate_taxes: { federal: text(taxes.federal), state: text(taxes.state), municipal: text(taxes.municipal) },
            ibs_cbs: ibsCbsDraft(draft?.values.ibs_cbs ?? null, issuer?.ibs_cbs ?? null),
            tax_details: taxDetailsFromDraft(draft?.values),
            ibs_cbs_extras: ibsCbsExtrasFromDraft(draft?.values.ibs_cbs as Record<string, unknown> | null | undefined),
            intermediary_received: text(draft?.values.intermediary_received),
        },
        substitution: { reason_code: target?.reason_code ?? '', reason: target?.reason ?? '' },
    };
}

/** Rascunho só traz códigos próprios quando eles diferem do padrão do emitente; senão o campo fica vazio e vale o padrão. */
function ibsCbsDraft(saved: (IbsCbsCodes & { final_consumer: boolean | null }) | null, defaults: IbsCbsCodes | null): InvoiceForm['values']['ibs_cbs'] {
    const sameAsDefault =
        saved !== null && defaults !== null && saved.cst === defaults.cst && saved.class_code === defaults.class_code && saved.operation_code === defaults.operation_code;
    const own = saved !== null && !sameAsDefault;

    return {
        cst: own ? saved.cst : '',
        class_code: own ? saved.class_code : '',
        operation_code: own ? saved.operation_code : '',
        final_consumer: saved?.final_consumer === true,
    };
}

/** Converte o estado do formulário no payload da API, mandando só os grupos que se aplicam. */
function payload(data: InvoiceForm, withSubstitution: boolean) {
    const { values, service } = data;
    const exporting = values.iss_taxation === ISS_EXPORT;
    const withholder = data.has_intermediary ? values.iss_withholder : 'taker';

    return {
        external_id: data.external_id,
        notify_taker: data.notify_taker,
        competence_date: data.competence_date,
        taker: personPayload(data.taker),
        intermediary: data.has_intermediary ? personPayload(data.intermediary) : null,
        service: {
            national_tax_code: service.national_tax_code,
            municipal_tax_code: service.municipal_tax_code,
            nbs_code: service.nbs_code,
            description: service.description,
            city_code: service.city_code,
            result_country: exporting ? service.result_country : '',
            foreign_trade: exporting ? service.foreign_trade : null,
            ...serviceExtrasPayload(data.service_extras),
        },
        values: {
            amount: values.amount,
            intermediary_received: data.has_intermediary ? values.intermediary_received : '',
            iss_rate: values.iss_rate,
            iss_withheld: values.iss_withheld,
            iss_withholder: values.iss_withheld ? withholder : null,
            iss_taxation: values.iss_taxation,
            immunity_type: values.iss_taxation === ISS_IMMUNE ? values.immunity_type : '',
            discounts: values.discounts,
            deduction:
                values.deduction_type === 'documents'
                    ? values.deduction_documents.length > 0
                        ? { documents: values.deduction_documents.map(deductionDocumentPayload) }
                        : null
                    : values.deduction_type === 'none' || values.deduction_value === ''
                      ? null
                      : { [values.deduction_type]: values.deduction_value },
            approximate_taxes: values.approximate_taxes,
            ibs_cbs: { ...values.ibs_cbs, final_consumer: values.ibs_cbs.final_consumer ? true : null, ...ibsCbsExtrasPayload(values.ibs_cbs_extras) },
            ...taxDetailsPayload(values.tax_details),
        },
        ...(withSubstitution ? { substitution: data.substitution } : {}),
    };
}

export default function InvoicesCreate({ issuer, retryOf, replacing, options }: Props) {
    // Substituta rejeitada continua substituindo a mesma nota ao ser corrigida.
    const target: ReplaceTarget | null = replacing ?? retryOf?.replaces ?? null;
    const form = useForm<InvoiceForm>(initialData(issuer, retryOf?.draft ?? replacing?.draft, target));

    const errors = form.errors as Record<string, string | undefined>;

    // Atualiza um grupo do formulário sem perder os outros campos dele.
    const setGroup = <K extends 'taker' | 'intermediary' | 'service' | 'values'>(group: K, patch: Partial<InvoiceForm[K]>) =>
        form.setData((data) => ({ ...data, [group]: { ...data[group], ...patch } }));
    const setNested = <K extends 'service' | 'values', G extends keyof InvoiceForm[K]>(group: K, key: G, patch: Partial<InvoiceForm[K][G]>) =>
        form.setData((data) => ({
            ...data,
            [group]: { ...data[group], [key]: { ...(data[group][key] as object), ...patch } },
        }));

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.transform((data) => payload(data, target !== null));
        form.post(target ? route('invoices.replace', target.id) : route('invoices.store'), { preserveScroll: true });
    };

    const ready = issuer?.ready_to_issue ?? false;
    const codeCheck = useServiceCodeCheck(
        {
            national: form.data.service.national_tax_code,
            municipal: form.data.service.municipal_tax_code,
            city: form.data.service.city_code,
            competence: form.data.competence_date,
        },
        ready,
    );
    const isMei = issuer?.simples_nacional === SIMPLES_MEI;
    const defaultService = issuer?.default_service ?? null;
    const { service, values } = form.data;
    const generalError = errors.issuer ?? errors.dps ?? errors.substitution;
    const pageTitle = retryOf ? 'Corrigir e reenviar NFS-e' : replacing ? 'Substituir NFS-e' : 'Nova NFS-e';
    const exporting = values.iss_taxation === ISS_EXPORT;
    const resultCountry = countryName(service.result_country);

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais', href: route('invoices.index') }, { title: 'Nova nota' }]} width="narrow">
            <Head title={pageTitle} />

            <PageHeader
                title={retryOf ? `Corrigir DPS nº ${retryOf.dps_number}` : replacing ? `Substituir NFS-e nº ${replacing.nfse_number}` : 'Nova NFS-e'}
                description={
                    issuer
                        ? `Emitida por ${issuer.legal_name} (${formatCnpj(issuer.cnpj)}), DPS nº ${issuer.next_dps_number}.`
                        : 'Configure o emitente para emitir notas.'
                }
            />

            {!ready && (
                <div className="flex flex-col gap-3 rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p>O emitente precisa estar configurado e com certificado digital válido para emitir.</p>
                    <Button asChild size="sm" variant="outline" className="shrink-0 bg-card text-foreground">
                        <Link href={route('invoices.issuer.edit')}>
                            <Building2 /> Configurar emitente
                        </Link>
                    </Button>
                </div>
            )}

            {issuer && (
                <p
                    className={cn(
                        'rounded-lg border px-4 py-3 text-sm',
                        issuer.environment === 'production'
                            ? 'border-rejected/30 bg-rejected-soft text-rejected'
                            : 'bg-muted/40 text-muted-foreground',
                    )}
                    role="note"
                >
                    {issuer.environment === 'production'
                        ? 'Ambiente de produção: a nota tem validade fiscal e o ISS passa a ser devido.'
                        : 'Produção restrita: a nota é de teste e não tem validade fiscal.'}
                </p>
            )}

            {issuer?.agreement_situation && issuer.agreement_situation !== 'national_emitter' && (
                <div className="flex gap-3 rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending" role="status">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        {issuer.agreement_situation === 'adn_only'
                            ? `${issuer.city_name ?? 'O município do emitente'} não libera os emissores nacionais neste ambiente: a prefeitura mantém sistema próprio e a Sefin Nacional tende a recusar a DPS.`
                            : `${issuer.city_name ?? 'O município do emitente'} não tem convênio ativo com o Sistema Nacional neste ambiente.`}{' '}
                        <Link href={route('invoices.agreements')} className="font-medium underline underline-offset-4">
                            Ver municípios
                        </Link>
                    </p>
                </div>
            )}

            {retryOf && retryOf.rejection.length > 0 && (
                <section className="rounded-lg border border-rejected/30 bg-rejected-soft px-4 py-3 text-sm text-rejected" role="note">
                    <p className="font-medium">A Sefin rejeitou a DPS nº {retryOf.dps_number} por:</p>
                    <ul className="mt-1.5 grid gap-1">
                        {retryOf.rejection.map((r, index) => (
                            <li key={index}>
                                {r.code && <span className="font-mono font-medium">{r.code}</span>} {r.message}
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 text-rejected/80">Os dados abaixo vieram dela. Corrija e emita: sai uma DPS nova, e a rejeitada fica no histórico.</p>
                </section>
            )}

            {generalError && (
                <div className="flex gap-3 rounded-lg border border-rejected/30 bg-rejected-soft px-4 py-3 text-sm text-rejected" role="alert">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>{generalError}</p>
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <fieldset disabled={!ready} className="contents">
                    {target && (
                        <>
                            <FormSection
                                title="Substituição"
                                description="A nova nota leva a chave da original. Quando a Sefin gerar a substituta, a original é cancelada por substituição."
                            >
                                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                                    Substitui a NFS-e nº <span className="font-medium">{target.nfse_number}</span>
                                    {target.issued_at && <>, emitida em {formatDateTime(target.issued_at)}</>}
                                    <span className="mt-1 block font-mono text-xs break-all text-muted-foreground">{formatAccessKey(target.access_key)}</span>
                                </p>
                                <Field id="substitution_reason_code" label="Motivo" error={errors['substitution.reason_code']} className="sm:max-w-md">
                                    <Select
                                        value={form.data.substitution.reason_code}
                                        onValueChange={(v) => form.setData((data) => ({ ...data, substitution: { ...data.substitution, reason_code: v } }))}
                                    >
                                        <SelectTrigger id="substitution_reason_code" aria-invalid={Boolean(errors['substitution.reason_code'])}>
                                            <SelectValue placeholder="Escolha o motivo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(options.substitution_reason).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    id="substitution_reason"
                                    label="Descrição do motivo"
                                    optional={form.data.substitution.reason_code !== '99'}
                                    hint={`Entre 15 e 255 caracteres. ${form.data.substitution.reason.trim().length}/255`}
                                    error={errors['substitution.reason']}
                                >
                                    <Textarea
                                        id="substitution_reason"
                                        value={form.data.substitution.reason}
                                        onChange={(e) => form.setData((data) => ({ ...data, substitution: { ...data.substitution, reason: e.target.value } }))}
                                        rows={2}
                                        maxLength={255}
                                        aria-invalid={Boolean(errors['substitution.reason'])}
                                    />
                                </Field>
                            </FormSection>

                            <Separator />
                        </>
                    )}

                    <FormSection title="Tomador" description="Quem contratou o serviço. CPF ou CNPJ, com ou sem máscara; estrangeiro vai pelo NIF.">
                        <PersonFields
                            idPrefix="taker"
                            errorKey="taker"
                            value={form.data.taker}
                            onChange={(patch) => setGroup('taker', patch)}
                            errors={errors}
                            nifAbsence={options.nif_absence}
                        />
                    </FormSection>

                    <Separator />

                    <FormSection title="Intermediário" description="Quem intermediou a contratação, quando houver. Pode ser o responsável pela retenção do ISS.">
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="has_intermediary"
                                checked={form.data.has_intermediary}
                                onCheckedChange={(checked) => form.setData('has_intermediary', checked === true)}
                            />
                            <Label htmlFor="has_intermediary" className="font-normal">
                                Houve intermediário
                            </Label>
                        </div>
                        {form.data.has_intermediary && (
                            <PersonFields
                                idPrefix="intermediary"
                                errorKey="intermediary"
                                value={form.data.intermediary}
                                onChange={(patch) => setGroup('intermediary', patch)}
                                errors={errors}
                                nifAbsence={options.nif_absence}
                            />
                        )}
                        {form.data.has_intermediary && (
                            <Field
                                id="intermediary_received"
                                label="Valor recebido pelo intermediário (R$)"
                                optional
                                hint="Quando o intermediário recebeu do tomador um valor diferente do valor do serviço."
                                error={errors['values.intermediary_received']}
                                className="sm:max-w-sm"
                            >
                                <Input
                                    id="intermediary_received"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={values.intermediary_received}
                                    onChange={(e) => setGroup('values', { intermediary_received: e.target.value })}
                                />
                            </Field>
                        )}
                    </FormSection>

                    <Separator />

                    <FormSection title="Serviço" description="O código de tributação nacional vem da lista da LC 116 com o desdobramento nacional (6 dígitos).">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field id="national_tax_code" label="Tributação nacional" hint={defaultService?.national_tax_code ? `Vazio usa o padrão: ${defaultService.national_tax_code}` : 'Ex.: 01.07.01'} error={errors['service.national_tax_code']}>
                                <Input
                                    id="national_tax_code"
                                    value={service.national_tax_code}
                                    onChange={(e) => setGroup('service', { national_tax_code: e.target.value })}
                                    aria-invalid={Boolean(errors['service.national_tax_code'])}
                                    placeholder={defaultService?.national_tax_code ?? undefined}
                                    required={!defaultService?.national_tax_code}
                                />
                            </Field>
                            <Field id="municipal_tax_code" label="Trib. municipal" hint="Opcional, 3 dígitos" error={errors['service.municipal_tax_code']}>
                                <Input
                                    id="municipal_tax_code"
                                    value={service.municipal_tax_code}
                                    onChange={(e) => setGroup('service', { municipal_tax_code: e.target.value })}
                                    inputMode="numeric"
                                />
                            </Field>
                            <Field id="nbs_code" label="Código NBS" hint="Opcional, 9 dígitos" error={errors['service.nbs_code']}>
                                <Input
                                    id="nbs_code"
                                    value={service.nbs_code}
                                    onChange={(e) => setGroup('service', { nbs_code: e.target.value })}
                                    inputMode="numeric"
                                />
                            </Field>
                        </div>
                        {codeCheck?.status === 'parametrized' && (
                            <p className="-mt-2 flex items-start gap-2 text-sm text-signed" role="status">
                                <CircleCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <span>
                                    {codeCheck.service_code} parametrizado em {issuer?.city_name ?? 'no município do emitente'}
                                    {codeCheck.rate !== null && <> · alíquota {formatRate(codeCheck.rate)}</>}
                                </span>
                            </p>
                        )}
                        {codeCheck?.status === 'not_parametrized' && (
                            <p className="-mt-2 flex items-start gap-2 text-sm text-rejected" role="alert">
                                <CircleX className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <span>{codeCheck.message}</span>
                            </p>
                        )}
                        <Field
                            id="description"
                            label="Descrição do serviço"
                            hint={defaultService?.description ? `Vazio usa o padrão do emitente: ${defaultService.description}` : undefined}
                            error={errors['service.description']}
                        >
                            <Textarea
                                id="description"
                                value={service.description}
                                onChange={(e) => setGroup('service', { description: e.target.value })}
                                rows={4}
                                maxLength={2000}
                                aria-invalid={Boolean(errors['service.description'])}
                                required={!defaultService?.description}
                            />
                        </Field>
                        <Field
                            id="service_city_code"
                            label="Município da prestação"
                            hint={issuer?.city_name ? `Padrão: ${issuer.city_name}, o município do emitente.` : undefined}
                            error={errors['service.city_code']}
                            className="sm:max-w-sm"
                        >
                            <MunicipalityInput
                                id="service_city_code"
                                value={service.city_code}
                                onChange={(code) => setGroup('service', { city_code: code })}
                                invalid={Boolean(errors['service.city_code'])}
                            />
                        </Field>

                        <details
                            className="rounded-lg border px-4 py-3 [&[open]]:pb-4"
                            open={Boolean(
                                form.data.service_extras.notes ||
                                    form.data.service_extras.construction_mode !== 'none' ||
                                    form.data.service_extras.has_event ||
                                    Object.keys(errors).some((key) => /^service\.(additional_info|construction|event)/.test(key)),
                            )}
                        >
                            <summary className="cursor-pointer text-sm font-medium">
                                Informações complementares, obra e evento <span className="font-normal text-muted-foreground">(opcional)</span>
                            </summary>
                            <div className="mt-4">
                                <ServiceExtrasFields
                                    value={form.data.service_extras}
                                    onChange={(patch) => form.setData((data) => ({ ...data, service_extras: { ...data.service_extras, ...patch } }))}
                                    errors={errors}
                                />
                            </div>
                        </details>
                    </FormSection>

                    <Separator />

                    <FormSection title="Valores e ISS" description="Na dúvida sobre a alíquota, deixe em branco: em município conveniado a Sefin usa a parametrizada.">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field id="amount" label="Valor (R$)" error={errors['values.amount']}>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={values.amount}
                                    onChange={(e) => setGroup('values', { amount: e.target.value })}
                                    aria-invalid={Boolean(errors['values.amount'])}
                                    required
                                />
                            </Field>
                            <Field id="iss_rate" label="Alíquota ISS (%)" hint="Opcional" error={errors['values.iss_rate']}>
                                <Input
                                    id="iss_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="5"
                                    value={values.iss_rate}
                                    onChange={(e) => setGroup('values', { iss_rate: e.target.value })}
                                    disabled={isMei}
                                />
                            </Field>
                            <Field id="competence_date" label="Competência" error={errors.competence_date}>
                                <Input
                                    id="competence_date"
                                    type="date"
                                    max={today()}
                                    value={form.data.competence_date}
                                    onChange={(e) => form.setData('competence_date', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="iss_taxation" label="Tributação do ISSQN" error={errors['values.iss_taxation']}>
                                <Select value={values.iss_taxation} onValueChange={(v) => setGroup('values', { iss_taxation: v })}>
                                    <SelectTrigger id="iss_taxation">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(options.iss_taxation).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <div className="grid content-start gap-3 sm:pt-7">
                                <div className="flex items-center gap-2.5">
                                    <Checkbox
                                        id="iss_withheld"
                                        checked={values.iss_withheld}
                                        onCheckedChange={(checked) => setGroup('values', { iss_withheld: checked === true })}
                                    />
                                    <Label htmlFor="iss_withheld" className="font-normal">
                                        ISS retido na fonte
                                    </Label>
                                </div>
                                {values.iss_withheld && form.data.has_intermediary && (
                                    <Field id="iss_withholder" label="Retido por" error={errors['values.iss_withholder']}>
                                        <Select
                                            value={values.iss_withholder}
                                            onValueChange={(v) => setGroup('values', { iss_withholder: v as InvoiceForm['values']['iss_withholder'] })}
                                        >
                                            <SelectTrigger id="iss_withholder">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="taker">Tomador</SelectItem>
                                                <SelectItem value="intermediary">Intermediário</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                )}
                            </div>
                        </div>

                        {values.iss_taxation === ISS_IMMUNE && (
                            <Field id="immunity_type" label="Tipo de imunidade" error={errors['values.immunity_type']}>
                                <Select value={values.immunity_type} onValueChange={(v) => setGroup('values', { immunity_type: v })}>
                                    <SelectTrigger id="immunity_type" aria-invalid={Boolean(errors['values.immunity_type'])}>
                                        <SelectValue placeholder="Escolha a imunidade" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(options.immunity).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        )}

                        {exporting && (
                            <div className="grid gap-4 rounded-lg border px-4 py-4">
                                <p className="text-sm font-medium">Exportação de serviço</p>
                                <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                                    <Field
                                        id="result_country"
                                        label="País do resultado"
                                        hint={resultCountry ?? 'Código ISO, ex.: US'}
                                        error={errors['service.result_country']}
                                    >
                                        <Input
                                            id="result_country"
                                            value={service.result_country}
                                            onChange={(e) => setGroup('service', { result_country: e.target.value.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() })}
                                            maxLength={2}
                                            aria-invalid={Boolean(errors['service.result_country'])}
                                        />
                                    </Field>
                                    <Field id="provision_mode" label="Modo de prestação" error={errors['service.foreign_trade.provision_mode'] ?? errors['service.foreign_trade']}>
                                        <Select
                                            value={service.foreign_trade.provision_mode}
                                            onValueChange={(v) => setNested('service', 'foreign_trade', { provision_mode: v })}
                                        >
                                            <SelectTrigger id="provision_mode">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(options.provision_mode).map(([key, label]) => (
                                                    <SelectItem key={key} value={key}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="currency"
                                        label="Moeda (código BACEN)"
                                        hint="Ex.: 220 dólar dos EUA, 978 euro"
                                        error={errors['service.foreign_trade.currency']}
                                    >
                                        <Input
                                            id="currency"
                                            value={service.foreign_trade.currency}
                                            onChange={(e) => setNested('service', 'foreign_trade', { currency: e.target.value.replace(/\D/g, '').slice(0, 3) })}
                                            inputMode="numeric"
                                        />
                                    </Field>
                                    <Field id="foreign_amount" label="Valor na moeda estrangeira" error={errors['service.foreign_trade.foreign_amount']}>
                                        <Input
                                            id="foreign_amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={service.foreign_trade.foreign_amount}
                                            onChange={(e) => setNested('service', 'foreign_trade', { foreign_amount: e.target.value })}
                                        />
                                    </Field>
                                </div>
                            </div>
                        )}

                        <details className="rounded-lg border px-4 py-3 [&[open]]:pb-4" open={Boolean(values.discounts.unconditional || values.discounts.conditional || values.deduction_type !== 'none')}>
                            <summary className="cursor-pointer text-sm font-medium">
                                Descontos e deduções <span className="font-normal text-muted-foreground">(opcional)</span>
                            </summary>
                            <div className="mt-4 grid gap-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="discount_unconditional"
                                        label="Desconto incondicionado (R$)"
                                        hint="Dado na própria nota; reduz a base do ISS."
                                        error={errors['values.discounts.unconditional']}
                                    >
                                        <Input
                                            id="discount_unconditional"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={values.discounts.unconditional}
                                            onChange={(e) => setNested('values', 'discounts', { unconditional: e.target.value })}
                                        />
                                    </Field>
                                    <Field
                                        id="discount_conditional"
                                        label="Desconto condicionado (R$)"
                                        hint="Depende de evento futuro, como pagamento antecipado."
                                        error={errors['values.discounts.conditional']}
                                    >
                                        <Input
                                            id="discount_conditional"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={values.discounts.conditional}
                                            onChange={(e) => setNested('values', 'discounts', { conditional: e.target.value })}
                                        />
                                    </Field>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field id="deduction_type" label="Dedução da base de cálculo" hint="Só quando a legislação municipal permite.">
                                        <Select
                                            value={values.deduction_type}
                                            onValueChange={(v) =>
                                                setGroup('values', {
                                                    deduction_type: v as DeductionType,
                                                    // Ao escolher documentos, já abre o primeiro para preencher.
                                                    deduction_documents:
                                                        v === 'documents' && values.deduction_documents.length === 0 ? [emptyDeductionDocument()] : values.deduction_documents,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="deduction_type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Sem dedução</SelectItem>
                                                <SelectItem value="amount">Valor (R$)</SelectItem>
                                                <SelectItem value="percent">Percentual (%)</SelectItem>
                                                <SelectItem value="documents">Por documentos</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    {(values.deduction_type === 'amount' || values.deduction_type === 'percent') && (
                                        <Field
                                            id="deduction_value"
                                            label={values.deduction_type === 'percent' ? 'Percentual deduzido (%)' : 'Valor deduzido (R$)'}
                                            error={errors['values.deduction.amount'] ?? errors['values.deduction.percent']}
                                        >
                                            <Input
                                                id="deduction_value"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max={values.deduction_type === 'percent' ? '100' : undefined}
                                                value={values.deduction_value}
                                                onChange={(e) => setGroup('values', { deduction_value: e.target.value })}
                                            />
                                        </Field>
                                    )}
                                </div>
                                {values.deduction_type === 'documents' && (
                                    <DeductionDocuments
                                        value={values.deduction_documents}
                                        onChange={(documents) => setGroup('values', { deduction_documents: documents })}
                                        errors={errors}
                                        kinds={options.deduction_document}
                                        reasons={options.deduction_reason}
                                    />
                                )}
                            </div>
                        </details>

                        <details className="rounded-lg border px-4 py-3 [&[open]]:pb-4" open={hasTaxDetails(values.tax_details)}>
                            <summary className="cursor-pointer text-sm font-medium">
                                Retenções federais, benefício e suspensão do ISS <span className="font-normal text-muted-foreground">(opcional)</span>
                            </summary>
                            <div className="mt-4">
                                <TaxDetailsFields
                                    value={values.tax_details}
                                    onChange={(patch) => setNested('values', 'tax_details', patch)}
                                    amount={values.amount}
                                    errors={errors}
                                    withholdingOptions={options.pis_cofins_withholding}
                                    suspensionOptions={options.iss_suspension}
                                />
                            </div>
                        </details>

                        <details className="rounded-lg border px-4 py-3 [&[open]]:pb-4" open={Boolean(values.ibs_cbs.cst) || hasIbsCbsExtras(values.ibs_cbs_extras)}>
                            <summary className="cursor-pointer text-sm font-medium">
                                IBS e CBS{' '}
                                <span className="font-normal text-muted-foreground">
                                    {issuer?.ibs_cbs
                                        ? `(padrão do emitente: CST ${issuer.ibs_cbs.cst}, cClassTrib ${issuer.ibs_cbs.class_code})`
                                        : '(sem padrão no emitente)'}
                                </span>
                            </summary>
                            <div className="mt-4 grid gap-4">
                                <p className="text-sm text-muted-foreground">
                                    Preencha só para usar nesta nota uma classificação diferente da do emitente. A Sefin calcula base e valores.
                                </p>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {(
                                        [
                                            ['cst', 'CST', 3],
                                            ['class_code', 'cClassTrib', 6],
                                            ['operation_code', 'cIndOp', 6],
                                        ] as const
                                    ).map(([key, label, size]) => (
                                        <Field key={key} id={`ibs_cbs_${key}`} label={label} error={errors[`values.ibs_cbs.${key}`]}>
                                            <Input
                                                id={`ibs_cbs_${key}`}
                                                value={values.ibs_cbs[key]}
                                                onChange={(e) => setNested('values', 'ibs_cbs', { [key]: e.target.value.replace(/\D/g, '').slice(0, size) })}
                                                inputMode="numeric"
                                                placeholder={issuer?.ibs_cbs?.[key]}
                                            />
                                        </Field>
                                    ))}
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <Checkbox
                                        id="ibs_cbs_final_consumer"
                                        checked={values.ibs_cbs.final_consumer}
                                        onCheckedChange={(checked) => setNested('values', 'ibs_cbs', { final_consumer: checked === true })}
                                    />
                                    <Label htmlFor="ibs_cbs_final_consumer" className="font-normal">
                                        Operação de uso ou consumo pessoal (art. 57 da LC 214/2025)
                                    </Label>
                                </div>
                                <Separator />
                                <IbsCbsExtrasFields
                                    value={values.ibs_cbs_extras}
                                    onChange={(patch) => setNested('values', 'ibs_cbs_extras', patch)}
                                    errors={errors}
                                    options={{
                                        operation: options.ibs_cbs_operation,
                                        government: options.government_entity,
                                        reimbursement: options.reimbursement_type,
                                        dfe: options.dfe_type,
                                    }}
                                />
                            </div>
                        </details>

                        {!isMei && (
                            <details className="rounded-lg border px-4 py-3 [&[open]]:pb-4">
                                <summary className="cursor-pointer text-sm font-medium">
                                    Tributos aproximados <span className="font-normal text-muted-foreground">(Lei 12.741, opcional)</span>
                                </summary>
                                <div className="mt-4 grid gap-4 sm:grid-cols-3">
                                    {(['federal', 'state', 'municipal'] as const).map((sphere) => (
                                        <Field
                                            key={sphere}
                                            id={`tax_${sphere}`}
                                            label={{ federal: 'Federal (%)', state: 'Estadual (%)', municipal: 'Municipal (%)' }[sphere]}
                                            error={errors[`values.approximate_taxes.${sphere}`]}
                                        >
                                            <Input
                                                id={`tax_${sphere}`}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                value={values.approximate_taxes[sphere]}
                                                onChange={(e) => setNested('values', 'approximate_taxes', { [sphere]: e.target.value })}
                                            />
                                        </Field>
                                    ))}
                                </div>
                            </details>
                        )}

                        <Field
                            id="external_id"
                            label="Referência interna"
                            optional
                            hint="Número do pedido ou contrato no seu sistema. A API usa para não emitir duas vezes."
                            error={errors.external_id}
                            className="sm:max-w-sm"
                        >
                            <Input id="external_id" value={form.data.external_id} onChange={(e) => form.setData('external_id', e.target.value)} />
                        </Field>

                        <div className="flex items-center gap-2.5">
                            <Checkbox id="notify_taker" checked={form.data.notify_taker} onCheckedChange={(checked) => form.setData('notify_taker', checked === true)} />
                            <Label htmlFor="notify_taker" className="font-normal">
                                Enviar a nota por e-mail ao tomador{form.data.taker.email ? ` (${form.data.taker.email})` : ' (informe o e-mail do tomador)'}
                            </Label>
                        </div>
                    </FormSection>

                    <Separator />

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button asChild variant="outline">
                            <Link href={route('invoices.index')}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || !ready}>
                            {form.processing ? <Spinner /> : <Send />}
                            {form.processing ? 'Enviando à Sefin…' : target ? 'Emitir substituta' : 'Emitir nota'}
                        </Button>
                    </div>
                </fieldset>
            </form>
        </AppLayout>
    );
}
