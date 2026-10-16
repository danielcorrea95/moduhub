import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { SignerNames, SignerProgress, type SignerSummary } from '@/components/signer-progress';
import { StatusBadge, type DocumentStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDate } from '@/lib/utils';
import type { CurrentTenant, ModuleKey, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronRight,
    CircleCheck,
    CircleX,
    FileSignature,
    Hourglass,
    Plus,
    ReceiptText,
    type LucideIcon,
} from 'lucide-react';

interface InProgressDocument {
    id: string;
    name: string;
    status: DocumentStatus;
    sent_at: string | null;
    created_at: string;
    signers: SignerSummary[];
}

interface SignaturesSummary {
    // Coleção vazia chega como [] do Laravel; por isso a leitura sempre usa `?? 0`.
    statusCounts: Partial<Record<DocumentStatus, number>>;
    inProgress: InProgressDocument[];
}

interface Props {
    signatures: SignaturesSummary | null;
    [key: string]: unknown;
}

type CountKey = 'in_progress' | 'completed' | 'rejected';

const COUNTS: {
    key: CountKey;
    label: string;
    hint: string;
    statuses: DocumentStatus[];
    icon: LucideIcon;
    tone: string;
}[] = [
    {
        key: 'in_progress',
        label: 'Em andamento',
        hint: 'esperando assinatura',
        statuses: ['sent', 'partially_signed'],
        icon: Hourglass,
        tone: 'border-t-pending [&_[data-icon]]:bg-pending-soft [&_[data-icon]]:text-pending',
    },
    {
        key: 'completed',
        label: 'Concluídos',
        hint: 'todos assinaram',
        statuses: ['completed'],
        icon: CircleCheck,
        tone: 'border-t-signed [&_[data-icon]]:bg-signed-soft [&_[data-icon]]:text-signed',
    },
    {
        key: 'rejected',
        label: 'Recusados',
        hint: 'alguém recusou',
        statuses: ['rejected'],
        icon: CircleX,
        tone: 'border-t-rejected [&_[data-icon]]:bg-rejected-soft [&_[data-icon]]:text-rejected',
    },
];

function sum(counts: SignaturesSummary['statusCounts'], statuses: DocumentStatus[]) {
    return statuses.reduce((total, s) => total + (counts[s] ?? 0), 0);
}

function totalDocuments(counts: SignaturesSummary['statusCounts']) {
    return Object.values(counts).reduce<number>((total, n) => total + (n ?? 0), 0);
}

function StatusCounts({ counts }: { counts: SignaturesSummary['statusCounts'] }) {
    return (
        <ul className="grid gap-4 sm:grid-cols-3">
            {COUNTS.map((c) => (
                <li key={c.key}>
                    <Link
                        href={route('signatures.index', { status: c.key })}
                        className={cn(
                            'flex h-full flex-col gap-4 rounded-xl border border-t-2 bg-card p-5 shadow-xs transition-shadow',
                            'hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            c.tone,
                        )}
                    >
                        <span className="flex items-center justify-between gap-3">
                            <span className="text-sm font-medium text-foreground">{c.label}</span>
                            <span data-icon className="flex size-9 items-center justify-center rounded-full">
                                <c.icon className="size-4.5" aria-hidden="true" />
                            </span>
                        </span>
                        <span className="flex items-baseline gap-2">
                            <span className="text-3xl font-semibold tabular-nums leading-none text-foreground">
                                {sum(counts, c.statuses)}
                            </span>
                            <span className="text-sm text-muted-foreground">{c.hint}</span>
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function AwaitingSignatures({ summary }: { summary: SignaturesSummary }) {
    const hasAnyDocument = totalDocuments(summary.statusCounts) > 0;

    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <header className="flex items-center justify-between gap-4 border-b px-5 py-3.5 md:px-6">
                <div className="min-w-0">
                    <h2 className="text-base font-medium">Aguardando assinatura</h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Os envios mais recentes que ainda dependem de alguém.
                    </p>
                </div>
                {summary.inProgress.length > 0 && (
                    <Button variant="ghost" size="sm" asChild className="shrink-0">
                        <Link href={route('signatures.index', { status: 'in_progress' })}>
                            Ver todos
                            <span className="sr-only"> os documentos em andamento</span>
                        </Link>
                    </Button>
                )}
            </header>

            {summary.inProgress.length === 0 ? (
                hasAnyDocument ? (
                    <EmptyState
                        icon={FileSignature}
                        title="Nada pendente por aqui"
                        description="Todos os documentos enviados já foram concluídos ou encerrados."
                        action={
                            <Button variant="outline" asChild>
                                <Link href={route('signatures.create')}>
                                    <Plus aria-hidden="true" /> Novo documento
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <EmptyState
                        icon={FileSignature}
                        title="Envie seu primeiro documento"
                        description="Suba um PDF, adicione quem precisa assinar e acompanhe cada assinatura por aqui."
                        action={
                            <Button asChild>
                                <Link href={route('signatures.create')}>
                                    <Plus aria-hidden="true" /> Novo documento
                                </Link>
                            </Button>
                        }
                    />
                )
            ) : (
                <ul className="divide-y">
                    {summary.inProgress.map((d) => (
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
                                        Enviado em {formatDate(d.sent_at ?? d.created_at)}
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
    );
}

interface ModuleInfo {
    key: ModuleKey;
    label: string;
    description: string;
    icon: LucideIcon;
    /** Rota da área do módulo; ausente enquanto o módulo não tem telas no painel. */
    href?: () => string;
}

const MODULES: ModuleInfo[] = [
    {
        key: 'signatures',
        label: 'Assinatura de documentos',
        description: 'Envie PDFs e colete assinaturas eletrônicas.',
        icon: FileSignature,
        href: () => route('signatures.index'),
    },
    {
        key: 'invoices',
        label: 'Notas fiscais',
        description: 'Emissão de NFS-e no padrão nacional.',
        icon: ReceiptText,
        href: () => route('invoices.index'),
    },
];

function ModuleList({ tenant }: { tenant: CurrentTenant }) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <h2 className="border-b px-5 py-3.5 text-base font-medium">Módulos</h2>
            <ul className="divide-y">
                {MODULES.map((m) => {
                    const active = tenant.modules.includes(m.key);
                    const href = active ? m.href?.() : undefined;
                    const state = !active
                        ? { label: 'Não contratado', className: 'bg-idle-soft text-idle' }
                        : href
                          ? { label: 'Ativo', className: 'bg-signed-soft text-signed' }
                          : { label: 'Em breve', className: 'bg-sent-soft text-sent' };

                    const body = (
                        <div className="flex items-start gap-3 px-5 py-4">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                                <m.icon className="size-4" aria-hidden="true" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p className="font-medium text-foreground">{m.label}</p>
                                    <span
                                        className={cn('rounded-md px-2 py-0.5 text-xs font-semibold', state.className)}
                                    >
                                        {state.label}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-sm text-muted-foreground">{m.description}</p>
                            </div>
                            {href && (
                                <ChevronRight
                                    className="mt-2.5 size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                    );

                    return (
                        <li key={m.key}>
                            {href ? (
                                <Link
                                    href={href}
                                    className="block transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                >
                                    {body}
                                </Link>
                            ) : (
                                body
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

export default function Dashboard({ signatures }: Props) {
    const { auth, currentTenant } = usePage<SharedData>().props;
    const firstName = auth.user?.name.trim().split(/\s+/)[0];

    if (!currentTenant) {
        return (
            <AppLayout breadcrumbs={[{ title: 'Início' }]} width="narrow">
                <Head title="Início" />
                <section className="rounded-xl border bg-card shadow-xs">
                    <EmptyState
                        icon={Building2}
                        title={firstName ? `Boas-vindas, ${firstName}` : 'Boas-vindas ao Moduhub'}
                        description="Para começar, crie um workspace para a sua empresa. É nele que ficam seus documentos, sua equipe e suas integrações."
                        action={
                            <Button asChild>
                                <Link href={route('workspaces.index')}>
                                    <Plus aria-hidden="true" /> Criar workspace
                                </Link>
                            </Button>
                        }
                    />
                </section>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Início' }]}>
            <Head title="Início" />

            <PageHeader
                title={firstName ? `Olá, ${firstName}` : 'Início'}
                description={`O que precisa da sua atenção em ${currentTenant.name}.`}
            />

            <div className="grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="flex min-w-0 flex-col gap-6">
                    {signatures ? (
                        <>
                            <StatusCounts counts={signatures.statusCounts} />
                            <AwaitingSignatures summary={signatures} />
                        </>
                    ) : (
                        <section className="rounded-xl border bg-card shadow-xs">
                            <EmptyState
                                icon={FileSignature}
                                title="Nenhum módulo com atividade ainda"
                                description="Quando a assinatura de documentos estiver contratada para este workspace, os envios pendentes aparecem aqui."
                            />
                        </section>
                    )}
                </div>

                <ModuleList tenant={currentTenant} />
            </div>
        </AppLayout>
    );
}
