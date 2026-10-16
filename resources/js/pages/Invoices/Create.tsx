import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
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
import { formatCnpj } from '@/lib/fiscal';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, Send, TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

interface IssuerSummary {
    legal_name: string;
    cnpj: string;
    city_code: string;
    city_name: string | null;
    environment: 'restricted' | 'production';
    simples_nacional: number;
    next_dps_number: number;
    ready_to_issue: boolean;
}

interface Props {
    issuer: IssuerSummary | null;
    options: {
        iss_taxation: Record<string, string>;
        environment: Record<string, string>;
    };
    [key: string]: unknown;
}

interface InvoiceForm {
    external_id: string;
    competence_date: string;
    taker: {
        document: string;
        name: string;
        email: string;
        phone: string;
        municipal_registration: string;
        address: {
            postal_code: string;
            street: string;
            number: string;
            complement: string;
            district: string;
            city_code: string;
        };
    };
    service: {
        national_tax_code: string;
        municipal_tax_code: string;
        nbs_code: string;
        description: string;
        city_code: string;
    };
    values: {
        amount: string;
        iss_rate: string;
        iss_withheld: boolean;
        iss_taxation: string;
        approximate_taxes: { federal: string; state: string; municipal: string };
    };
}

const SIMPLES_MEI = 2;

function today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function Field({
    id,
    label,
    optional,
    hint,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    optional?: boolean;
    hint?: ReactNode;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid content-start gap-2', className)}>
            <Label htmlFor={id}>
                {label} {optional && <span className="font-normal text-muted-foreground">(opcional)</span>}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function InvoicesCreate({ issuer, options }: Props) {
    const form = useForm<InvoiceForm>({
        external_id: '',
        competence_date: today(),
        taker: {
            document: '',
            name: '',
            email: '',
            phone: '',
            municipal_registration: '',
            address: { postal_code: '', street: '', number: '', complement: '', district: '', city_code: '' },
        },
        service: {
            national_tax_code: '',
            municipal_tax_code: '',
            nbs_code: '',
            description: '',
            city_code: issuer?.city_code ?? '',
        },
        values: {
            amount: '',
            iss_rate: '',
            iss_withheld: false,
            iss_taxation: '1',
            approximate_taxes: { federal: '', state: '', municipal: '' },
        },
    });

    const errors = form.errors as Record<string, string | undefined>;

    // Atualiza um grupo do formulário sem perder os outros campos dele.
    const setGroup = <K extends 'taker' | 'service' | 'values'>(group: K, patch: Partial<InvoiceForm[K]>) =>
        form.setData((data) => ({ ...data, [group]: { ...data[group], ...patch } }));
    const setAddress = (patch: Partial<InvoiceForm['taker']['address']>) =>
        form.setData((data) => ({ ...data, taker: { ...data.taker, address: { ...data.taker.address, ...patch } } }));
    const setTaxes = (patch: Partial<InvoiceForm['values']['approximate_taxes']>) =>
        form.setData((data) => ({
            ...data,
            values: { ...data.values, approximate_taxes: { ...data.values.approximate_taxes, ...patch } },
        }));

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('invoices.store'), { preserveScroll: true });
    };

    const ready = issuer?.ready_to_issue ?? false;
    const isMei = issuer?.simples_nacional === SIMPLES_MEI;
    const { taker, service, values } = form.data;
    const generalError = errors.issuer ?? errors.dps;

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais', href: route('invoices.index') }, { title: 'Nova nota' }]} width="narrow">
            <Head title="Nova NFS-e" />

            <PageHeader
                title="Nova NFS-e"
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

            {generalError && (
                <div className="flex gap-3 rounded-lg border border-rejected/30 bg-rejected-soft px-4 py-3 text-sm text-rejected" role="alert">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>{generalError}</p>
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <fieldset disabled={!ready} className="contents">
                    <FormSection title="Tomador" description="Quem contratou o serviço. CPF ou CNPJ, com ou sem máscara.">
                        <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                            <Field id="taker_document" label="CPF ou CNPJ" error={errors['taker.document']}>
                                <Input
                                    id="taker_document"
                                    value={taker.document}
                                    onChange={(e) => setGroup('taker', { document: e.target.value })}
                                    aria-invalid={Boolean(errors['taker.document'])}
                                    required
                                />
                            </Field>
                            <Field id="taker_name" label="Nome ou razão social" error={errors['taker.name']}>
                                <Input
                                    id="taker_name"
                                    value={taker.name}
                                    onChange={(e) => setGroup('taker', { name: e.target.value })}
                                    aria-invalid={Boolean(errors['taker.name'])}
                                    required
                                />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="taker_email" label="E-mail" optional error={errors['taker.email']}>
                                <Input
                                    id="taker_email"
                                    type="email"
                                    value={taker.email}
                                    onChange={(e) => setGroup('taker', { email: e.target.value })}
                                    aria-invalid={Boolean(errors['taker.email'])}
                                />
                            </Field>
                            <Field id="taker_phone" label="Telefone" optional error={errors['taker.phone']}>
                                <Input
                                    id="taker_phone"
                                    value={taker.phone}
                                    onChange={(e) => setGroup('taker', { phone: e.target.value })}
                                    inputMode="tel"
                                    aria-invalid={Boolean(errors['taker.phone'])}
                                />
                            </Field>
                        </div>

                        <details className="group rounded-lg border px-4 py-3 [&[open]]:pb-4">
                            <summary className="cursor-pointer text-sm font-medium">
                                Endereço do tomador <span className="font-normal text-muted-foreground">(opcional)</span>
                            </summary>
                            <div className="mt-4 grid gap-4">
                                <div className="grid gap-4 sm:grid-cols-[9rem_1fr_6rem]">
                                    <Field id="postal_code" label="CEP" error={errors['taker.address.postal_code']}>
                                        <Input
                                            id="postal_code"
                                            value={taker.address.postal_code}
                                            onChange={(e) => setAddress({ postal_code: e.target.value })}
                                            inputMode="numeric"
                                        />
                                    </Field>
                                    <Field id="street" label="Logradouro" error={errors['taker.address.street']}>
                                        <Input id="street" value={taker.address.street} onChange={(e) => setAddress({ street: e.target.value })} />
                                    </Field>
                                    <Field id="number" label="Número" error={errors['taker.address.number']}>
                                        <Input id="number" value={taker.address.number} onChange={(e) => setAddress({ number: e.target.value })} />
                                    </Field>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-[1fr_1fr_9rem]">
                                    <Field id="complement" label="Complemento" optional error={errors['taker.address.complement']}>
                                        <Input id="complement" value={taker.address.complement} onChange={(e) => setAddress({ complement: e.target.value })} />
                                    </Field>
                                    <Field id="district" label="Bairro" error={errors['taker.address.district']}>
                                        <Input id="district" value={taker.address.district} onChange={(e) => setAddress({ district: e.target.value })} />
                                    </Field>
                                    <Field id="address_city_code" label="Código IBGE" error={errors['taker.address.city_code']}>
                                        <Input
                                            id="address_city_code"
                                            value={taker.address.city_code}
                                            onChange={(e) => setAddress({ city_code: e.target.value.replace(/\D/g, '').slice(0, 7) })}
                                            inputMode="numeric"
                                        />
                                    </Field>
                                </div>
                            </div>
                        </details>
                    </FormSection>

                    <Separator />

                    <FormSection title="Serviço" description="O código de tributação nacional vem da lista da LC 116 com o desdobramento nacional (6 dígitos).">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                id="national_tax_code"
                                label="Tributação nacional"
                                hint="Ex.: 01.07.01"
                                error={errors['service.national_tax_code']}
                            >
                                <Input
                                    id="national_tax_code"
                                    value={service.national_tax_code}
                                    onChange={(e) => setGroup('service', { national_tax_code: e.target.value })}
                                    aria-invalid={Boolean(errors['service.national_tax_code'])}
                                    required
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
                        <Field id="description" label="Descrição do serviço" error={errors['service.description']}>
                            <Textarea
                                id="description"
                                value={service.description}
                                onChange={(e) => setGroup('service', { description: e.target.value })}
                                rows={4}
                                maxLength={2000}
                                aria-invalid={Boolean(errors['service.description'])}
                                required
                            />
                        </Field>
                        <Field
                            id="service_city_code"
                            label="Município da prestação (código IBGE)"
                            hint={issuer?.city_name ? `Padrão: ${issuer.city_name}, o município do emitente.` : undefined}
                            error={errors['service.city_code']}
                            className="sm:max-w-xs"
                        >
                            <Input
                                id="service_city_code"
                                value={service.city_code}
                                onChange={(e) => setGroup('service', { city_code: e.target.value.replace(/\D/g, '').slice(0, 7) })}
                                inputMode="numeric"
                            />
                        </Field>
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
                            <div className="flex items-center gap-2.5 sm:pt-7">
                                <Checkbox
                                    id="iss_withheld"
                                    checked={values.iss_withheld}
                                    onCheckedChange={(checked) => setGroup('values', { iss_withheld: checked === true })}
                                />
                                <Label htmlFor="iss_withheld" className="font-normal">
                                    ISS retido pelo tomador
                                </Label>
                            </div>
                        </div>

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
                                                onChange={(e) => setTaxes({ [sphere]: e.target.value })}
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
                    </FormSection>

                    <Separator />

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button asChild variant="outline">
                            <Link href={route('invoices.index')}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || !ready}>
                            {form.processing ? <Spinner /> : <Send />}
                            {form.processing ? 'Enviando à Sefin…' : 'Emitir nota'}
                        </Button>
                    </div>
                </fieldset>
            </form>
        </AppLayout>
    );
}
