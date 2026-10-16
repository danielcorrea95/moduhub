import type { SignerStatus } from '@/components/status-badge';
import { cn } from '@/lib/utils';

export interface SignerSummary {
    id: number | string;
    name: string;
    status: SignerStatus | string;
}

const DOT: Record<string, string> = {
    signed: 'bg-signed',
    rejected: 'bg-rejected',
    viewed: 'bg-sent',
    pending: 'bg-transparent ring-1 ring-inset ring-idle/60',
};

const SEGMENT: Record<string, string> = {
    signed: 'bg-signed',
    rejected: 'bg-rejected',
    viewed: 'bg-sent/40',
    pending: 'bg-idle-soft',
};

/** Nomes dos signatários com um ponto de estado em cada — quem já assinou, quem está segurando. */
export function SignerNames({
    signers,
    max = 4,
    className,
}: {
    signers: SignerSummary[];
    max?: number;
    className?: string;
}) {
    if (signers.length === 0) {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                Nenhum signatário adicionado
            </p>
        );
    }

    const visible = signers.slice(0, max);
    const hidden = signers.length - visible.length;

    return (
        <ul className={cn('flex flex-wrap items-center gap-x-4 gap-y-1 text-sm', className)}>
            {visible.map((s) => (
                <li key={s.id} className="flex min-w-0 items-center gap-1.5 text-foreground/80">
                    <span
                        className={cn('size-2 shrink-0 rounded-full', DOT[s.status] ?? DOT.pending)}
                        aria-hidden="true"
                    />
                    <span className="truncate">{s.name}</span>
                </li>
            ))}
            {hidden > 0 && <li className="text-muted-foreground">+{hidden}</li>}
        </ul>
    );
}

/** Uma faixa por signatário, preenchida quando ele assina. */
export function SignerProgress({
    signers,
    className,
}: {
    signers: SignerSummary[];
    className?: string;
}) {
    if (signers.length === 0) return null;

    const signed = signers.filter((s) => s.status === 'signed').length;

    return (
        <div
            className={cn('flex items-center gap-2', className)}
            role="img"
            aria-label={`${signed} de ${signers.length} assinaram`}
        >
            <div className="flex gap-0.5">
                {signers.map((s) => (
                    <span
                        key={s.id}
                        className={cn('h-1.5 w-5 rounded-full', SEGMENT[s.status] ?? SEGMENT.pending)}
                    />
                ))}
            </div>
            <span className="text-xs tabular-nums text-muted-foreground">
                {signed} de {signers.length}
            </span>
        </div>
    );
}
