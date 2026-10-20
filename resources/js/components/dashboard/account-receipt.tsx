import { cn, formatDate } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Money } from './money';
import { formatCents, type Billing } from './types';

/**
 * O gasto com a Moduhub no formato de comprovante: cada item com pontilhado até o valor, total
 * em destaque. É previsão do ciclo; impostos e descontos ficam com a fatura.
 */
export function AccountReceipt({ billing }: { billing: Billing }) {
    return (
        <section aria-labelledby="receipt-title" className="rounded-xl border bg-card px-5 py-5 shadow-xs">
            <h2 id="receipt-title" className="text-sm text-muted-foreground">
                Sua conta na Moduhub
            </h2>
            <Money cents={billing.total_cents} className="mt-1 text-[2rem] leading-none" />
            <p className="mt-2 text-xs text-muted-foreground">
                {billing.status === 'trial'
                    ? `Teste grátis até ${formatDate(billing.trial_ends_at)}. Nada é cobrado até lá.`
                    : billing.plan
                      ? `Previsão deste ciclo, que fecha em ${formatDate(billing.period_end)}.`
                      : 'Nenhum plano contratado.'}
            </p>

            {billing.lines.length > 0 && (
                <dl className="mt-5 flex flex-col gap-2.5 border-t border-dashed pt-4 text-sm">
                    {billing.lines.map((line) => (
                        <Leader key={line.label} label={line.label} value={formatCents(line.cents)} />
                    ))}
                    {billing.lines.length > 1 && <Leader label="Total previsto" value={formatCents(billing.total_cents)} strong />}
                </dl>
            )}

            {billing.status === 'past_due' && (
                <p className="mt-4 rounded-md bg-rejected-soft px-3 py-2 text-xs font-medium text-rejected" role="status">
                    Pagamento em atraso. Regularize para não perder o acesso.
                </p>
            )}

            <Link href={route('billing.show')} className="mt-4 inline-block text-sm font-medium text-primary underline-offset-4 hover:underline">
                {billing.status === 'trial' ? 'Ver planos e assinar' : 'Gerenciar assinatura'}
            </Link>
        </section>
    );
}

function Leader({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
    return (
        <div className={cn('flex items-baseline gap-2', strong && 'mt-1 border-t pt-3')}>
            <dt className={cn('shrink-0', strong ? 'font-medium' : 'text-muted-foreground')}>{label}</dt>
            <span aria-hidden="true" className="min-w-4 flex-1 translate-y-[-0.2em] border-b border-dotted border-muted-foreground/40" />
            <dd className={cn('shrink-0 tabular-nums', strong && 'font-semibold')}>{value}</dd>
        </div>
    );
}
