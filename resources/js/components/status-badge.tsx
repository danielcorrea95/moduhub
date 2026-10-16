import { cn } from '@/lib/utils';

export type DocumentStatus =
    | 'draft'
    | 'sent'
    | 'partially_signed'
    | 'completed'
    | 'rejected'
    | 'expired'
    | 'cancelled';

export type SignerStatus = 'pending' | 'viewed' | 'signed' | 'rejected';

export type InvoiceStatus = 'pending' | 'issued' | 'rejected' | 'cancelled';

type Tone = 'signed' | 'pending' | 'rejected' | 'sent' | 'idle';

const TONE_CLASSES: Record<Tone, string> = {
    signed: 'bg-signed-soft text-signed',
    pending: 'bg-pending-soft text-pending',
    rejected: 'bg-rejected-soft text-rejected',
    sent: 'bg-sent-soft text-sent',
    idle: 'bg-idle-soft text-idle',
};

export const DOCUMENT_STATUS: Record<DocumentStatus, { label: string; tone: Tone }> = {
    draft: { label: 'Rascunho', tone: 'idle' },
    sent: { label: 'Em andamento', tone: 'pending' },
    partially_signed: { label: 'Em andamento', tone: 'pending' },
    completed: { label: 'Concluído', tone: 'signed' },
    rejected: { label: 'Recusado', tone: 'rejected' },
    expired: { label: 'Expirado', tone: 'idle' },
    cancelled: { label: 'Cancelado', tone: 'idle' },
};

export const SIGNER_STATUS: Record<SignerStatus, { label: string; tone: Tone }> = {
    pending: { label: 'Aguardando', tone: 'idle' },
    viewed: { label: 'Visualizou', tone: 'sent' },
    signed: { label: 'Assinou', tone: 'signed' },
    rejected: { label: 'Recusou', tone: 'rejected' },
};

export const INVOICE_STATUS: Record<InvoiceStatus, { label: string; tone: Tone }> = {
    pending: { label: 'Aguardando Sefin', tone: 'pending' },
    issued: { label: 'Emitida', tone: 'signed' },
    rejected: { label: 'Rejeitada', tone: 'rejected' },
    cancelled: { label: 'Cancelada', tone: 'idle' },
};

const STATUS_MAPS = { document: DOCUMENT_STATUS, signer: SIGNER_STATUS, invoice: INVOICE_STATUS };

export function StatusBadge({
    status,
    kind = 'document',
    className,
}: {
    status: string;
    kind?: keyof typeof STATUS_MAPS;
    className?: string;
}) {
    const map = STATUS_MAPS[kind];
    const entry = (map as Record<string, { label: string; tone: Tone }>)[status] ?? {
        label: status,
        tone: 'idle' as Tone,
    };

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                TONE_CLASSES[entry.tone],
                className,
            )}
        >
            {entry.label}
        </span>
    );
}
