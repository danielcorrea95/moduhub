import { useMemo, useState } from 'react';
import type { CatalogPlan, CatalogProduct, ProductKey } from './types';

/**
 * Carrinho da assinatura: no máximo um plano por produto. Guarda o id escolhido de cada produto
 * (ou nada) e devolve os planos e o total.
 */
export function usePlanCart(products: CatalogProduct[], initialIds: number[]) {
    const [selected, setSelected] = useState<Partial<Record<ProductKey, number>>>(() => {
        const start: Partial<Record<ProductKey, number>> = {};
        for (const product of products) {
            const hit = product.plans.find((p) => initialIds.includes(p.id));
            if (hit) start[product.key] = hit.id;
        }
        return start;
    });

    const plans = useMemo(
        () =>
            products
                .map((product) => product.plans.find((p) => p.id === selected[product.key]))
                .filter((p): p is CatalogPlan => Boolean(p)),
        [products, selected],
    );

    const toggle = (product: ProductKey, planId: number | null) =>
        setSelected((current) => {
            const next = { ...current };
            if (planId === null || current[product] === planId) delete next[product];
            else next[product] = planId;
            return next;
        });

    return {
        selected,
        plans,
        ids: plans.map((p) => p.id),
        keys: plans.map((p) => p.key),
        totalCents: plans.reduce((sum, p) => sum + p.price_cents, 0),
        toggle,
    };
}
