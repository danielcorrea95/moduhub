import { CopyButton } from '@/components/copy-button';
import EmptyState from '@/components/empty-state';
import {
    DELIVERY_STATUS,
    EVENT_LABELS,
    explainFailure,
    formatCount,
    type MessageDetail,
    type MessageRow,
    type ReportPeriod,
    type StatusFilter,
} from '@/components/mailing/email-log';
import { EmailsShell } from '@/components/mailing/emails-shell';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn, formatDateTime } from '@/lib/utils';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { Eye, Inbox, MousePointerClick, Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

interface Filters {
    status: StatusFilter | null;
    provider: string | null;
    search: string | null;
}

interface Props {
    period: ReportPeriod;
    filters: Filters;
    providers: { key: string; label: string }[];
    messages: Paginated<MessageRow>;
    selected: MessageDetail | null;
    tracking: boolean;
    suppressed: number;
    [key: string]: unknown;
}

const STATUS_FILTERS: { key: StatusFilter | null; label: string; dot?: string; tracking?: boolean }[] = [
    { key: null, label: 'Todas' },
    { key: 'delivered', label: 'Entregues', dot: 'bg-signed' },
    { key: 'opened', label: 'Abertas', dot: 'bg-primary', tracking: true },
    { key: 'clicked', label: 'Com clique', dot: 'bg-primary', tracking: true },
    { key: 'pending', label: 'Aguardando', dot: 'bg-idle' },
    { key: 'failed', label: 'Não entregues', dot: 'bg-rejected' },
    { key: 'complained', label: 'Spam', dot: 'bg-rejected' },
];

type Query = { status?: string | null; provedor?: string | null; busca?: string | null; mensagem?: string | null };

export default function EmailMessages({ period, filters, providers, messages, selected, tracking, suppressed }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    const base: Query = { status: filters.status, provedor: filters.provider, busca: filters.search };

    const visit = (query: Query, only?: string[]) => {
        const params: Record<string, string | number> = { periodo: period.days };
        for (const [key, value] of Object.entries(query)) if (value) params[key] = value;

        router.get(route('emails.messages'), params, { preserveState: true, preserveScroll: true, replace: true, only });
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(() => visit({ ...base, busca: search.trim() || null }, ['messages', 'filters']), 300);
        return () => clearTimeout(t);
        // Só a digitação dispara a busca; os outros filtros já navegam por conta própria.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const page = messages.current_page > 1 ? { page: String(messages.current_page) } : {};
    const open = (id: string | null) => visit({ ...base, ...page, mensagem: id }, ['selected']);
    const isFiltering = Boolean(filters.status || filters.provider || filters.search);

    return (
        <EmailsShell section="messages" period={period} suppressed={suppressed}>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div role="tablist" aria-label="Filtrar por situação" className="-mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:px-0">
                    {STATUS_FILTERS.filter((f) => tracking || !f.tracking).map((f) => {
                        const active = filters.status === f.key;
                        return (
                            <button
                                key={f.label}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => visit({ ...base, status: f.key })}
                                className={cn(
                                    'flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2',
                                    active
                                        ? 'border-primary/30 bg-card text-foreground shadow-[0_1px_2px_rgb(27_34_48/0.06)]'
                                        : 'border-transparent text-muted-foreground hover:bg-card hover:text-foreground',
                                )}
                            >
                                {f.dot && <span className={cn('size-2 rounded-full', f.dot)} aria-hidden="true" />}
                                {f.label}
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <Select value={filters.provider ?? 'all'} onValueChange={(v) => visit({ ...base, provedor: v === 'all' ? null : v })}>
                        <SelectTrigger className="h-10 bg-card sm:w-48" aria-label="Provedor">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos os provedores</SelectItem>
                            {providers.map((p) => (
                                <SelectItem key={p.key} value={p.key}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="relative sm:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Destinatário ou assunto"
                            aria-label="Buscar mensagens"
                            className="h-10 bg-card pl-9"
                        />
                    </div>
                </div>
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {messages.data.length === 0 ? (
                    <EmptyState
                        icon={isFiltering ? Search : Inbox}
                        title={isFiltering ? 'Nenhuma mensagem com esses filtros' : `Nenhum e-mail nos últimos ${period.days} dias`}
                        description={
                            isFiltering
                                ? 'Troque a situação, o provedor ou o período.'
                                : 'Convites de assinatura, NFS-e e o que seus sistemas enviarem pelo SMTP aparecem aqui.'
                        }
                        action={
                            isFiltering && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setSearch('');
                                        visit({});
                                    }}
                                >
                                    <X /> Limpar filtros
                                </Button>
                            )
                        }
                    />
                ) : (
                    <ul className="divide-y">
                        {messages.data.map((m) => (
                            <li key={m.id}>
                                <MessageItem message={m} tracking={tracking} active={selected?.id === m.id} onOpen={() => open(m.id)} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Pagination page={messages} />

            <Sheet open={selected !== null} onOpenChange={(isOpen) => !isOpen && open(null)}>
                <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-lg">
                    {selected && <MessageDetailPanel message={selected} tracking={tracking} />}
                </SheetContent>
            </Sheet>
        </EmailsShell>
    );
}

function MessageItem({ message: m, tracking, active, onOpen }: { message: MessageRow; tracking: boolean; active: boolean; onOpen: () => void }) {
    const status = DELIVERY_STATUS[m.status];

    return (
        <button
            type="button"
            onClick={onOpen}
            aria-current={active ? 'true' : undefined}
            className={cn(
                'grid w-full grid-cols-[minmax(0,1fr)_auto] gap-x-6 gap-y-1 px-5 py-3.5 text-left transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 md:grid-cols-[9.5rem_minmax(0,1fr)_auto] md:px-6',
                active && 'bg-accent/60 hover:bg-accent/60',
            )}
        >
            <span className={cn('flex items-center gap-2 text-sm font-medium md:row-span-2', status.text)}>
                <span className={cn('size-2 shrink-0 rounded-full', status.dot)} aria-hidden="true" />
                {status.label}
            </span>
            <span className="col-start-1 min-w-0 md:col-start-2 md:row-start-1">
                <span className="block truncate font-medium text-foreground">{m.subject || '(sem assunto)'}</span>
            </span>
            <span className="col-start-1 truncate text-sm text-muted-foreground md:col-start-2">
                Para {m.recipient} · {m.origin}
            </span>
            <span className="col-start-2 row-span-2 row-start-1 flex flex-col items-end justify-center gap-1 text-sm md:col-start-3">
                <span className="text-muted-foreground tabular-nums">{formatDateTime(m.sent_at)}</span>
                {tracking && (
                    <span className="flex items-center gap-3 text-xs text-muted-foreground tabular-nums">
                        <span className={cn('flex items-center gap-1', m.open_count > 0 && 'text-primary')} title="Aberturas">
                            <Eye className="size-3.5" aria-hidden="true" />
                            <span className="sr-only">Aberturas:</span>
                            {m.open_count}
                        </span>
                        <span className={cn('flex items-center gap-1', m.click_count > 0 && 'text-primary')} title="Cliques">
                            <MousePointerClick className="size-3.5" aria-hidden="true" />
                            <span className="sr-only">Cliques:</span>
                            {m.click_count}
                        </span>
                    </span>
                )}
            </span>
        </button>
    );
}

function MessageDetailPanel({ message: m, tracking }: { message: MessageDetail; tracking: boolean }) {
    const status = DELIVERY_STATUS[m.status];
    const failure = explainFailure(m.status, m.bounce_type);
    const negative = ['bounced', 'rejected', 'complained'].includes(m.status);

    return (
        <>
            <SheetHeader className="gap-3 border-b p-6">
                <span className={cn('flex items-center gap-2 text-sm font-medium', status.text)}>
                    <span className={cn('size-2 rounded-full', status.dot)} aria-hidden="true" />
                    {status.label}
                </span>
                <SheetTitle className="pr-6 text-lg leading-snug">{m.subject || '(sem assunto)'}</SheetTitle>
                <SheetDescription>Para {m.recipient}</SheetDescription>
            </SheetHeader>

            <div className="flex flex-col gap-6 p-6">
                {failure && (
                    <div className={cn('rounded-lg px-4 py-3 text-sm', negative ? 'bg-rejected-soft text-rejected' : 'bg-pending-soft text-pending')} role="status">
                        <p className="font-medium">{failure}</p>
                        {m.detail && <p className="mt-1 font-mono text-xs break-words opacity-80">{m.detail}</p>}
                    </div>
                )}

                <dl className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-x-4 gap-y-2.5 text-sm">
                    <Term label="De">{m.from_address ?? '—'}</Term>
                    <Term label="Para">{m.recipient}</Term>
                    <Term label="Origem">{m.origin}</Term>
                    <Term label="Provedor">{m.provider}</Term>
                    <Term label="Enviado em">{formatDateTime(m.sent_at)}</Term>
                    <Term label="Entregue em">{formatDateTime(m.delivered_at)}</Term>
                    {tracking && (
                        <>
                            <Term label="Aberturas">{m.open_count ? `${formatCount(m.open_count)}, a primeira em ${formatDateTime(m.opened_at)}` : 'Nenhuma'}</Term>
                            <Term label="Cliques">{m.click_count ? `${formatCount(m.click_count)}, o primeiro em ${formatDateTime(m.clicked_at)}` : 'Nenhum'}</Term>
                        </>
                    )}
                    <Term label="ID no SES">
                        <span className="flex min-w-0 items-center gap-1">
                            <span className="truncate font-mono text-xs">{m.message_id}</span>
                            <CopyButton value={m.message_id} variant="ghost" />
                        </span>
                    </Term>
                </dl>

                <section aria-labelledby="history-title">
                    <h3 id="history-title" className="text-sm font-medium">
                        Histórico
                    </h3>
                    <ol className="mt-3">
                        {m.events.map((e, i) => (
                            <li key={e.id} className="relative grid grid-cols-[1rem_minmax(0,1fr)] gap-x-3 pb-4 last:pb-0">
                                {i < m.events.length - 1 && <span aria-hidden="true" className="absolute top-4 bottom-0 left-[0.4375rem] w-px bg-border" />}
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'mt-1 size-2.5 justify-self-center rounded-full ring-4 ring-card',
                                        ['bounced', 'rejected', 'complained'].includes(e.type)
                                            ? 'bg-rejected'
                                            : e.type === 'delayed'
                                              ? 'bg-pending'
                                              : e.type === 'delivered'
                                                ? 'bg-signed'
                                                : e.type === 'opened' || e.type === 'clicked'
                                                  ? 'bg-primary'
                                                  : 'bg-idle',
                                    )}
                                />
                                <div className="min-w-0 text-sm">
                                    <p className="font-medium">{EVENT_LABELS[e.type] ?? e.type}</p>
                                    <p className="text-muted-foreground tabular-nums">{formatDateTime(e.occurred_at)}</p>
                                    {e.detail && <p className="mt-0.5 truncate text-xs text-muted-foreground" title={e.detail}>{e.detail}</p>}
                                </div>
                            </li>
                        ))}
                    </ol>
                    {tracking && (
                        <p className="mt-4 text-xs text-muted-foreground">
                            Abertura é aproximada: o Apple Mail baixa as imagens sozinho, e alguns provedores bloqueiam.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

function Term({ label, children }: { label: string; children: ReactNode }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{children}</dd>
        </>
    );
}
