import type { CatalogPlan, SubscriptionStatus } from '@/components/billing/types';

/** Plano ativo que pode ir para a cortesia; os ocultos do site também. */
export interface ComplimentaryPlan extends CatalogPlan {
    is_public: boolean;
}

export interface AdminCustomer {
    id: string;
    name: string;
    slug: string;
    tax_id: string | null;
    created_at: string | null;
    owners: { name: string; email: string }[];
    subscription: {
        status: SubscriptionStatus;
        blocked: boolean;
        has_gateway_subscription: boolean;
        trial_ends_at: string | null;
        price_cents: number;
        plans: CatalogPlan[];
    } | null;
}
