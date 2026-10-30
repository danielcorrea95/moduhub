import type { ComplimentaryPlan } from '@/components/admin/customer-types';
import { PRODUCT_LABEL, quotaLabel, type ProductKey } from '@/components/billing/types';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

/**
 * Um plano por produto, como no carrinho, mas sem preço: na cortesia o plano só dá as cotas.
 * Clicar no plano escolhido tira o produto.
 */
export function ComplimentaryPlanPicker({
    plans,
    selected,
    onChange,
}: {
    plans: ComplimentaryPlan[];
    selected: number[];
    onChange: (ids: number[]) => void;
}) {
    const products = [...new Set(plans.map((p) => p.product))] as ProductKey[];

    const toggle = (plan: ComplimentaryPlan) => {
        const others = selected.filter((id) => plans.find((p) => p.id === id)?.product !== plan.product);
        onChange(selected.includes(plan.id) ? others : [...others, plan.id]);
    };

    if (products.length === 0) {
        return <p className="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">Nenhum plano ativo. Cadastre os planos em Admin → Planos.</p>;
    }

    return (
        <div className="flex flex-col gap-6">
            {products.map((product) => (
                <fieldset key={product} className="flex flex-col gap-2">
                    <legend className="mb-2 text-sm font-medium">{PRODUCT_LABEL[product]}</legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {plans
                            .filter((p) => p.product === product)
                            .map((plan) => {
                                const active = selected.includes(plan.id);
                                return (
                                    <button
                                        key={plan.id}
                                        type="button"
                                        role="checkbox"
                                        aria-checked={active}
                                        onClick={() => toggle(plan)}
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors',
                                            active ? 'border-primary bg-primary/5' : 'hover:bg-muted/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-sm border',
                                                active && 'border-primary bg-primary text-primary-foreground',
                                            )}
                                        >
                                            {active && <Check className="size-3" />}
                                        </span>
                                        <span className="flex flex-col">
                                            <span className="font-medium">
                                                {plan.name}
                                                {!plan.is_public && <span className="ml-2 text-xs font-normal text-muted-foreground">oculto do site</span>}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {quotaLabel(plan)}
                                                {plan.daily_quota !== null && `, até ${plan.daily_quota.toLocaleString('pt-BR')} por dia`}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                    </div>
                </fieldset>
            ))}
        </div>
    );
}
