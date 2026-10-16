import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { SignerNames, SignerProgress, type SignerSummary } from '@/components/signer-progress';
import { StatusBadge, type DocumentStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileSignature, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type FilterKey = 'in_progress' | 'completed' | 'rejected' | 'draft';

interface DocumentRow {
    id: string;
    name: string;
    status: DocumentStatus;
    page_count: number | null;
    signers_count: number;
    signers: SignerSummary[];
    creator: { name: string; email: string } | null;
    created_at: string;
    sent_at: string | null;
    completed_at: string | null;
}

interface Props {
    documents: Paginated<DocumentRow>;
    statusCounts: Partial<Record<DocumentStatus, number>>;
    filters: { status: FilterKey | null; search: string | null };
    [key: string]: unknown;
}

const FILTERS: { key: FilterKey | null; label: string; statuses: DocumentStatus[] | null; dot: string }[] = [
    { key: 'in_progress', label: 'Em andamento', statuses: ['sent', 'partially_signed'], dot: 'bg-pending' },
    { key: 'completed', label: 'Concluídos', statuses: ['completed'], dot: 'bg-signed' },
    { key: 'rejected', label: 'Recusados', statuses: ['rejected'], dot: 'bg-rejected' },
    { key: 'draft', label: 'Rascunhos', statuses: ['draft'], dot: 'bg-idle' },
    { key: null, label: 'Todos', statuses: null, dot: '' },
];

function countFor(counts: Props['statusCounts'], statuses: DocumentStatus[] | null) {
    const entries = Object.entries(counts) as [DocumentStatus, number][];
    return entries
        .filter(([status]) => statuses === null || statuses.includes(status))
        .reduce((sum, [, n]) => sum + n, 0);
}

function visit(params: { status?: FilterKey | null; search?: string | null }) {
    const query: Record<string, string> = {};
    if (params.status) query.status = params.status;
    if (params.search) query.search = params.search;

    router.get(route('signatures.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['documents', 'filters'],
    });
}

function documentSubtitle(d: DocumentRow) {
    if (d.status === 'completed' && d.completed_at) return `Concluído em ${formatDate(d.completed_at)}`;
    if (d.sent_at) return `Enviado em ${formatDate(d.sent_at)}`;
    return `Criado em ${formatDate(d.created_at)}`;
}

export default function SignaturesIndex({ documents, statusCounts, filters }: Props) {
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

    const totalDocuments = countFor(statusCounts, null);
    const isFiltering = Boolean(filters.status || filters.search);

    return (
        <AppLayout breadcrumbs={[{ title: 'Documentos' }]}>
            <Head title="Documentos" />

            <PageHeader
                title="Documentos"
                description="Contratos enviados para assinatura neste workspace."
                actions={
                    <div className="relative w-full sm:w-80">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por documento ou signatário"
                            aria-label="Buscar documentos"
                            className="h-10 bg-card pl-9"
                        />
                    </div>
                }
            />

            <div
                role="tablist"
                aria-label="Filtrar por status"
                className="-mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:px-0"
            >
                {FILTERS.map((f) => {
                    const active = filters.status === f.key;
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
                            <span
                                className={cn(
                                    'tabular-nums',
                                    active ? 'text-primary' : 'text-muted-foreground/80',
                                )}
                            >
                                {countFor(statusCounts, f.statuses)}
                            </span>
                        </button>
                    );
                })}
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {documents.data.length === 0 ? (
                    totalDocuments === 0 ? (
                        <EmptyState
                            icon={FileSignature}
                            title="Envie seu primeiro documento"
                            description="Suba um PDF, adicione quem precisa assinar e acompanhe cada assinatura por aqui."
                            action={
                                <Button asChild>
                                    <Link href={route('signatures.create')}>
                                        <Plus /> Novo documento
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Search}
                            title="Nenhum documento encontrado"
                            description={
                                filters.search
                                    ? `Nada corresponde a “${filters.search}” neste filtro.`
                                    : 'Não há documentos com este status.'
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
                    )
                ) : (
                    <ul className="divide-y">
                        {documents.data.map((d) => (
                            <li key={d.id} className="group relative">
                                <div className="flex flex-col gap-3 px-5 py-4 transition-colors group-hover:bg-muted/40 sm:flex-row sm:items-center sm:gap-6 md:px-6">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('signatures.show', d.id)}
                                            className="block truncate font-medium text-foreground after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-ring"
                                        >
                                            {d.name}
                                        </Link>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {documentSubtitle(d)}
                                            {d.creator && <> por {d.creator.name}</>}
                                        </p>
                                        <SignerNames signers={d.signers} className="mt-2.5" />
                                    </div>
                                    <div className="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-2">
                                        <StatusBadge status={d.status} />
                                        <SignerProgress signers={d.signers} />
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Pagination page={documents} />
        </AppLayout>
    );
}
