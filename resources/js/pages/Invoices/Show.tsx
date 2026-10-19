import { countryName } from '@/components/invoices/field';
import InputError from '@/components/input-error';
import { StatusBadge, type InvoiceStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatAccessKey, formatMoney, formatTaxDocument } from '@/lib/fiscal';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, Download, FileText, Mail, Pencil, RefreshCw, Replace, Scale, Send, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface SefinMessage {
    code: string | null;
    message: string;
    complement: string | null;
}

interface Party {
    document?: string | null;
    nif?: string | null;
    nif_absence_reason?: string | null;
    name: string;
    email?: string | null;
    phone?: string | null;
    address?: {
        country?: string | null;
        street: string;
        number: string;
        district: string;
        postal_code: string;
        city_code?: string | null;
        city?: string | null;
        state?: string | null;
    } | null;
}

interface InvoiceData {
    id: string;
    external_id: string | null;
    status: InvoiceStatus;
    environment: 'restricted' | 'production';
    dps: { id: string; series: string; number: number; emitted_at: string | null };
    competence_date: string | null;
    access_key: string | null;
    nfse_number: string | null;
    issued_at: string | null;
    taker: Party;
    intermediary: Party | null;
    service: {
        national_tax_code: string;
        municipal_tax_code?: string | null;
        nbs_code?: string | null;
        description: string;
        city_code?: string | null;
        result_country?: string | null;
        additional_info?: { notes?: string; reference?: string; order?: string; technical_document?: string } | null;
        construction?: { work_code?: string; cib?: string; registration?: string; address?: { street: string; number: string; postal_code: string } } | null;
        event?: { name: string; starts_on: string; ends_on: string; event_id?: string } | null;
        foreign_trade?: { currency: string; foreign_amount: string } | null;
    };
    values: {
        amount: string;
        iss_rate: string | null;
        iss_withheld: boolean;
        iss_withholder: 'taker' | 'intermediary' | null;
        intermediary_received: string | null;
        federal_taxes: {
            pis_cofins?: { cst: string; pis_amount: string | null; cofins_amount: string | null } | null;
            irrf?: { amount: string | null } | null;
            csll?: { amount: string | null } | null;
            inss?: { amount: string | null } | null;
        } | null;
        municipal_benefit: { number: string; reduction_amount?: string; reduction_percent?: string } | null;
        iss_suspension: { type: string; process_number: string } | null;
        ibs_cbs: { cst: string; class_code: string; operation_code: string } | null;
        iss_taxation: number;
        discounts: { unconditional: string | null; conditional: string | null } | null;
        deduction: {
            percent: string | null;
            amount: string | null;
            documents: { kind: string; access_key?: string; number?: string; reason: string; issued_on: string; amount: string }[] | null;
        } | null;
    };
    rejection: SefinMessage[] | null;
    last_error: string | null;
    last_attempt_at: string | null;
    cancellation: { reason_code: number; reason: string; cancelled_at: string } | null;
    substitution: { replaces_invoice_id: string; replaces_access_key: string | null; reason_code: string; reason: string | null } | null;
    replacement: { invoice_id: string | null; access_key: string | null; replaced_at: string } | null;
    delivery: { notify_taker: boolean; emailed_at: string | null; last_error: string | null };
    cancellation_review: { status: 'requested' | 'approved' | 'denied'; reason_code?: string; reason?: string; requested_at?: string; decided_at?: string } | null;
    taker_manifestation: { type: 'confirmed' | 'rejected' | 'rejection_annulled'; by: string; reason_code?: string; reason?: string; at: string } | null;
    blocked_at: string | null;
    has_xml: boolean;
    created_at: string;
}

interface InvoiceEvent {
    nsu: number;
    code: string | null;
    label: string;
    reason: string | null;
    processed_at: string | null;
}

interface Props {
    invoice: InvoiceData;
    events: InvoiceEvent[];
    canCancel: boolean;
    canReplace: boolean;
    cities: Record<string, string>;
    options: {
        cancel_reason: Record<string, string>;
        iss_taxation: Record<string, string>;
        substitution_reason: Record<string, string>;
        deduction_document: Record<string, string>;
    };
    [key: string]: unknown;
}

function Meta({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium break-words text-foreground">{children}</dd>
        </div>
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card shadow-xs">
            <h2 className="border-b px-5 py-3.5 text-base font-medium md:px-6">{title}</h2>
            <dl className="grid gap-x-6 gap-y-4 px-5 py-4 sm:grid-cols-2 md:px-6">{children}</dl>
        </section>
    );
}

function hasFiscalDetails(invoice: InvoiceData): boolean {
    const { service, values } = invoice;
    return Boolean(
        service.additional_info ||
            service.construction ||
            service.event ||
            values.intermediary_received ||
            values.federal_taxes ||
            values.municipal_benefit ||
            values.iss_suspension ||
            values.ibs_cbs,
    );
}

function PartyPanel({ title, party, cities }: { title: string; party: Party; cities: Record<string, string> }) {
    const address = party.address;
    const foreign = Boolean(address?.country && address.country !== 'BR');
    const identification = party.document
        ? formatTaxDocument(party.document)
        : party.nif
          ? `NIF ${party.nif}`
          : party.nif_absence_reason === '1'
            ? 'Estrangeiro dispensado do NIF'
            : 'Estrangeiro sem exigência de NIF';

    return (
        <Panel title={title}>
            <Meta label="Nome ou razão social" className="sm:col-span-2">
                {party.name}
            </Meta>
            <Meta label={party.document ? 'CPF/CNPJ' : 'Identificação'}>{identification}</Meta>
            <Meta label="E-mail">{party.email || '—'}</Meta>
            {address && (
                <Meta label="Endereço" className="sm:col-span-2">
                    {address.street}, {address.number} · {address.district} ·{' '}
                    {foreign
                        ? `${address.city} / ${address.state} · ${countryName(address.country ?? '') ?? address.country} · ${address.postal_code}`
                        : `${(address.city_code && cities[address.city_code]) ?? address.city_code} · CEP ${address.postal_code}`}
                </Meta>
            )}
        </Panel>
    );
}

function EmailDialog({ invoice }: { invoice: InvoiceData }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ emails: invoice.taker.email ?? '' });
    const errors = form.errors as Record<string, string | undefined>;
    const firstError = errors.emails ?? Object.entries(errors).find(([key]) => key.startsWith('emails.'))?.[1];

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('invoices.email', invoice.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Mail /> Enviar por e-mail
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Enviar NFS-e nº {invoice.nfse_number} por e-mail</DialogTitle>
                        <DialogDescription>
                            {invoice.status === 'issued'
                                ? 'Vai com o DANFSe e o XML anexados.'
                                : 'A nota não vale mais: vai o aviso de cancelamento ou substituição.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="emails">Para</Label>
                        <Input
                            id="emails"
                            value={form.data.emails}
                            onChange={(e) => form.setData('emails', e.target.value)}
                            placeholder="email@exemplo.com, outro@exemplo.com"
                            aria-invalid={Boolean(firstError)}
                        />
                        <p className="text-xs text-muted-foreground">Até 10 endereços, separados por vírgula.</p>
                        <InputError message={firstError} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Voltar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : <Send />} Enviar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Cancelamento comum ou, fora do prazo, o pedido para a prefeitura analisar (evento 101103). */
function CancelDialog({ invoice, reasons, review = false }: { invoice: InvoiceData; reasons: Record<string, string>; review?: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason_code: '1', reason: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route(review ? 'invoices.cancellation-review' : 'invoices.cancel', invoice.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {review ? (
                    <Button variant="ghost" className="text-muted-foreground">
                        <Scale /> Cancelamento fora do prazo
                    </Button>
                ) : (
                    <Button variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive">
                        <Ban /> Cancelar nota
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {review ? `Pedir análise fiscal para cancelar a NFS-e nº ${invoice.nfse_number}` : `Cancelar NFS-e nº ${invoice.nfse_number}?`}
                        </DialogTitle>
                        <DialogDescription>
                            {review
                                ? 'Quando o prazo de cancelamento já passou, a prefeitura decide. O pedido vai assinado para a Sefin Nacional e a decisão chega pelo ADN.'
                                : 'O pedido de cancelamento vai assinado para a Sefin Nacional. Depois de aceito, a nota não volta a valer.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="reason_code">Motivo</Label>
                        <Select value={form.data.reason_code} onValueChange={(v) => form.setData('reason_code', v)}>
                            <SelectTrigger id="reason_code">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(reasons).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.reason_code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="reason">Justificativa</Label>
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            rows={3}
                            minLength={15}
                            maxLength={255}
                            aria-invalid={Boolean(errors.reason)}
                            required
                        />
                        <p className="text-xs text-muted-foreground">Entre 15 e 255 caracteres. {form.data.reason.trim().length}/255</p>
                        <InputError message={errors.reason ?? errors.cancel ?? errors.review} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Voltar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            {form.processing && <Spinner />} {review ? 'Enviar pedido' : 'Cancelar nota'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function InvoicesShow({ invoice, events, canCancel, canReplace, cities, options }: Props) {
    const [syncing, setSyncing] = useState(false);
    const title = invoice.nfse_number ? `NFS-e nº ${invoice.nfse_number}` : `DPS nº ${invoice.dps.number}`;

    const sync = () =>
        router.post(route('invoices.sync', invoice.id), {}, {
            preserveScroll: true,
            onStart: () => setSyncing(true),
            onFinish: () => setSyncing(false),
        });

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais', href: route('invoices.index') }, { title }]}>
            <Head title={title} />

            <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                        <StatusBadge status={invoice.status} kind="invoice" />
                        {invoice.environment === 'restricted' && (
                            <span className="rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground">Produção restrita</span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {invoice.taker.name} · {formatMoney(invoice.values.amount)}
                    </p>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {invoice.status === 'issued' && canCancel && <CancelDialog invoice={invoice} reasons={options.cancel_reason} />}
                    {invoice.status === 'issued' && canCancel && invoice.cancellation_review?.status !== 'requested' && (
                        <CancelDialog invoice={invoice} reasons={options.cancel_reason} review />
                    )}
                    {invoice.status === 'issued' && canReplace && (
                        <Button variant="outline" asChild>
                            <Link href={route('invoices.create', { replace: invoice.id })}>
                                <Replace /> Substituir
                            </Link>
                        </Button>
                    )}
                    {invoice.has_xml && (
                        <>
                            <EmailDialog invoice={invoice} />
                            <Button variant="outline" asChild>
                                <a href={route('invoices.xml', invoice.id)}>
                                    <Download /> Baixar XML
                                </a>
                            </Button>
                            <Button asChild>
                                <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener">
                                    <FileText /> Ver DANFSe
                                </a>
                            </Button>
                        </>
                    )}
                    {invoice.status === 'pending' && (
                        <Button onClick={sync} disabled={syncing}>
                            {syncing ? <Spinner /> : <RefreshCw />} Sincronizar com a Sefin
                        </Button>
                    )}
                </div>
            </header>

            {invoice.status === 'pending' && (
                <div className="flex gap-3 rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending" role="status">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                        <p className="font-medium">A Sefin Nacional não confirmou esta DPS.</p>
                        <p className="mt-1">
                            Sincronizar consulta se a nota foi gerada e, se não foi, reenvia a mesma DPS. Não emita outra nota para o mesmo
                            serviço antes disso.
                        </p>
                        {invoice.last_error && (
                            <p className="mt-1 text-pending/80">
                                Última tentativa {formatDateTime(invoice.last_attempt_at)}: {invoice.last_error}
                            </p>
                        )}
                    </div>
                </div>
            )}

            {invoice.status === 'rejected' && invoice.rejection && (
                <section className="rounded-xl border border-rejected/30 bg-rejected-soft px-5 py-4 text-sm text-rejected md:px-6" role="alert">
                    <h2 className="font-medium">A Sefin Nacional rejeitou a DPS</h2>
                    <ul className="mt-2 grid gap-1.5">
                        {invoice.rejection.map((r, index) => (
                            <li key={index}>
                                {r.code && <span className="font-mono font-medium">{r.code}</span>} {r.message}
                                {r.complement && <span className="text-rejected/80"> ({r.complement})</span>}
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-rejected/80">Corrija os dados e reenvie. Sai uma DPS nova; esta fica registrada como rejeitada.</p>
                        <Button asChild size="sm" className="shrink-0">
                            <Link href={route('invoices.create', { from: invoice.id })}>
                                <Pencil /> Corrigir e reenviar
                            </Link>
                        </Button>
                    </div>
                </section>
            )}

            {invoice.cancellation && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="status">
                    Cancelada em {formatDateTime(invoice.cancellation.cancelled_at)} ·{' '}
                    {options.cancel_reason[String(invoice.cancellation.reason_code)]}: {invoice.cancellation.reason}
                </p>
            )}

            {invoice.taker_manifestation?.type === 'rejected' && (
                <div className="flex gap-3 rounded-lg border border-rejected/30 bg-rejected-soft px-4 py-3 text-sm text-rejected" role="alert">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        {invoice.taker_manifestation.by === 'intermediary' ? 'O intermediário' : 'O tomador'} rejeitou esta nota em{' '}
                        {formatDateTime(invoice.taker_manifestation.at)}
                        {invoice.taker_manifestation.reason && <>: {invoice.taker_manifestation.reason}</>}. Corrija com uma substituição ou cancele.
                    </p>
                </div>
            )}

            {invoice.blocked_at && (
                <p className="rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending" role="status">
                    Nota bloqueada pela prefeitura em {formatDateTime(invoice.blocked_at)}: não pode ser cancelada nem substituída até o desbloqueio.
                </p>
            )}

            {invoice.cancellation_review && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="status">
                    {invoice.cancellation_review.status === 'requested' &&
                        `Cancelamento enviado para análise fiscal em ${formatDateTime(invoice.cancellation_review.requested_at ?? null)}. A decisão da prefeitura chega pelo ADN.`}
                    {invoice.cancellation_review.status === 'approved' && `A análise fiscal deferiu o cancelamento em ${formatDateTime(invoice.cancellation_review.decided_at ?? null)}.`}
                    {invoice.cancellation_review.status === 'denied' &&
                        `A análise fiscal indeferiu o cancelamento em ${formatDateTime(invoice.cancellation_review.decided_at ?? null)}. A nota continua válida.`}
                </p>
            )}

            {invoice.replacement && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="status">
                    Substituída em {formatDateTime(invoice.replacement.replaced_at)}
                    {invoice.replacement.invoice_id && (
                        <>
                            {' '}
                            pela{' '}
                            <Link href={route('invoices.show', invoice.replacement.invoice_id)} className="font-medium text-foreground underline underline-offset-4">
                                nota substituta
                            </Link>
                        </>
                    )}
                    . A Sefin registrou o cancelamento por substituição.
                </p>
            )}

            {invoice.substitution && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="note">
                    Substitui a{' '}
                    <Link href={route('invoices.show', invoice.substitution.replaces_invoice_id)} className="font-medium text-foreground underline underline-offset-4">
                        nota de chave {formatAccessKey(invoice.substitution.replaces_access_key)}
                    </Link>{' '}
                    · {options.substitution_reason[invoice.substitution.reason_code]}
                    {invoice.substitution.reason && <>: {invoice.substitution.reason}</>}
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Nota">
                    <Meta label="Chave de acesso" className="sm:col-span-2">
                        <span className="font-mono text-xs tracking-tight">{formatAccessKey(invoice.access_key)}</span>
                    </Meta>
                    <Meta label="Emitida em">{formatDateTime(invoice.issued_at)}</Meta>
                    <Meta label="Competência">{formatDate(invoice.competence_date)}</Meta>
                    <Meta label="DPS">
                        Série {invoice.dps.series}, nº {invoice.dps.number}
                    </Meta>
                    <Meta label="Referência interna">{invoice.external_id ?? '—'}</Meta>
                    <Meta label="E-mail ao tomador" className="sm:col-span-2">
                        {invoice.delivery.emailed_at
                            ? `Enviado em ${formatDateTime(invoice.delivery.emailed_at)}`
                            : invoice.delivery.last_error
                              ? `Falhou: ${invoice.delivery.last_error}`
                              : invoice.delivery.notify_taker
                                ? invoice.taker.email
                                    ? 'Na fila de envio'
                                    : 'Não enviado: tomador sem e-mail'
                                : 'Envio automático desligado'}
                    </Meta>
                </Panel>

                <PartyPanel title="Tomador" party={invoice.taker} cities={cities} />
                {invoice.intermediary && <PartyPanel title="Intermediário" party={invoice.intermediary} cities={cities} />}

                <Panel title="Serviço">
                    <Meta label="Descrição" className="sm:col-span-2">
                        <span className="font-normal whitespace-pre-line">{invoice.service.description}</span>
                    </Meta>
                    <Meta label="Tributação nacional">{invoice.service.national_tax_code}</Meta>
                    <Meta label="Município da prestação">{invoice.service.city_code ? (cities[invoice.service.city_code] ?? invoice.service.city_code) : '—'}</Meta>
                    {invoice.service.result_country && <Meta label="País do resultado (exportação)">{countryName(invoice.service.result_country) ?? invoice.service.result_country}</Meta>}
                    {invoice.service.foreign_trade && (
                        <Meta label="Valor em moeda estrangeira">
                            {invoice.service.foreign_trade.foreign_amount} (moeda BACEN {invoice.service.foreign_trade.currency})
                        </Meta>
                    )}
                </Panel>

                {hasFiscalDetails(invoice) && (
                    <Panel title="Informações fiscais adicionais">
                        {invoice.service.additional_info?.notes && (
                            <Meta label="Informações complementares" className="sm:col-span-2">
                                <span className="font-normal whitespace-pre-line">{invoice.service.additional_info.notes}</span>
                            </Meta>
                        )}
                        {invoice.service.additional_info?.reference && <Meta label="Documento de referência">{invoice.service.additional_info.reference}</Meta>}
                        {invoice.service.additional_info?.order && <Meta label="Pedido">{invoice.service.additional_info.order}</Meta>}
                        {invoice.service.construction && (
                            <Meta label="Obra">
                                {invoice.service.construction.work_code ??
                                    (invoice.service.construction.cib
                                        ? `CIB ${invoice.service.construction.cib}`
                                        : `${invoice.service.construction.address?.street}, ${invoice.service.construction.address?.number}`)}
                            </Meta>
                        )}
                        {invoice.service.event && (
                            <Meta label="Evento">
                                {invoice.service.event.name} · {formatDate(invoice.service.event.starts_on)} a {formatDate(invoice.service.event.ends_on)}
                            </Meta>
                        )}
                        {invoice.values.intermediary_received && <Meta label="Recebido pelo intermediário">{formatMoney(invoice.values.intermediary_received)}</Meta>}
                        {invoice.values.federal_taxes?.irrf?.amount && <Meta label="IRRF retido">{formatMoney(invoice.values.federal_taxes.irrf.amount)}</Meta>}
                        {invoice.values.federal_taxes?.csll?.amount && <Meta label="CSLL retida">{formatMoney(invoice.values.federal_taxes.csll.amount)}</Meta>}
                        {invoice.values.federal_taxes?.inss?.amount && <Meta label="INSS retido">{formatMoney(invoice.values.federal_taxes.inss.amount)}</Meta>}
                        {invoice.values.federal_taxes?.pis_cofins && (
                            <Meta label={`PIS / COFINS (CST ${invoice.values.federal_taxes.pis_cofins.cst})`}>
                                {formatMoney(invoice.values.federal_taxes.pis_cofins.pis_amount)} / {formatMoney(invoice.values.federal_taxes.pis_cofins.cofins_amount)}
                            </Meta>
                        )}
                        {invoice.values.municipal_benefit && (
                            <Meta label="Benefício municipal">
                                Nº {invoice.values.municipal_benefit.number}
                                {invoice.values.municipal_benefit.reduction_percent && ` · redução de ${invoice.values.municipal_benefit.reduction_percent}%`}
                                {invoice.values.municipal_benefit.reduction_amount && ` · redução de ${formatMoney(invoice.values.municipal_benefit.reduction_amount)}`}
                            </Meta>
                        )}
                        {invoice.values.iss_suspension && (
                            <Meta label="ISS com exigibilidade suspensa">
                                {invoice.values.iss_suspension.type === '1' ? 'Decisão judicial' : 'Processo administrativo'} · {invoice.values.iss_suspension.process_number}
                            </Meta>
                        )}
                        {invoice.values.ibs_cbs && (
                            <Meta label="IBS/CBS">
                                CST {invoice.values.ibs_cbs.cst} · cClassTrib {invoice.values.ibs_cbs.class_code} · cIndOp {invoice.values.ibs_cbs.operation_code}
                            </Meta>
                        )}
                    </Panel>
                )}

                {events.length > 0 && (
                    <section className="rounded-xl border bg-card shadow-xs lg:col-span-2">
                        <h2 className="border-b px-5 py-3.5 text-base font-medium md:px-6">Eventos na Sefin Nacional</h2>
                        <ol className="grid gap-3 px-5 py-4 md:px-6">
                            {events.map((e) => (
                                <li key={e.nsu} className="flex gap-3 text-sm">
                                    <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary/60" aria-hidden="true" />
                                    <div>
                                        <p className="font-medium">{e.label}</p>
                                        <p className="text-muted-foreground">
                                            {formatDateTime(e.processed_at)}
                                            {e.reason && <> · {e.reason}</>}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}

                <Panel title="Valores">
                    <Meta label="Valor do serviço">{formatMoney(invoice.values.amount)}</Meta>
                    <Meta label="Alíquota ISS">{invoice.values.iss_rate ? `${invoice.values.iss_rate.replace('.', ',')}%` : 'Parametrizada pelo município'}</Meta>
                    <Meta label="Tributação do ISSQN">{options.iss_taxation[String(invoice.values.iss_taxation)]}</Meta>
                    <Meta label="Retenção">
                        {!invoice.values.iss_withheld ? 'Não retido' : invoice.values.iss_withholder === 'intermediary' ? 'Retido pelo intermediário' : 'Retido pelo tomador'}
                    </Meta>
                    {invoice.values.discounts?.unconditional && <Meta label="Desconto incondicionado">{formatMoney(invoice.values.discounts.unconditional)}</Meta>}
                    {invoice.values.discounts?.conditional && <Meta label="Desconto condicionado">{formatMoney(invoice.values.discounts.conditional)}</Meta>}
                    {invoice.values.deduction && !invoice.values.deduction.documents && (
                        <Meta label="Dedução da base">
                            {invoice.values.deduction.percent ? `${invoice.values.deduction.percent.replace('.', ',')}%` : formatMoney(invoice.values.deduction.amount ?? '0')}
                        </Meta>
                    )}
                    {invoice.values.deduction?.documents && (
                        <Meta label="Dedução por documentos" className="sm:col-span-2">
                            <ul className="grid gap-1 font-normal">
                                {invoice.values.deduction.documents.map((doc, index) => (
                                    <li key={index} className="flex flex-wrap justify-between gap-x-4">
                                        <span>
                                            {options.deduction_document[doc.kind]} · {doc.access_key ? formatAccessKey(doc.access_key) : doc.number} ·{' '}
                                            {formatDate(doc.issued_on)}
                                        </span>
                                        <span className="font-medium">{formatMoney(doc.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </Meta>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
