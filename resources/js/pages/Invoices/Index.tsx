import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { StatusBadge, type InvoiceStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatMoney, formatTaxDocument } from '@/lib/fiscal';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, ReceiptText, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface InvoiceRow {
    id: string;
    status: InvoiceStatus;
    environment: 'restricted' | 'production';
    nfse_number: string | null;
    dps_number: number;
    taker_name: string;
    taker_document: string | null;
    amount: string;
    competence_date: string | null;
    issued_at: string | null;
    created_at: string;
}

interface Props {
    invoices: Paginated<InvoiceRow>;
    statusCounts: Partial<Record<InvoiceStatus, number>>;
    filters: { status: InvoiceStatus | null; search: string | null };
    issuerReady: boolean;
    [key: string]: unknown;
}

const FILTERS: { key: InvoiceStatus | null; label: string; dot: string }[] = [
    { key: null, label: 'Todas', dot: '' },
    { key: 'issued', label: 'Emitidas', dot: 'bg-signed' },
    { key: 'pending', label: 'Aguardando', dot: 'bg-pending' },
    { key: 'rejected', label: 'Rejeitadas', dot: 'bg-rejected' },
    { key: 'cancelled', label: 'Canceladas', dot: 'bg-idle' },
];

function visit(params: { status?: InvoiceStatus | null; search?: string | null }) {
    const query: Record<string, string> = {};
    if (params.status) query.status = params.status;
    if (params.search) query.search = params.search;

    router.get(route('invoices.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['invoices', 'filters'],
    });
}

function rowTitle(i: InvoiceRow) {
    return i.nfse_number ? `NFS-e nº ${i.nfse_number}` : `DPS nº ${i.dps_number}`;
}

export default function InvoicesIndex({ invoices, statusCounts, filters, issuerReady }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(() => visit({ status: filters.status, search: search.trim() || null }), 300);
        return () => clearTimeout(t);
        // Só a digitação dispara a busca; trocar de filtro já navega por conta própria.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const total = Object.values(statusCounts).reduce((sum, n) => sum + (n ?? 0), 0);
    const isFiltering = Boolean(filters.status || filters.search);

    const newInvoice = (
        <Button asChild>
            <Link href={route('invoices.create')}>
                <Plus /> Nova nota
            </Link>
        </Button>
    );

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais' }]}>
            <Head title="Notas fiscais" />

            <PageHeader
                title="Notas fiscais"
                description="NFS-e emitidas por este workspace na Sefin Nacional."
                actions={
                    <>
                        <div className="relative w-full sm:w-72">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Tomador, CPF/CNPJ ou número"
                                aria-label="Buscar notas"
                                className="h-10 bg-card pl-9"
                            />
                        </div>
                        {issuerReady && newInvoice}
                    </>
                }
            />

            {!issuerReady && (
                <div className="flex flex-col gap-3 rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p>Para emitir, configure o emitente e envie o certificado digital (e-CNPJ A1).</p>
                    <Button asChild size="sm" variant="outline" className="shrink-0 bg-card text-foreground">
                        <Link href={route('invoices.issuer.edit')}>
                            <Building2 /> Configurar emitente
                        </Link>
                    </Button>
                </div>
            )}

            <div role="tablist" aria-label="Filtrar por status" className="-mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:px-0">
                {FILTERS.map((f) => {
                    const active = filters.status === f.key;
                    const count = f.key === null ? total : (statusCounts[f.key] ?? 0);
                    return (
                        <button
                            key={f.label}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => visit({ status: f.key, search: filters.search })}
                            className={cn(
                                'flex shrink-0 items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors',
                                'focus-visible:outline-2 focus-visible:outline-offset-2',
                                active
                                    ? 'border-primary/30 bg-card text-foreground shadow-[0_1px_2px_rgb(27_34_48/0.06)]'
                                    : 'border-transparent text-muted-foreground hover:bg-card hover:text-foreground',
                            )}
                        >
                            {f.dot && <span className={cn('size-2 rounded-full', f.dot)} aria-hidden="true" />}
                            {f.label}
                            <span className={cn('tabular-nums', active ? 'text-primary' : 'text-muted-foreground/80')}>{count}</span>
                        </button>
                    );
                })}
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {invoices.data.length === 0 ? (
                    total === 0 ? (
                        <EmptyState
                            icon={ReceiptText}
                            title="Nenhuma nota emitida ainda"
                            description="Preencha tomador, serviço e valor. A nota é assinada com o certificado do emitente e enviada à Sefin Nacional na hora."
                            action={issuerReady ? newInvoice : undefined}
                        />
                    ) : (
                        <EmptyState
                            icon={Search}
                            title="Nenhuma nota encontrada"
                            description={filters.search ? `Nada corresponde a “${filters.search}” neste filtro.` : 'Não há notas com este status.'}
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
                    )
                ) : (
                    <ul className="divide-y">
                        {invoices.data.map((i) => (
                            <li key={i.id} className="group relative">
                                <div className="flex flex-col gap-3 px-5 py-4 transition-colors group-hover:bg-muted/40 sm:flex-row sm:items-center sm:gap-6 md:px-6">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('invoices.show', i.id)}
                                            className="block truncate font-medium text-foreground after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-ring"
                                        >
                                            {i.taker_name}
                                        </Link>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {rowTitle(i)} · {formatTaxDocument(i.taker_document)} · competência {formatDate(i.competence_date)}
                                            {i.environment === 'restricted' && <> · produção restrita</>}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-1.5">
                                        <StatusBadge status={i.status} kind="invoice" />
                                        <span className="text-sm font-medium tabular-nums">{formatMoney(i.amount)}</span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Pagination page={invoices} />
        </AppLayout>
    );
}
