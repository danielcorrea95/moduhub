import { cn } from '@/lib/utils';

/**
 * O cabeçalho do e-mail como aparece na caixa de entrada de quem recebe. Recebe os valores do
 * formulário, então muda enquanto a pessoa digita.
 */
export function EnvelopePreview({
    fromName,
    fromAddress,
    replyTo,
    subject,
    muted = false,
    className,
}: {
    fromName: string;
    fromAddress: string;
    replyTo?: string | null;
    subject: string;
    muted?: boolean;
    className?: string;
}) {
    const name = fromName.trim() || 'Sua empresa';
    const initial = name.charAt(0).toUpperCase();

    return (
        <figure className={cn('overflow-hidden rounded-lg border bg-background', className)} aria-label="Prévia do e-mail na caixa de entrada">
            <div className="flex items-start gap-3 p-4">
                <span
                    aria-hidden="true"
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full text-base font-semibold',
                        muted ? 'bg-muted text-muted-foreground' : 'bg-primary text-primary-foreground',
                    )}
                >
                    {initial}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-baseline gap-x-2">
                        <span className="font-semibold text-foreground">{name}</span>
                        <span className="min-w-0 text-sm break-all text-muted-foreground">&lt;{fromAddress}&gt;</span>
                    </p>
                    <p className="text-sm text-muted-foreground">
                        para você{replyTo ? `, respostas para ${replyTo}` : ''}
                    </p>
                    <p className="mt-3 font-medium text-foreground">{subject}</p>
                    <div className="mt-3 space-y-2" aria-hidden="true">
                        <span className="block h-2 w-11/12 rounded-full bg-muted" />
                        <span className="block h-2 w-3/4 rounded-full bg-muted" />
                    </div>
                </div>
            </div>
            <figcaption className="sr-only">
                De {name}, {fromAddress}. Assunto: {subject}.
            </figcaption>
        </figure>
    );
}
