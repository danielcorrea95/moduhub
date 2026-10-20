import { cn, formatDate } from '@/lib/utils';
import type { EmailUsageData } from './types';

const number = new Intl.NumberFormat('pt-BR');

/** Uso da cota de disparo: hoje e no período do plano. Só conta o que sai pelo SMTP e pela API. */
export function UsageMeter({ usage, compact = false }: { usage: EmailUsageData; compact?: boolean }) {
    return (
        <div className="flex flex-col gap-4">
            {!compact && (
                <p className="text-sm text-muted-foreground">
                    {usage.free ? 'Plano gratuito.' : `Plano ${usage.plan}.`} Conta cada e-mail enviado pelo SMTP ou pela API. Convites de
                    assinatura, códigos e NFS-e não entram.
                </p>
            )}
            <Meter label="Hoje" used={usage.daily.used} limit={usage.daily.limit} note="Renova à meia-noite (horário de Brasília)" />
            <Meter
                label={usage.free ? 'Este mês' : 'Neste ciclo'}
                used={usage.monthly.used}
                limit={usage.monthly.limit}
                note={`Renova em ${formatDate(usage.monthly.resets_at)}`}
            />
        </div>
    );
}

function Meter({ label, used, limit, note }: { label: string; used: number; limit: number | null; note: string }) {
    const ratio = limit ? Math.min(1, used / limit) : 0;
    const tone = limit === null ? 'bg-primary' : ratio >= 1 ? 'bg-rejected' : ratio >= 0.8 ? 'bg-pending' : 'bg-primary';

    return (
        <div>
            <div className="flex items-baseline justify-between gap-3 text-sm">
                <span className="font-medium">{label}</span>
                <span className="tabular-nums">
                    {number.format(used)}
                    <span className="text-muted-foreground"> {limit === null ? 'enviados, sem limite' : `de ${number.format(limit)}`}</span>
                </span>
            </div>
            {limit !== null && (
                <div
                    className="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                    role="meter"
                    aria-label={`${label}: ${used} de ${limit} e-mails`}
                    aria-valuemin={0}
                    aria-valuemax={limit}
                    aria-valuenow={Math.min(used, limit)}
                >
                    <div className={cn('h-full rounded-full transition-[width] duration-500', tone)} style={{ width: `${ratio * 100}%` }} />
                </div>
            )}
            <p className={cn('mt-1.5 text-xs', limit !== null && used >= limit ? 'font-medium text-rejected' : 'text-muted-foreground')}>
                {limit !== null && used >= limit ? `Limite atingido. ${note}.` : `${note}.`}
            </p>
        </div>
    );
}
