import { formatCount, formatRate } from '@/components/mailing/email-log';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { MailingSection } from './types';

const DOMAIN_STATE = {
    verified: { label: 'verificado', className: 'text-signed' },
    pending: { label: 'aguardando DNS', className: 'text-pending' },
    failed: { label: 'com problema', className: 'text-rejected' },
} as const;

/**
 * Se os e-mails estão chegando: uma barra só, dividida entre entregue, aguardando, devolvido e
 * spam. Quando está tudo bem, ela é quase toda verde e não pede atenção.
 */
export function EmailHealth({ mailing }: { mailing: MailingSection }) {
    const { totals, usage } = mailing;
    const segments = [
        { key: 'delivered', label: 'Entregues', value: totals.delivered - totals.complained, className: 'bg-signed' },
        { key: 'pending', label: 'Aguardando', value: totals.pending, className: 'bg-idle/40' },
        { key: 'bounced', label: 'Devolvidos', value: totals.bounced, className: 'bg-rejected' },
        { key: 'complained', label: 'Spam', value: totals.complained, className: 'bg-pending' },
    ].filter((s) => s.value > 0);
    const monthly = usage.monthly;

    return (
        <section aria-labelledby="email-title" className="rounded-xl border bg-card px-5 py-5 shadow-xs">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id="email-title" className="text-sm text-muted-foreground">
                    E-mails nos últimos {mailing.days} dias
                </h2>
                <Link href={route('emails.overview')} className="text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2">
                    Relatório
                </Link>
            </div>

            {totals.sent === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">Nenhum e-mail enviado ainda.</p>
            ) : (
                <>
                    <p className="mt-1 flex items-baseline gap-2">
                        <span className="text-[2rem] leading-none font-semibold tracking-tight tabular-nums">{formatRate(totals.rates.delivered)}</span>
                        <span className="text-sm text-muted-foreground">entregues</span>
                    </p>

                    <div className="mt-4 flex h-2 gap-px overflow-hidden rounded-full bg-muted" role="img" aria-label={segments.map((s) => `${s.label}: ${s.value}`).join(', ')}>
                        {segments.map((s) => (
                            <span key={s.key} className={s.className} style={{ width: `${(s.value / totals.sent) * 100}%` }} />
                        ))}
                    </div>

                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                        <Stat label="Enviados" value={formatCount(totals.sent)} />
                        {mailing.tracking && <Stat label="Abertos" value={formatRate(totals.rates.opened)} />}
                        <Stat label="Devolvidos" value={formatRate(totals.rates.bounced)} bad={(totals.rates.bounced ?? 0) >= 5} />
                        <Stat label="Spam" value={formatRate(totals.rates.complained)} bad={(totals.rates.complained ?? 0) >= 0.1} />
                    </dl>
                </>
            )}

            <div className="mt-4 flex flex-col gap-1 border-t pt-3 text-xs text-muted-foreground">
                <p className="truncate">
                    {mailing.domain ? (
                        <>
                            Saindo de <span className="font-medium text-foreground">{mailing.domain.domain}</span>,{' '}
                            <span className={DOMAIN_STATE[mailing.domain.status].className}>{DOMAIN_STATE[mailing.domain.status].label}</span>
                        </>
                    ) : (
                        <>
                            Saindo pelo endereço da Moduhub.{' '}
                            <Link href={route('sending-domain.show')} className="font-medium text-primary hover:underline">
                                Usar o da empresa
                            </Link>
                        </>
                    )}
                </p>
                {monthly.limit !== null && (
                    <p className="tabular-nums">
                        Disparo pelo SMTP: {formatCount(monthly.used)} de {formatCount(monthly.limit)} no ciclo
                    </p>
                )}
            </div>
        </section>
    );
}

function Stat({ label, value, bad = false }: { label: string; value: string; bad?: boolean }) {
    return (
        <div className="flex justify-between gap-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('tabular-nums', bad && 'font-semibold text-rejected')}>{value}</dd>
        </div>
    );
}
