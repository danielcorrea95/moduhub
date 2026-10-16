import { cn, formatDateTime } from '@/lib/utils';

export interface DocumentEvent {
    id: string | number;
    type: string;
    data: Record<string, unknown> | null;
    user: { name: string; email: string } | null;
    created_at: string;
}

const TYPE_LABEL: Record<string, string> = {
    created: 'Documento criado',
    updated: 'Documento alterado',
    sent: 'Enviado para assinatura',
    reminded: 'Link reenviado por e-mail',
    viewed: 'Documento aberto por um signatário',
    code_sent: 'Código de verificação enviado',
    code_verified: 'Código de verificação confirmado',
    signed: 'Assinatura registrada',
    rejected: 'Assinatura recusada',
    cancelled: 'Documento cancelado',
    expired: 'Prazo expirado',
    downloaded: 'PDF baixado',
    finalized: 'PDF final gerado',
    copies_sent: 'Cópia assinada enviada aos envolvidos',
};

const CHANGE_LABEL: Record<string, string> = {
    signer_added: 'Signatário adicionado',
    signer_updated: 'Signatário atualizado',
    signer_removed: 'Signatário removido',
    signers_reordered: 'Ordem de assinatura alterada',
};

const DOT: Record<string, string> = {
    signed: 'bg-signed',
    finalized: 'bg-signed',
    rejected: 'bg-rejected',
    sent: 'bg-sent',
    cancelled: 'bg-idle',
    expired: 'bg-idle',
};

function describe(event: DocumentEvent): string {
    const change = typeof event.data?.change === 'string' ? event.data.change : null;
    if (change) return CHANGE_LABEL[change] ?? change;
    return TYPE_LABEL[event.type] ?? event.type;
}

function signerName(event: DocumentEvent): string | null {
    const signer = event.data?.signer;
    if (signer && typeof signer === 'object' && 'name' in signer && typeof signer.name === 'string') {
        return signer.name;
    }
    return null;
}

/** Trilha de auditoria: lista discreta, do registro mais antigo ao mais recente. */
export function EventTimeline({ events }: { events: DocumentEvent[] }) {
    if (events.length === 0) {
        return <p className="text-sm text-muted-foreground">Nenhum evento registrado ainda.</p>;
    }

    return (
        <ol className="relative space-y-4 border-l border-border pl-5">
            {events.map((e) => {
                const name = signerName(e);
                return (
                    <li key={e.id} className="relative">
                        <span
                            className={cn(
                                'absolute -left-[calc(1.5625rem+0.5px)] top-1.5 size-2.5 rounded-full ring-4 ring-background',
                                DOT[e.type] ?? 'bg-muted-foreground/40',
                            )}
                            aria-hidden="true"
                        />
                        <p className="text-sm text-foreground">
                            {describe(e)}
                            {name && <span className="text-muted-foreground">: {name}</span>}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            <time dateTime={e.created_at}>{formatDateTime(e.created_at)}</time>
                            {e.user && <> por {e.user.name}</>}
                        </p>
                    </li>
                );
            })}
        </ol>
    );
}
