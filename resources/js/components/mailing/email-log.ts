export type DeliveryStatus = 'sent' | 'delayed' | 'delivered' | 'bounced' | 'rejected' | 'complained';
export type EventType = DeliveryStatus | 'opened' | 'clicked';
export type StatusFilter = 'delivered' | 'opened' | 'clicked' | 'failed' | 'complained' | 'pending';

export interface ReportPeriod {
    days: number;
    options: number[];
    from: string;
    to: string;
}

export interface Counters {
    sent: number;
    delivered: number;
    opened: number;
    clicked: number;
    bounced: number;
    complained: number;
    pending: number;
}

export interface Rates {
    delivered: number | null;
    bounced: number | null;
    opened: number | null;
    clicked: number | null;
    complained: number | null;
}

export interface EmailReport {
    totals: Counters & { rates: Rates };
    providers: (Counters & { key: string; label: string; rates: Rates })[];
    daily: (Counters & { date: string })[];
    suppressed: number;
}

export interface MessageRow {
    id: string;
    recipient: string;
    from_address: string | null;
    subject: string | null;
    origin: string;
    provider: string;
    status: DeliveryStatus;
    sent_at: string;
    delivered_at: string | null;
    opened_at: string | null;
    open_count: number;
    clicked_at: string | null;
    click_count: number;
}

export interface MessageDetail extends MessageRow {
    message_id: string;
    bounce_type: string | null;
    detail: string | null;
    events: { id: string; type: EventType; occurred_at: string; detail: string | null }[];
}

/** Situação da entrega. "Enviado" = aceito pelo SES, ainda sem resposta do servidor do destinatário. */
export const DELIVERY_STATUS: Record<DeliveryStatus, { label: string; dot: string; text: string }> = {
    sent: { label: 'Enviado', dot: 'bg-idle', text: 'text-idle' },
    delayed: { label: 'Atrasado', dot: 'bg-pending', text: 'text-pending' },
    delivered: { label: 'Entregue', dot: 'bg-signed', text: 'text-signed' },
    bounced: { label: 'Devolvido', dot: 'bg-rejected', text: 'text-rejected' },
    rejected: { label: 'Recusado', dot: 'bg-rejected', text: 'text-rejected' },
    complained: { label: 'Marcado como spam', dot: 'bg-rejected', text: 'text-rejected' },
};

export const EVENT_LABELS: Record<EventType, string> = {
    sent: 'Aceito para envio',
    delayed: 'Entrega atrasada',
    delivered: 'Entregue na caixa do destinatário',
    bounced: 'Devolvido pelo servidor do destinatário',
    rejected: 'Recusado antes do envio',
    complained: 'Marcado como spam pelo destinatário',
    opened: 'Aberto',
    clicked: 'Clicou em um link',
};

/** O código técnico do SES traduzido para o que o cliente pode fazer. */
export function explainFailure(status: DeliveryStatus, bounceType: string | null): string | null {
    if (status === 'rejected') return 'O envio foi recusado antes de sair, normalmente por anexo suspeito.';
    if (status === 'complained') return 'O destinatário marcou como spam. O endereço foi bloqueado para novos envios.';
    if (!bounceType) return status === 'delayed' ? 'O servidor do destinatário não respondeu. As tentativas continuam por algumas horas.' : null;

    if (/Suppressed|OnAccountSuppressionList/.test(bounceType)) return 'O endereço já tinha devolvido antes e está bloqueado. Nada foi enviado.';
    if (/MailboxFull/.test(bounceType)) return 'A caixa do destinatário está cheia. As tentativas continuam por algumas horas.';
    if (/UnsubscribedRecipient/.test(bounceType)) return 'O destinatário pediu para não receber mais e-mails deste remetente.';
    if (/^Transient/.test(bounceType)) return 'Falha temporária no servidor do destinatário. As tentativas continuam por algumas horas.';
    return 'O endereço não existe ou o servidor do destinatário recusou de vez. Confira se foi digitado certo.';
}

const number = new Intl.NumberFormat('pt-BR');
const percent = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

export const formatCount = (n: number) => number.format(n);
export const formatRate = (rate: number | null) => (rate === null ? '—' : `${percent.format(rate)}%`);
