export type BillingType = 'PIX' | 'CREDIT_CARD';

export type SubscriptionStatus = 'trial' | 'incomplete' | 'active' | 'past_due' | 'suspended' | 'cancelled';

export type PaymentStatus = 'pending' | 'paid' | 'overdue' | 'refunded' | 'chargeback' | 'cancelled';

export type ProductKey = 'signatures' | 'invoices' | 'mailing';

/** Plano do catálogo (PlanCatalogService::plan). Cota nula = sem limite; excedente nulo = a cota é limite. */
export interface CatalogPlan {
    id: number;
    key: string;
    name: string;
    description: string | null;
    price_cents: number;
    trial_days: number;
    product: ProductKey;
    included_quota: number | null;
    daily_quota: number | null;
    overage_cents: number | null;
}

export interface CatalogProduct {
    key: ProductKey;
    name: string;
    description: string | null;
    released: boolean;
    plans: CatalogPlan[];
}

/** Unidade de cada produto, para "50 documentos por mês". */
export const PRODUCT_UNIT: Record<ProductKey, [string, string]> = {
    signatures: ['documento', 'documentos'],
    invoices: ['nota', 'notas'],
    mailing: ['e-mail', 'e-mails'],
};

export const PRODUCT_LABEL: Record<ProductKey, string> = {
    signatures: 'Assinatura de documentos',
    invoices: 'Notas fiscais',
    mailing: 'Disparo de e-mails',
};

export function quotaLabel(plan: CatalogPlan): string {
    const [one, many] = PRODUCT_UNIT[plan.product];
    if (plan.included_quota === null) return `${many[0].toUpperCase()}${many.slice(1)} sem limite`;
    return `${plan.included_quota.toLocaleString('pt-BR')} ${plan.included_quota === 1 ? one : many} por mês`;
}

export interface BillingSubscription {
    status: SubscriptionStatus;
    blocked: boolean;
    plans: CatalogPlan[];
    price_cents: number;
    billing_type: BillingType | null;
    has_gateway_subscription: boolean;
    card_brand: string | null;
    card_last4: string | null;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancelled_at: string | null;
    past_due_since: string | null;
    grace_ends_at: string | null;
}

export interface BillingPlan {
    id: number;
    name: string;
    description: string | null;
    price_cents: number;
    modules: { key: string; name: string; included_quota: number | null; overage_cents: number | null }[];
}

export interface BillingAccountData {
    name: string;
    tax_id: string;
    email: string;
    phone: string;
    postal_code: string;
    address_number: string;
    address_complement: string;
}

export interface BillingPaymentRow {
    id: string;
    kind: 'subscription' | 'overage';
    description: string | null;
    status: PaymentStatus;
    billing_type: string | null;
    value_cents: number;
    due_date: string;
    paid_at: string | null;
    invoice_url: string | null;
    failure_reason: string | null;
    lines: { label: string; cents: number }[] | null;
    has_pix: boolean;
}

/** Aviso de cobrança no topo do painel (prop compartilhada `billingNotice`). */
export interface BillingNotice {
    blocked: boolean;
    status: SubscriptionStatus;
    trial_ends_at: string | null;
    grace_ends_at: string | null;
    access_ends_at: string | null;
    can_manage: boolean;
}

export const SUBSCRIPTION_STATUS: Record<SubscriptionStatus, { label: string; tone: 'signed' | 'pending' | 'rejected' | 'idle' | 'sent' }> = {
    trial: { label: 'Teste grátis', tone: 'sent' },
    incomplete: { label: 'Aguardando pagamento', tone: 'pending' },
    active: { label: 'Ativa', tone: 'signed' },
    past_due: { label: 'Em atraso', tone: 'rejected' },
    suspended: { label: 'Bloqueada', tone: 'rejected' },
    cancelled: { label: 'Cancelada', tone: 'idle' },
};

export const PAYMENT_STATUS: Record<PaymentStatus, { label: string; tone: 'signed' | 'pending' | 'rejected' | 'idle' }> = {
    pending: { label: 'Em aberto', tone: 'pending' },
    paid: { label: 'Paga', tone: 'signed' },
    overdue: { label: 'Vencida', tone: 'rejected' },
    refunded: { label: 'Estornada', tone: 'idle' },
    chargeback: { label: 'Contestada', tone: 'rejected' },
    cancelled: { label: 'Cancelada', tone: 'idle' },
};
