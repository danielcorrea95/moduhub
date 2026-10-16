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
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatAccessKey, formatMoney, formatTaxDocument } from '@/lib/fiscal';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Download, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface SefinMessage {
    code: string | null;
    message: string;
    complement: string | null;
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
    taker: {
        document: string;
        name: string;
        email?: string | null;
        phone?: string | null;
        address?: { street: string; number: string; district: string; postal_code: string; city_code: string } | null;
    };
    service: {
        national_tax_code: string;
        municipal_tax_code?: string | null;
        nbs_code?: string | null;
        description: string;
        city_code?: string | null;
    };
    values: {
        amount: string;
        iss_rate: string | null;
        iss_withheld: boolean;
        iss_taxation: number;
    };
    rejection: SefinMessage[] | null;
    last_error: string | null;
    last_attempt_at: string | null;
    cancellation: { reason_code: number; reason: string; cancelled_at: string } | null;
    has_xml: boolean;
    created_at: string;
}

interface Props {
    invoice: InvoiceData;
    canCancel: boolean;
    options: { cancel_reason: Record<string, string>; iss_taxation: Record<string, string> };
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

function CancelDialog({ invoice, reasons }: { invoice: InvoiceData; reasons: Record<string, string> }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason_code: '1', reason: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('invoices.cancel', invoice.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive">
                    <Ban /> Cancelar nota
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Cancelar NFS-e nº {invoice.nfse_number}?</DialogTitle>
                        <DialogDescription>
                            O pedido de cancelamento vai assinado para a Sefin Nacional. Depois de aceito, a nota não volta a valer.
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
                        <InputError message={errors.reason ?? errors.cancel} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Voltar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            {form.processing && <Spinner />} Cancelar nota
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function InvoicesShow({ invoice, canCancel, options }: Props) {
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
                    {invoice.has_xml && (
                        <Button variant="outline" asChild>
                            <a href={route('invoices.xml', invoice.id)}>
                                <Download /> Baixar XML
                            </a>
                        </Button>
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
                    <p className="mt-3 text-rejected/80">Corrija os dados e emita uma nova nota. O número desta DPS não é reaproveitado.</p>
                </section>
            )}

            {invoice.cancellation && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="status">
                    Cancelada em {formatDateTime(invoice.cancellation.cancelled_at)} ·{' '}
                    {options.cancel_reason[String(invoice.cancellation.reason_code)]}: {invoice.cancellation.reason}
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
                </Panel>

                <Panel title="Tomador">
                    <Meta label="Nome ou razão social" className="sm:col-span-2">
                        {invoice.taker.name}
                    </Meta>
                    <Meta label="CPF/CNPJ">{formatTaxDocument(invoice.taker.document)}</Meta>
                    <Meta label="E-mail">{invoice.taker.email || '—'}</Meta>
                    {invoice.taker.address && (
                        <Meta label="Endereço" className="sm:col-span-2">
                            {invoice.taker.address.street}, {invoice.taker.address.number} · {invoice.taker.address.district} · CEP{' '}
                            {invoice.taker.address.postal_code}
                        </Meta>
                    )}
                </Panel>

                <Panel title="Serviço">
                    <Meta label="Descrição" className="sm:col-span-2">
                        <span className="font-normal whitespace-pre-line">{invoice.service.description}</span>
                    </Meta>
                    <Meta label="Tributação nacional">{invoice.service.national_tax_code}</Meta>
                    <Meta label="Município da prestação">{invoice.service.city_code ?? '—'}</Meta>
                </Panel>

                <Panel title="Valores">
                    <Meta label="Valor do serviço">{formatMoney(invoice.values.amount)}</Meta>
                    <Meta label="Alíquota ISS">{invoice.values.iss_rate ? `${invoice.values.iss_rate.replace('.', ',')}%` : 'Parametrizada pelo município'}</Meta>
                    <Meta label="Tributação do ISSQN">{options.iss_taxation[String(invoice.values.iss_taxation)]}</Meta>
                    <Meta label="Retenção">{invoice.values.iss_withheld ? 'Retido pelo tomador' : 'Não retido'}</Meta>
                </Panel>
            </div>
        </AppLayout>
    );
}
