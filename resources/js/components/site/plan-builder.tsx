import { PRODUCT_LABEL, PRODUCT_UNIT, quotaLabel, type CatalogProduct } from '@/components/billing/types';
import { usePlanCart } from '@/components/billing/use-plan-cart';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const money = (cents: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

// O total animado sempre com centavos, para os dígitos não pularem durante a contagem.
const moneyFixed = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);

/** O total sobe ou desce até o novo valor quando o carrinho muda (resposta ao clique, ~300 ms). */
function useTweenedNumber(target: number) {
    const [value, setValue] = useState(target);
    const from = useRef(target);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setValue(target);
            from.current = target;
            return;
        }
        const start = performance.now();
        const initial = from.current;
        let frame = 0;
        const step = (now: number) => {
            const t = Math.min(1, (now - start) / 300);
            const eased = 1 - Math.pow(1 - t, 3);
            const next = Math.round(initial + (target - initial) * eased);
            setValue(next);
            from.current = next;
            if (t < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
        return () => cancelAnimationFrame(frame);
    }, [target]);

    return value;
}

/**
 * Preços como carrinho: um plano por produto, clique para escolher ou tirar. O resumo leva ao
 * cadastro (visitante) ou à assinatura (dono logado) com o carrinho montado. Carrinho vazio também
 * vale: o teste grátis começa com o plano de entrada de cada produto.
 */
export function PlanBuilder({ products, canRegister }: { products: CatalogProduct[]; canRegister: boolean }) {
    const { auth, currentTenant } = usePage<SharedData>().props;
    const cart = usePlanCart(products, []);
    const total = useTweenedNumber(cart.totalCents);
    const trialDays = Math.max(14, ...products.flatMap((p) => p.plans.map((plan) => plan.trial_days)));
    const sellable = products.filter((p) => p.released && p.plans.length > 0);

    const query = cart.keys.length > 0 ? { plans: cart.keys.join(',') } : {};
    const href = !auth.user
        ? route('register', query)
        : currentTenant?.role === 'owner'
          ? route('billing.show', query)
          : route('dashboard');
    const cta = auth.user
        ? 'Ir para a assinatura'
        : cart.plans.length > 0
          ? `Testar grátis por ${trialDays} dias`
          : 'Testar tudo grátis';

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-start">
            <div className="grid gap-8 md:grid-cols-3 md:gap-5">
                {sellable.map((product) => (
                    <fieldset key={product.key} className="flex min-w-0 flex-col gap-3">
                        <legend className="mb-3 text-base font-semibold">{PRODUCT_LABEL[product.key]}</legend>
                        {product.plans.map((plan) => {
                            const on = cart.selected[product.key] === plan.id;
                            return (
                                <button
                                    key={plan.id}
                                    type="button"
                                    role="checkbox"
                                    aria-checked={on}
                                    onClick={() => cart.toggle(product.key, plan.id)}
                                    className={cn(
                                        'group flex flex-col gap-1 rounded-lg border bg-background px-4 py-3.5 text-left transition-[border-color,box-shadow,background-color] duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                        on ? 'border-primary bg-primary/[0.04] shadow-[0_0_0_1px_var(--primary)]' : 'hover:border-foreground/25',
                                    )}
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium">{plan.name}</span>
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'flex size-[1.125rem] items-center justify-center rounded-full border transition-colors duration-200',
                                                on ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                                            )}
                                        >
                                            <Check className={cn('size-3 transition-transform duration-200', on ? 'scale-100' : 'scale-0')} />
                                        </span>
                                    </span>
                                    <span className="flex items-baseline gap-1">
                                        <span className="text-xl font-semibold tracking-tight tabular-nums">{money(plan.price_cents)}</span>
                                        <span className="text-xs text-muted-foreground">por mês</span>
                                    </span>
                                    <span className="text-sm text-foreground/70">{quotaLabel(plan)}</span>
                                    {plan.overage_cents !== null && (
                                        <span className="text-xs text-muted-foreground">{money(plan.overage_cents)} por {PRODUCT_UNIT[plan.product][0]} além disso</span>
                                    )}
                                </button>
                            );
                        })}
                    </fieldset>
                ))}
            </div>

            <aside className="flex flex-col gap-4 rounded-xl border bg-background p-5 lg:sticky lg:top-24">
                <h3 className="text-sm font-medium">Sua assinatura</h3>
                {cart.plans.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Escolha um plano em cada produto que quiser usar. Sem escolha, o teste grátis libera o plano de entrada de todos.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2 text-sm">
                        {cart.plans.map((plan) => (
                            <li key={plan.id} className="flex items-baseline justify-between gap-3">
                                <span className="min-w-0">
                                    <span className="block">{plan.name}</span>
                                    <span className="block text-xs text-muted-foreground">{PRODUCT_LABEL[plan.product]}</span>
                                </span>
                                <span className="tabular-nums">{money(plan.price_cents)}</span>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="flex items-baseline justify-between border-t pt-4">
                    <span className="text-sm">Total por mês</span>
                    <span className="text-2xl font-semibold tracking-tight tabular-nums" aria-live="polite">
                        {moneyFixed(total)}
                    </span>
                </p>
                {(canRegister || auth.user) && (
                    <Button asChild size="lg" className="h-11">
                        <Link href={href}>{cta}</Link>
                    </Button>
                )}
                <p className="text-xs text-muted-foreground">
                    {trialDays} dias grátis, sem cartão. Depois, Pix todo mês ou cartão com cobrança automática. Pagamentos pelo Asaas.
                </p>
            </aside>
        </div>
    );
}
