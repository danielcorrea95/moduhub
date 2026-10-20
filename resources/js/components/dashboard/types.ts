import type { Counters, Rates } from '@/components/mailing/email-log';
import type { EmailUsageData } from '@/components/mailing/types';
import type { SignerSummary } from '@/components/signer-progress';
import type { DocumentStatus } from '@/components/status-badge';

export interface DashboardAlert {
    tone: 'danger' | 'warning';
    title: string;
    description: string | null;
    href: string | null;
    action: string | null;
}

export interface Billing {
    plan: string | null;
    status: 'trial' | 'active' | 'past_due' | 'cancelled' | null;
    trial_ends_at: string | null;
    period_start: string;
    period_end: string;
    lines: { label: string; cents: number }[];
    total_cents: number;
}

export interface SignaturesSection {
    // Coleção vazia chega como [] do Laravel; por isso a leitura sempre usa `?? 0`.
    statusCounts: Partial<Record<DocumentStatus, number>>;
    month: { sent: number; completed: number };
    inProgress: {
        id: string;
        name: string;
        status: DocumentStatus;
        sent_at: string | null;
        created_at: string;
        signers: SignerSummary[];
    }[];
}

export interface InvoicePace {
    month: string;
    previous_month: string;
    today: number;
    days_in_month: number;
    /** Acumulado em centavos, um valor por dia, até hoje. */
    current: number[];
    /** Acumulado do mês anterior inteiro. */
    previous: number[];
}

export interface InvoicesSection {
    months: { month: string; count: number; total_cents: number }[];
    pace: InvoicePace;
    pending: number;
    issuer_ready: boolean;
    usage: { used: number; included: number | null; overage_allowed: boolean; period_end: string; plan: string | null };
}

export interface MailingSection {
    domain: { domain: string; status: 'pending' | 'verified' | 'failed' } | null;
    days: number;
    totals: Counters & { rates: Rates };
    tracking: boolean;
    usage: EmailUsageData;
}

export interface DashboardSections {
    signatures?: SignaturesSection;
    invoices?: InvoicesSection;
    mailing?: MailingSection;
}

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const compactMoney = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', notation: 'compact', maximumFractionDigits: 1 });

export const formatCents = (cents: number) => money.format(cents / 100);
export const formatCentsCompact = (cents: number) => compactMoney.format(cents / 100);

const monthName = new Intl.DateTimeFormat('pt-BR', { month: 'long' });
const monthShort = new Intl.DateTimeFormat('pt-BR', { month: 'short' });

/** "2026-10" → "outubro" / "out". Meio-dia para não cair no mês anterior por causa do fuso. */
export const formatMonth = (ym: string, short = false) => (short ? monthShort : monthName).format(new Date(`${ym}-15T12:00:00`)).replace('.', '');
