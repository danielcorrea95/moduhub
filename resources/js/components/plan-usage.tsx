import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn, formatDate } from '@/lib/utils';
import type { PlanUsageMeter, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

const number = new Intl.NumberFormat('pt-BR');

const BAR: Record<string, string> = {
    signatures: 'bg-meter-signatures',
    invoices: 'bg-meter-invoices',
    mailing: 'bg-meter-mailing',
};

/** Uso de cada módulo no ciclo, acima do usuário na barra lateral. Some com a barra recolhida. */
export function PlanUsage() {
    const { planUsage } = usePage<SharedData>().props;

    if (!planUsage || planUsage.length === 0) return null;

    return (
        <section
            aria-label="Uso do plano"
            className="flex flex-col gap-2.5 border-t border-sidebar-border px-2 pt-4 pb-1 group-data-[collapsible=icon]:hidden"
        >
            {planUsage.map((meter) => (
                <Meter key={meter.key} meter={meter} />
            ))}
        </section>
    );
}

function Meter({ meter }: { meter: PlanUsageMeter }) {
    const limited = meter.limit !== null;
    const ratio = limited ? Math.min(1, meter.used / (meter.limit || 1)) : 0;
    const full = limited && meter.used >= (meter.limit ?? 0);
    const counts = limited ? `${number.format(meter.used)}/${number.format(meter.limit ?? 0)}` : `${number.format(meter.used)}`;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div tabIndex={0} className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring">
                    <div className="flex items-baseline justify-between gap-2 text-xs">
                        <span className="text-sidebar-foreground">{meter.label}</span>
                        <span className={cn('tabular-nums', full ? 'font-medium text-rejected' : 'text-muted-foreground')}>{counts}</span>
                    </div>
                    <div
                        className="mt-1 h-1.5 overflow-hidden rounded-full bg-sidebar-accent"
                        role="meter"
                        aria-label={`${meter.label}: ${meter.used}${limited ? ` de ${meter.limit}` : ', sem limite'}`}
                        aria-valuemin={0}
                        aria-valuemax={meter.limit ?? meter.used}
                        aria-valuenow={limited ? Math.min(meter.used, meter.limit ?? 0) : meter.used}
                    >
                        {limited && <div className={cn('h-full rounded-full', BAR[meter.key] ?? 'bg-primary')} style={{ width: `${ratio * 100}%` }} />}
                    </div>
                </div>
            </TooltipTrigger>
            <TooltipContent side="right" align="center" className="max-w-56">
                <p className="font-medium">
                    {limited ? `${number.format(meter.used)} de ${number.format(meter.limit ?? 0)} no ciclo` : `${number.format(meter.used)} no ciclo, sem limite`}
                </p>
                {meter.detail && <p>{meter.detail}</p>}
                <p>{full ? 'Limite atingido. ' : ''}Renova em {formatDate(meter.resets_at)}.</p>
            </TooltipContent>
        </Tooltip>
    );
}
