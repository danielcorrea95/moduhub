import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Money } from './money';
import { formatCents, formatCentsCompact, formatMonth, type InvoicesSection } from './types';

const W = 600;
const H = 160;

/**
 * O destaque da página: quanto já foi faturado em notas no mês, desenhado dia a dia por cima do
 * mês anterior. A pergunta que responde é "estou à frente ou atrás de onde estava no mês passado?".
 */
export function InvoicePace({ invoices }: { invoices: InvoicesSection }) {
    const { pace } = invoices;
    const current = pace.current;
    const previous = pace.previous;
    const total = current[current.length - 1] ?? 0;
    const sameDayBefore = previous[Math.min(pace.today, previous.length) - 1] ?? 0;
    const change = sameDayBefore > 0 ? ((total - sameDayBefore) / sameDayBefore) * 100 : null;
    const count = invoices.months[invoices.months.length - 1]?.count ?? 0;

    const span = Math.max(pace.days_in_month, previous.length);
    const max = Math.max(1, ...current, ...previous);
    const x = (i: number) => (i / (span - 1)) * W;
    const y = (v: number) => H - (v / max) * (H - 8);
    const line = (values: number[]) => values.map((v, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
    const area = current.length > 1 ? `${line(current)} L${x(current.length - 1).toFixed(1)},${H} L0,${H} Z` : '';

    const todayX = (x(current.length - 1) / W) * 100;
    const todayY = (y(total) / H) * 100;

    return (
        <section aria-labelledby="pace-title" className="rounded-xl border bg-card px-5 pt-5 pb-4 shadow-xs md:px-7 md:pt-6">
            <div className="flex flex-col-reverse gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 flex-1">
                    <h2 id="pace-title" className="text-sm text-muted-foreground">
                        Notas emitidas em {formatMonth(pace.month)}
                    </h2>
                    <Money cents={total} className="mt-1 text-[2.75rem] leading-none md:text-5xl" />
                    <p className="mt-3 max-w-[60ch] text-sm text-muted-foreground">
                        {count === 1 ? '1 nota' : `${count} notas`} até hoje.{' '}
                        {change === null ? (
                            `Nada emitido até o dia ${pace.today} de ${formatMonth(pace.previous_month)}.`
                        ) : (
                            <>
                                No dia {pace.today} de {formatMonth(pace.previous_month)} eram {formatCents(sameDayBefore)},{' '}
                                <span className={cn('font-medium', change >= 0 ? 'text-signed' : 'text-rejected')}>
                                    {change >= 0 ? `${formatPercent(change)} a mais` : `${formatPercent(-change)} a menos`}
                                </span>
                                .
                            </>
                        )}
                    </p>
                </div>
                <div className="flex shrink-0 gap-2 self-end sm:self-start">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={route('invoices.index')}>Ver notas</Link>
                    </Button>
                    {invoices.issuer_ready && (
                        <Button asChild size="sm">
                            <Link href={route('invoices.create')}>
                                <Plus aria-hidden="true" /> Nova nota
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <figure className="mt-6">
                <div className="relative h-40">
                    <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" className="absolute inset-0 h-full w-full overflow-visible" aria-hidden="true">
                        <line x1="0" x2={W} y1={H} y2={H} className="stroke-border" vectorEffect="non-scaling-stroke" />
                        {previous.length > 1 && (
                            <path d={line(previous)} fill="none" className="stroke-idle/45" strokeWidth="1.5" strokeDasharray="4 4" vectorEffect="non-scaling-stroke" />
                        )}
                        <g className="pace-reveal">
                        {area && <path d={area} className="fill-meter-invoices/10" />}
                        {current.length > 1 && (
                            <path
                                d={line(current)}
                                fill="none"
                                className="stroke-meter-invoices"
                                strokeWidth="2.5"
                                strokeLinejoin="round"
                                vectorEffect="non-scaling-stroke"
                            />
                        )}
                        </g>
                    </svg>
                    {/* Marcador fora do SVG: com preserveAspectRatio="none" um círculo viraria elipse. */}
                    <span
                        aria-hidden="true"
                        className="absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-card bg-meter-invoices shadow-[0_0_0_4px_rgb(14_124_134/0.15)]"
                        style={{ left: `${todayX}%`, top: `${todayY}%` }}
                    />
                </div>
                <figcaption className="mt-2 flex flex-wrap items-center justify-between gap-x-6 gap-y-1 text-xs text-muted-foreground tabular-nums">
                    <span className="flex gap-4">
                        <span>dia 1</span>
                        <span className="hidden sm:inline">hoje é dia {pace.today}</span>
                    </span>
                    <span className="flex items-center gap-4">
                        <Key className="h-0.5 bg-meter-invoices" label={formatMonth(pace.month)} />
                        <Key className="h-0 border-t-2 border-dashed border-idle/45" label={formatMonth(pace.previous_month)} />
                    </span>
                </figcaption>
            </figure>

            <MonthsStrip invoices={invoices} />
        </section>
    );
}

/** Os seis meses em miniatura, para dar contexto ao mês atual sem competir com o gráfico. */
function MonthsStrip({ invoices }: { invoices: InvoicesSection }) {
    const max = Math.max(1, ...invoices.months.map((m) => m.total_cents));
    const last = invoices.months.length - 1;

    return (
        <ol aria-label="Valor emitido nos últimos seis meses" className="mt-5 grid grid-cols-3 gap-x-4 gap-y-4 border-t pt-4 sm:grid-cols-6">
            {invoices.months.map((m, i) => (
                <li key={m.month} className="flex min-w-0 flex-col gap-1">
                    <span className={cn('truncate text-xs capitalize', i === last ? 'font-medium text-foreground' : 'text-muted-foreground')}>
                        {formatMonth(m.month, true)}
                    </span>
                    <span className={cn('truncate text-sm tabular-nums', i === last ? 'font-semibold' : 'text-muted-foreground')}>
                        {m.total_cents > 0 ? formatCentsCompact(m.total_cents) : '—'}
                        <span className="sr-only"> ({formatCents(m.total_cents)})</span>
                    </span>
                    <span aria-hidden="true" className="mt-0.5 h-[3px] rounded-full bg-muted">
                        <span
                            className={cn('block h-full rounded-full', i === last ? 'bg-meter-invoices' : 'bg-meter-invoices/35')}
                            style={{ width: `${(m.total_cents / max) * 100}%` }}
                        />
                    </span>
                </li>
            ))}
        </ol>
    );
}

function Key({ className, label }: { className: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <span aria-hidden="true" className={cn('w-4', className)} />
            {label}
        </span>
    );
}

function formatPercent(value: number) {
    return `${value.toLocaleString('pt-BR', { maximumFractionDigits: value < 10 ? 1 : 0 })}%`;
}
