import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatAccessKey, formatMoney, formatTaxDocument } from '@/lib/fiscal';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheck, CircleX, Download, FileText, Inbox, RefreshCw, Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

type Role = 'taker' | 'intermediary' | 'provider';
type Status = 'issued' | 'cancelled' | 'replaced';

interface ReceivedEvent {
    nsu: number;
    label: string;
    reason: string | null;
    processed_at: string | null;
}

interface ReceivedRow {
    id: string;
    environment: 'restricted' | 'production';
    access_key: string;
    role: Role;
    status: Status;
    nfse_number: string | null;
    provider: { document: string | null; name: string | null };
    taker: { document: string | null; name: string | null };
    competence_date: string | null;
    issued_at: string | null;
    amount: string | null;
    iss_amount: string | null;
    description: string | null;
    manifestation: { type: 'confirmed' | 'rejected'; reason_code?: string; reason?: string; tacit?: boolean; at: string } | null;
    events: ReceivedEvent[];
}

interface Props {
    invoices: Paginated<ReceivedRow>;
    roleCounts: Partial<Record<Role, number>>;
    filters: { role: Role | null; status: Status | null; search: string | null };
    syncedAt: string | null;
    canSync: boolean;
    canManifest: boolean;
    options: { role: Record<Role, string>; rejection_reason: Record<string, string> };
    [key: string]: unknown;
}

const STATUS_LABEL: Record<Status, string> = { issued: 'Válida', cancelled: 'Cancelada', replaced: 'Substituída' };

function visit(filters: Partial<Props['filters']>) {
    const query: Record<string, string> = {};
    Object.entries(filters).forEach(([key, value]) => {
        if (value) query[key] = value;
    });
    router.get(route('invoices.received.index'), query, { preserveState: true, preserveScroll: true, replace: true, only: ['invoices', 'filters'] });
}

function ManifestDialog({ invoice, reasons }: { invoice: ReceivedRow; reasons: Record<string, string> }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ decision: 'confirm' as 'confirm' | 'reject', reason_code: '', reason: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('invoices.received.manifest', invoice.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Manifestar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Manifestar sobre a NFS-e nº {invoice.nfse_number}</DialogTitle>
                        <DialogDescription>
                            Emitida por {invoice.provider.name} no valor de {formatMoney(invoice.amount)}. A manifestação vai assinada para a Sefin Nacional
                            e não pode ser desfeita por aqui.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="decision">Decisão</Label>
                        <Select value={form.data.decision} onValueChange={(v) => form.setData('decision', v as 'confirm' | 'reject')}>
                            <SelectTrigger id="decision">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="confirm">Confirmar: o serviço foi prestado como descrito</SelectItem>
                                <SelectItem value="reject">Rejeitar</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    {form.data.decision === 'reject' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reason_code">Motivo</Label>
                                <Select value={form.data.reason_code} onValueChange={(v) => form.setData('reason_code', v)}>
                                    <SelectTrigger id="reason_code" aria-invalid={Boolean(errors.reason_code)}>
                                        <SelectValue placeholder="Escolha o motivo" />
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
                                <Label htmlFor="reason">Justificativa {form.data.reason_code !== '9' && <span className="font-normal text-muted-foreground">(opcional)</span>}</Label>
                                <Textarea id="reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} rows={3} maxLength={255} />
                                <p className="text-xs text-muted-foreground">Entre 15 e 255 caracteres.</p>
                                <InputError message={errors.reason} />
                            </div>
                        </>
                    )}
                    <InputError message={errors.manifestation} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Voltar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant={form.data.decision === 'reject' ? 'destructive' : 'default'} disabled={form.processing}>
                            {form.processing && <Spinner />} {form.data.decision === 'reject' ? 'Rejeitar nota' : 'Confirmar nota'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function InvoicesReceived({ invoices, roleCounts, filters, syncedAt, canSync, canManifest, options }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [syncing, setSyncing] = useState(false);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(() => visit({ ...filters, search: search.trim() || null }), 300);
        return () => clearTimeout(t);
        // Só a digitação dispara a busca; os outros filtros já navegam por conta própria.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sync = () =>
        router.post(route('invoices.received.sync'), {}, { preserveScroll: true, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) });

    const total = Object.values(roleCounts).reduce((sum, n) => sum + (n ?? 0), 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais', href: route('invoices.index') }, { title: 'Recebidas' }]}>
            <Head title="Notas recebidas" />

            <PageHeader
                title="Notas recebidas"
                description={
                    syncedAt
                        ? `NFS-e de terceiros em que a empresa aparece, trazidas do Ambiente de Dados Nacional. Última busca: ${formatDateTime(syncedAt)}.`
                        : 'NFS-e de terceiros em que a empresa aparece, trazidas do Ambiente de Dados Nacional a cada hora.'
                }
                actions={
                    <>
                        <div className="relative w-full sm:w-72">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Prestador, CNPJ, número ou chave"
                                aria-label="Buscar notas recebidas"
                                className="h-10 bg-card pl-9"
                            />
                        </div>
                        {canSync && (
                            <Button onClick={sync} disabled={syncing}>
                                {syncing ? <Spinner /> : <RefreshCw />} Buscar agora
                            </Button>
                        )}
                    </>
                }
            />

            <div className="flex flex-wrap items-center gap-3">
                <div role="tablist" aria-label="Filtrar por papel" className="flex gap-1">
                    {([null, 'taker', 'intermediary', 'provider'] as (Role | null)[]).map((role) => {
                        const active = filters.role === role;
                        return (
                            <button
                                key={role ?? 'all'}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => visit({ ...filters, role })}
                                className={cn(
                                    'rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors',
                                    active ? 'border-primary/30 bg-card text-foreground' : 'border-transparent text-muted-foreground hover:bg-card hover:text-foreground',
                                )}
                            >
                                {role === null ? 'Todas' : { taker: 'Como tomador', intermediary: 'Como intermediário', provider: 'Emitidas pelo tomador' }[role]}{' '}
                                <span className="tabular-nums text-muted-foreground/80">{role === null ? total : (roleCounts[role] ?? 0)}</span>
                            </button>
                        );
                    })}
                </div>
                <Select value={filters.status ?? 'all'} onValueChange={(v) => visit({ ...filters, status: v === 'all' ? null : (v as Status) })}>
                    <SelectTrigger className="h-10 w-40 bg-card" aria-label="Situação">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas as situações</SelectItem>
                        {Object.entries(STATUS_LABEL).map(([key, label]) => (
                            <SelectItem key={key} value={key}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {(filters.role || filters.status || filters.search) && (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            setSearch('');
                            visit({});
                        }}
                    >
                        <X /> Limpar
                    </Button>
                )}
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {invoices.data.length === 0 ? (
                    <EmptyState
                        icon={total === 0 ? Inbox : Search}
                        title={total === 0 ? 'Nenhuma nota recebida ainda' : 'Nenhuma nota encontrada'}
                        description={
                            total === 0
                                ? 'Quando um fornecedor emitir NFS-e para a empresa, ela aparece aqui. A busca no ADN roda de hora em hora.'
                                : 'Nada corresponde a estes filtros.'
                        }
                    />
                ) : (
                    <ul className="divide-y">
                        {invoices.data.map((i) => (
                            <li key={i.id} className="flex flex-col gap-3 px-5 py-4 md:px-6">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-6">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{i.role === 'provider' ? i.taker.name : i.provider.name}</p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            NFS-e nº {i.nfse_number} · {formatTaxDocument(i.role === 'provider' ? i.taker.document : i.provider.document)} · emitida{' '}
                                            {formatDate(i.issued_at)} · {options.role[i.role]}
                                            {i.environment === 'restricted' && <> · produção restrita</>}
                                        </p>
                                        {i.description && <p className="mt-1 line-clamp-2 text-sm text-foreground/80">{i.description}</p>}
                                        <p className="mt-1 font-mono text-xs break-all text-muted-foreground">{formatAccessKey(i.access_key)}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end sm:gap-1.5">
                                        <Badge variant="outline" className={cn(i.status !== 'issued' && 'text-muted-foreground')}>
                                            {STATUS_LABEL[i.status]}
                                        </Badge>
                                        <span className="text-sm font-medium tabular-nums">{formatMoney(i.amount)}</span>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {i.manifestation ? (
                                        <span className={cn('flex items-center gap-1.5 text-sm', i.manifestation.type === 'confirmed' ? 'text-signed' : 'text-rejected')}>
                                            {i.manifestation.type === 'confirmed' ? <CircleCheck className="size-4" /> : <CircleX className="size-4" />}
                                            {i.manifestation.type === 'confirmed' ? (i.manifestation.tacit ? 'Confirmada tacitamente' : 'Confirmada') : 'Rejeitada'} em{' '}
                                            {formatDateTime(i.manifestation.at)}
                                        </span>
                                    ) : (
                                        canManifest && i.status === 'issued' && <ManifestDialog invoice={i} reasons={options.rejection_reason} />
                                    )}
                                    <Button size="sm" variant="ghost" asChild>
                                        <a href={route('invoices.received.xml', i.id)}>
                                            <Download /> XML
                                        </a>
                                    </Button>
                                    <Button size="sm" variant="ghost" asChild>
                                        <a href={route('invoices.received.pdf', i.id)} target="_blank" rel="noopener">
                                            <FileText /> DANFSe
                                        </a>
                                    </Button>
                                </div>
                                {i.events.length > 0 && (
                                    <ul className="grid gap-1 border-l-2 pl-3 text-xs text-muted-foreground">
                                        {i.events.map((e) => (
                                            <li key={e.nsu}>
                                                {formatDateTime(e.processed_at)} · {e.label}
                                                {e.reason && <>: {e.reason}</>}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Pagination page={invoices} />
        </AppLayout>
    );
}
