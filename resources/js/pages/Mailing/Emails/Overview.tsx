import EmptyState from '@/components/empty-state';
import { formatCount, formatRate, type EmailReport, type ReportPeriod } from '@/components/mailing/email-log';
import { EmailsShell } from '@/components/mailing/emails-shell';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn, formatDate } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';

interface Props {
    period: ReportPeriod;
    report: EmailReport;
    tracking: boolean;
    [key: string]: unknown;
}

/** Limites a partir dos quais a AWS coloca a conta SES em revisão. */
const BOUNCE_LIMIT = 5;
const COMPLAINT_LIMIT = 0.1;

export default function EmailsOverview({ period, report, tracking }: Props) {
    const { totals } = report;
    const messages = (status?: string) => route('emails.messages', { periodo: period.days, ...(status ? { status } : {}) });

    return (
        <EmailsShell section="overview" period={period} suppressed={report.suppressed}>
            {totals.sent === 0 ? (
                <section className="rounded-xl border bg-card">
                    <EmptyState
                        icon={Mail}
                        title={`Nenhum e-mail nos últimos ${period.days} dias`}
                        description="Quando este workspace enviar um convite de assinatura, uma NFS-e ou um e-mail pelo SMTP, a entrega aparece aqui."
                    />
                </section>
            ) : (
                <>
                    <DeliveryTrail report={report} tracking={tracking} link={messages} />
                    <Problems report={report} link={messages} />
                    <DailyChart daily={report.daily} />
                    <Providers report={report} tracking={tracking} />
                    {!tracking && (
                        <p className="text-sm text-muted-foreground">
                            Abertura e clique ainda não estão ativados para este ambiente. Por enquanto aparecem só envio e entrega.
                        </p>
                    )}
                </>
            )}
        </EmailsShell>
    );
}

/**
 * O caminho de um e-mail, do envio ao clique. Cada etapa tem uma barra proporcional ao total
 * enviado, então a queda entre uma etapa e outra aparece sem precisar ler os números.
 */
function DeliveryTrail({ report, tracking, link }: { report: EmailReport; tracking: boolean; link: (status?: string) => string }) {
    const { totals } = report;
    const steps = [
        { label: 'Enviados', value: totals.sent, note: 'aceitos para envio', href: link() },
        { label: 'Entregues', value: totals.delivered, note: `${formatRate(totals.rates.delivered)} dos enviados`, href: link('delivered') },
        {
            label: 'Abertos',
            value: tracking ? totals.opened : null,
            note: tracking ? `${formatRate(totals.rates.opened)} dos entregues` : 'rastreio desativado',
            href: link('opened'),
        },
        {
            label: 'Clicaram',
            value: tracking ? totals.clicked : null,
            note: tracking ? `${formatRate(totals.rates.clicked)} dos entregues` : 'rastreio desativado',
            href: link('clicked'),
        },
    ];

    return (
        <section aria-label="Do envio ao clique" className="grid overflow-hidden rounded-xl border bg-card sm:grid-cols-2 lg:grid-cols-4">
            {steps.map((step, i) => {
                const width = step.value === null || totals.sent === 0 ? 0 : (step.value / totals.sent) * 100;
                return (
                    <Link
                        key={step.label}
                        href={step.href}
                        className={cn(
                            'group flex flex-col gap-3 px-5 py-5 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 md:px-6',
                            i > 0 && 'border-t sm:border-t-0 sm:border-l',
                            i === 2 && 'sm:border-l-0 sm:border-t lg:border-t-0 lg:border-l',
                            i === 3 && 'sm:border-t lg:border-t-0',
                        )}
                    >
                        <span className="text-sm text-muted-foreground group-hover:text-foreground">{step.label}</span>
                        <span className="text-3xl font-semibold tracking-tight tabular-nums">{step.value === null ? '—' : formatCount(step.value)}</span>
                        <span className="h-1.5 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                            <span className="block h-full rounded-full bg-primary" style={{ width: `${width}%` }} />
                        </span>
                        <span className="text-xs text-muted-foreground">{step.note}</span>
                    </Link>
                );
            })}
        </section>
    );
}

function Problems({ report, link }: { report: EmailReport; link: (status?: string) => string }) {
    const { totals } = report;
    const bounceHigh = (totals.rates.bounced ?? 0) >= BOUNCE_LIMIT;
    const complaintHigh = (totals.rates.complained ?? 0) >= COMPLAINT_LIMIT;

    const items = [
        {
            label: 'Devolvidos',
            value: totals.bounced,
            note: `${formatRate(totals.rates.bounced)} dos enviados. Acima de ${BOUNCE_LIMIT}% a AWS revisa a conta.`,
            alert: bounceHigh,
            href: link('failed'),
        },
        {
            label: 'Marcados como spam',
            value: totals.complained,
            note: `${formatRate(totals.rates.complained)} dos entregues. Acima de 0,1% a AWS revisa a conta.`,
            alert: complaintHigh,
            href: link('complained'),
        },
        { label: 'Aguardando resposta', value: totals.pending, note: 'Sem confirmação do servidor do destinatário ainda.', alert: false, href: link('pending') },
        {
            label: 'Endereços bloqueados',
            value: report.suppressed,
            note: 'Devolveram de vez ou marcaram como spam. Novos envios para eles são descartados.',
            alert: false,
            href: route('emails.suppressions'),
        },
    ];

    return (
        <section aria-label="Problemas de entrega" className="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-2 lg:grid-cols-4">
            {items.map((item) => (
                <Link
                    key={item.label}
                    href={item.href}
                    className={cn(
                        'flex flex-col gap-1 px-5 py-4 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 md:px-6',
                        item.alert ? 'bg-rejected-soft hover:bg-rejected-soft/80' : 'bg-card hover:bg-muted/40',
                    )}
                >
                    <span className="flex items-baseline justify-between gap-3">
                        <span className={cn('text-sm', item.alert ? 'font-medium text-rejected' : 'text-muted-foreground')}>{item.label}</span>
                        <span className={cn('text-lg font-semibold tabular-nums', item.value > 0 && item.alert && 'text-rejected')}>{formatCount(item.value)}</span>
                    </span>
                    <span className="text-xs text-muted-foreground">{item.note}</span>
                </Link>
            ))}
        </section>
    );
}

const weekday = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });

/** Uma coluna por dia: entregue, devolvido e aguardando empilhados, na escala do dia mais cheio. */
function DailyChart({ daily }: { daily: EmailReport['daily'] }) {
    const max = Math.max(1, ...daily.map((d) => d.sent));
    const ticks = [0, Math.floor((daily.length - 1) / 2), daily.length - 1];

    return (
        <section className="rounded-xl border bg-card px-5 py-5 md:px-6" aria-labelledby="daily-title">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="daily-title" className="text-base font-medium">
                    Envios por dia
                </h2>
                <ul className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    <Legend className="bg-signed" label="Entregues" />
                    <Legend className="bg-rejected" label="Devolvidos" />
                    <Legend className="bg-idle/50" label="Aguardando" />
                </ul>
            </div>

            <div className="mt-5 flex h-40 items-end gap-px sm:gap-0.5" role="img" aria-label={`Envios por dia, máximo de ${formatCount(max)} num dia`}>
                {daily.map((d) => {
                    const height = (d.sent / max) * 100;
                    return (
                        <Tooltip key={d.date}>
                            <TooltipTrigger asChild>
                                <div className="flex h-full min-w-0 flex-1 cursor-default items-end rounded-sm hover:bg-muted/60">
                                    <div className="flex w-full flex-col-reverse overflow-hidden rounded-t-sm" style={{ height: `${height}%` }}>
                                        <Segment value={d.delivered} total={d.sent} className="bg-signed" />
                                        <Segment value={d.bounced} total={d.sent} className="bg-rejected" />
                                        <Segment value={d.pending} total={d.sent} className="bg-idle/50" />
                                    </div>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                <p className="font-medium">{weekday.format(new Date(`${d.date}T12:00:00`))}</p>
                                <p>
                                    {formatCount(d.sent)} enviados · {formatCount(d.delivered)} entregues
                                </p>
                                {(d.bounced > 0 || d.opened > 0) && (
                                    <p>
                                        {formatCount(d.bounced)} devolvidos · {formatCount(d.opened)} abertos
                                    </p>
                                )}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </div>
            <div className="relative mt-2 h-4 text-xs text-muted-foreground tabular-nums">
                {ticks.map((i, n) => (
                    <span
                        key={n}
                        className={cn('absolute', n === 0 ? 'left-0' : n === 2 ? 'right-0' : '-translate-x-1/2')}
                        style={n === 1 ? { left: `${((i + 0.5) / daily.length) * 100}%` } : undefined}
                    >
                        {formatDate(daily[i].date).slice(0, 5)}
                    </span>
                ))}
            </div>
        </section>
    );
}

function Segment({ value, total, className }: { value: number; total: number; className: string }) {
    if (value === 0 || total === 0) return null;
    return <div className={className} style={{ height: `${(value / total) * 100}%` }} />;
}

function Legend({ className, label }: { className: string; label: string }) {
    return (
        <li className="flex items-center gap-1.5">
            <span className={cn('size-2 rounded-sm', className)} aria-hidden="true" />
            {label}
        </li>
    );
}

function Providers({ report, tracking }: { report: EmailReport; tracking: boolean }) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card" aria-labelledby="providers-title">
            <div className="px-5 pt-5 pb-3 md:px-6">
                <h2 id="providers-title" className="text-base font-medium">
                    Por provedor do destinatário
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">Devolução concentrada num provedor costuma ser filtro dele, não endereço errado.</p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[36rem] text-sm">
                    <thead>
                        <tr className="border-y bg-muted/40 text-left text-xs text-muted-foreground">
                            <th scope="col" className="px-5 py-2.5 font-medium md:px-6">
                                Provedor
                            </th>
                            <Num>Enviados</Num>
                            <Num>Entregues</Num>
                            {tracking && <Num>Abertos</Num>}
                            <Num>Devolvidos</Num>
                            <Num last>Spam</Num>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {report.providers.map((p) => (
                            <tr key={p.key}>
                                <th scope="row" className="px-5 py-3 text-left font-medium md:px-6">
                                    {p.label}
                                </th>
                                <td className="px-3 py-3 text-right tabular-nums">{formatCount(p.sent)}</td>
                                <td className="px-3 py-3 text-right tabular-nums">{formatRate(p.rates.delivered)}</td>
                                {tracking && <td className="px-3 py-3 text-right tabular-nums">{formatRate(p.rates.opened)}</td>}
                                <td className={cn('px-3 py-3 text-right tabular-nums', (p.rates.bounced ?? 0) >= BOUNCE_LIMIT && 'font-medium text-rejected')}>
                                    {formatRate(p.rates.bounced)}
                                </td>
                                <td className={cn('px-5 py-3 text-right tabular-nums md:px-6', p.complained > 0 && 'font-medium text-rejected')}>
                                    {formatCount(p.complained)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="border-t px-5 py-3 text-right md:px-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={route('emails.messages')}>Ver todas as mensagens</Link>
                </Button>
            </div>
        </section>
    );
}

function Num({ children, last = false }: { children: string; last?: boolean }) {
    return (
        <th scope="col" className={cn('py-2.5 text-right font-medium', last ? 'px-5 md:px-6' : 'px-3')}>
            {children}
        </th>
    );
}
