import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { ReportPeriod } from './email-log';

type Section = 'overview' | 'messages' | 'suppressions';

const SECTIONS: { key: Section; label: string; route: string }[] = [
    { key: 'overview', label: 'Visão geral', route: 'emails.overview' },
    { key: 'messages', label: 'Mensagens', route: 'emails.messages' },
    { key: 'suppressions', label: 'Bloqueados', route: 'emails.suppressions' },
];

/** Moldura das três telas de e-mails enviados: título, navegação entre elas e o período. */
export function EmailsShell({
    section,
    period,
    suppressed,
    children,
}: {
    section: Section;
    period?: ReportPeriod;
    suppressed?: number;
    children: ReactNode;
}) {
    const current = SECTIONS.find((s) => s.key === section)!;

    return (
        <AppLayout breadcrumbs={[{ title: 'E-mail' }, { title: 'Enviados', href: route('emails.overview') }, { title: current.label }]}>
            <Head title={`E-mails · ${current.label}`} />

            <PageHeader
                title="E-mails enviados"
                description="O que saiu deste workspace: convites de assinatura, códigos, NFS-e e o que seus sistemas enviarem pelo SMTP. Cada destinatário conta como uma mensagem."
                actions={period && <PeriodPicker period={period} />}
            />

            <nav aria-label="Seções" className="-mx-4 flex gap-6 overflow-x-auto border-b px-4 md:mx-0 md:px-0">
                {SECTIONS.map((s) => (
                    <Link
                        key={s.key}
                        href={route(s.route, period && s.key !== 'suppressions' ? { periodo: period.days } : undefined)}
                        aria-current={s.key === section ? 'page' : undefined}
                        className={cn(
                            '-mb-px flex shrink-0 items-center gap-2 border-b-2 pb-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4',
                            s.key === section ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {s.label}
                        {s.key === 'suppressions' && suppressed ? (
                            <span className="rounded-full bg-rejected-soft px-1.5 text-xs text-rejected tabular-nums">{suppressed}</span>
                        ) : null}
                    </Link>
                ))}
            </nav>

            {children}
        </AppLayout>
    );
}

function PeriodPicker({ period }: { period: ReportPeriod }) {
    const choose = (days: number) => {
        const url = new URL(window.location.href);
        url.searchParams.set('periodo', String(days));
        url.searchParams.delete('page');
        url.searchParams.delete('mensagem');
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <div role="radiogroup" aria-label="Período" className="inline-flex rounded-lg border bg-card p-0.5">
            {period.options.map((days) => {
                const active = days === period.days;
                return (
                    <button
                        key={days}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        onClick={() => !active && choose(days)}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-1',
                            active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {days} dias
                    </button>
                );
            })}
        </div>
    );
}
