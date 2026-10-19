import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDateTime } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CircleCheck, CircleDashed, CircleMinus, CircleX, MapPinned, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Situation = 'national_emitter' | 'adn_only' | 'inactive' | 'not_found';
type Environment = 'production' | 'restricted';

interface Agreement {
    city_code: string;
    name: string | null;
    uf: string | null;
    environment: Environment;
    situation: Situation;
    issues_through_sefin: boolean;
    agreement: {
        status: string;
        national_environment: boolean | null;
        national_emitter: boolean | null;
        rfb_standard_issuance: boolean | null;
        national_assessment: boolean | null;
        credit_use: boolean | null;
    };
    message: string | null;
    checked_at: string | null;
}

interface Filters {
    environment: Environment;
    uf: string | null;
    search: string | null;
    situation: Situation | null;
}

interface Props {
    agreements: Paginated<Agreement>;
    counts: Record<Situation, number>;
    filters: Filters;
    states: string[];
    environments: Record<Environment, string>;
    lastCheckedAt: string | null;
    issuerCity: { city_code: string; label: string | null; environment: Environment; agreement: Agreement | null } | null;
    [key: string]: unknown;
}

const SITUATIONS: { key: Situation; label: string; hint: string; icon: typeof CircleCheck; tone: string }[] = [
    {
        key: 'national_emitter',
        label: 'Emite pela Sefin Nacional',
        hint: 'Convênio ativo e emissores nacionais liberados: o Moduhub emite NFS-e para empresas daqui.',
        icon: CircleCheck,
        tone: 'text-signed',
    },
    {
        key: 'adn_only',
        label: 'Só ADN',
        hint: 'Convênio ativo, mas a prefeitura mantém emissor próprio. As notas vão para o ADN, emitidas no sistema da prefeitura.',
        icon: CircleMinus,
        tone: 'text-pending',
    },
    { key: 'inactive', label: 'Convênio inativo', hint: 'Assinou o convênio, mas ainda não está ativo no Sistema Nacional.', icon: CircleDashed, tone: 'text-muted-foreground' },
    { key: 'not_found', label: 'Sem convênio', hint: 'O ADN não tem convênio registrado para o município.', icon: CircleX, tone: 'text-rejected' },
];

const SITUATION = Object.fromEntries(SITUATIONS.map((s) => [s.key, s])) as Record<Situation, (typeof SITUATIONS)[number]>;

function visit(filters: Partial<Filters>) {
    const query: Record<string, string> = {};
    Object.entries(filters).forEach(([key, value]) => {
        if (value) query[key] = value;
    });

    router.get(route('invoices.agreements'), query, { preserveState: true, preserveScroll: true, replace: true, only: ['agreements', 'counts', 'filters', 'lastCheckedAt'] });
}

function Flag({ value, label }: { value: boolean | null; label: string }) {
    if (value === null) return null;
    return (
        <span className={cn('rounded border px-1.5 py-0.5 text-xs', value ? 'border-signed/30 text-signed' : 'text-muted-foreground line-through')}>{label}</span>
    );
}

export default function InvoicesAgreements({ agreements, counts, filters, states, environments, lastCheckedAt, issuerCity }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
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

    const total = Object.values(counts).reduce((sum, n) => sum + n, 0);
    const issuerAgreement = issuerCity?.agreement;

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais', href: route('invoices.index') }, { title: 'Municípios' }]}>
            <Head title="Municípios conveniados" />

            <PageHeader
                title="Municípios conveniados"
                description={
                    lastCheckedAt
                        ? `Situação de cada município no Sistema Nacional NFS-e, consultada no ADN em ${formatDateTime(lastCheckedAt)}. Atualiza toda semana.`
                        : 'Situação de cada município no Sistema Nacional NFS-e, consultada no ADN. A primeira varredura ainda não rodou.'
                }
            />

            {issuerCity && (
                <div
                    className={cn(
                        'flex gap-3 rounded-lg border px-4 py-3 text-sm',
                        issuerAgreement?.issues_through_sefin ? 'border-signed/30 bg-signed-soft text-signed' : 'border-pending/30 bg-pending-soft text-pending',
                    )}
                    role="status"
                >
                    <MapPinned className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        Emitente em <span className="font-medium">{issuerCity.label ?? issuerCity.city_code}</span> ({environments[issuerCity.environment]}):{' '}
                        {issuerAgreement ? SITUATION[issuerAgreement.situation].label.toLowerCase() : 'ainda sem consulta ao ADN'}.
                        {issuerAgreement && !issuerAgreement.issues_through_sefin && ' A Sefin Nacional pode recusar notas deste município.'}
                    </p>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {SITUATIONS.map((s) => {
                    const active = filters.situation === s.key;
                    const Icon = s.icon;
                    return (
                        <button
                            key={s.key}
                            type="button"
                            aria-pressed={active}
                            onClick={() => visit({ ...filters, situation: active ? null : s.key })}
                            title={s.hint}
                            className={cn(
                                'rounded-xl border bg-card px-4 py-3 text-left transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-2',
                                active && 'border-primary/40 ring-1 ring-primary/30',
                            )}
                        >
                            <span className={cn('flex items-center gap-2 text-sm font-medium', s.tone)}>
                                <Icon className="size-4" aria-hidden="true" /> {s.label}
                            </span>
                            <span className="mt-1 block text-2xl font-semibold tabular-nums">{counts[s.key].toLocaleString('pt-BR')}</span>
                            <span className="mt-0.5 block text-xs text-muted-foreground">{s.hint}</span>
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative w-full sm:w-72">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Nome ou código IBGE"
                        aria-label="Buscar município"
                        className="h-10 bg-card pl-9"
                    />
                </div>
                <Select value={filters.uf ?? 'all'} onValueChange={(v) => visit({ ...filters, uf: v === 'all' ? null : v })}>
                    <SelectTrigger className="h-10 w-full bg-card sm:w-36" aria-label="Estado">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os estados</SelectItem>
                        {states.map((uf) => (
                            <SelectItem key={uf} value={uf}>
                                {uf}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filters.environment} onValueChange={(v) => visit({ ...filters, environment: v as Environment })}>
                    <SelectTrigger className="h-10 w-full bg-card sm:w-56" aria-label="Ambiente">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(environments).map(([key, label]) => (
                            <SelectItem key={key} value={key}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {(filters.uf || filters.search || filters.situation) && (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            setSearch('');
                            visit({ environment: filters.environment });
                        }}
                    >
                        <X /> Limpar
                    </Button>
                )}
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {agreements.data.length === 0 ? (
                    <EmptyState
                        icon={total === 0 && !filters.uf ? MapPinned : Search}
                        title={total === 0 && !filters.uf ? 'Nenhum município consultado ainda' : 'Nenhum município encontrado'}
                        description={
                            total === 0 && !filters.uf
                                ? 'A varredura do ADN roda toda semana. Para rodar agora: php artisan invoices:sync-agreements.'
                                : 'Nada corresponde a estes filtros.'
                        }
                    />
                ) : (
                    <ul className="divide-y">
                        {agreements.data.map((a) => {
                            const s = SITUATION[a.situation];
                            const Icon = s.icon;
                            return (
                                <li key={a.city_code} className="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-6 md:px-6">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {a.name ?? a.city_code} <span className="font-normal text-muted-foreground">/ {a.uf}</span>
                                        </p>
                                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">{a.city_code}</p>
                                    </div>
                                    {a.agreement.status === 'active' && (
                                        <div className="flex flex-wrap gap-1.5">
                                            <Flag value={a.agreement.national_environment} label="ADN" />
                                            <Flag value={a.agreement.national_emitter} label="Emissor Nacional" />
                                            <Flag value={a.agreement.national_assessment} label="MAN" />
                                        </div>
                                    )}
                                    <Badge variant="outline" className={cn('shrink-0 gap-1.5', s.tone)}>
                                        <Icon className="size-3.5" aria-hidden="true" /> {s.label}
                                    </Badge>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            <Pagination page={agreements} />
        </AppLayout>
    );
}
