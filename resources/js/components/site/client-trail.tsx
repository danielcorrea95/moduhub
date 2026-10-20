import { cn } from '@/lib/utils';
import { FileSignature, Mail, ReceiptText } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

/** Quando cada etapa conclui (ms). A primeira acompanha a tinta da assinatura (.signature-ink: 0,8 s + 1,5 s). */
const DONE_AT = [2300, 3300, 4300];

/**
 * O momento central do site: um cliente atravessando os três produtos. O contrato é assinado, a
 * NFS-e é autorizada e o e-mail com tudo é entregue, cada etapa acendendo a trilha até a próxima.
 * Roda uma vez; com movimento reduzido, mostra direto o estado final.
 */
export function ClientTrail({ className }: { className?: string }) {
    const [done, setDone] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDone(DONE_AT.length);
            return;
        }
        const timers = DONE_AT.map((ms, i) => setTimeout(() => setDone(i + 1), ms));
        return () => timers.forEach(clearTimeout);
    }, []);

    return (
        <ol aria-label="Exemplo: contrato assinado, nota emitida e e-mail entregue" className={cn('relative flex flex-col gap-4', className)}>
            <Stage
                index={0}
                done={done}
                icon={<FileSignature />}
                title="Contrato nº 2026-10-0019"
                meta="Acme Consultoria e Tavares Advocacia"
                pending="Aguardando Rafael"
                complete="Assinado"
            >
                <div className="mt-3 flex items-end justify-between gap-4 border-t pt-3">
                    <div className="min-w-0">
                        <span
                            aria-hidden="true"
                            className="signature-ink block whitespace-nowrap text-[1.6rem] leading-none text-primary"
                            style={{ fontFamily: "'Great Vibes', cursive" }}
                        >
                            Rafael T.
                        </span>
                        <span className="mt-1 block border-t border-foreground/20 pt-1 text-xs text-muted-foreground">Rafael Tavares, contratado</span>
                    </div>
                    <span className="shrink-0 text-xs text-muted-foreground">2 de 2 assinaturas</span>
                </div>
            </Stage>

            <Stage
                index={1}
                done={done}
                icon={<ReceiptText />}
                title="NFS-e nº 128"
                meta="Consultoria em gestão, Novo Hamburgo (RS)"
                pending="Transmitindo"
                complete="Autorizada"
            >
                <div className="mt-3 flex items-baseline justify-between gap-4 border-t pt-3">
                    <span className="text-xs text-muted-foreground">Valor do serviço</span>
                    <span className="text-lg font-semibold tabular-nums">R$ 4.800,00</span>
                </div>
            </Stage>

            <Stage
                index={2}
                done={done}
                icon={<Mail />}
                title="financeiro@acmeconsultoria.com.br"
                meta="Para rafael@tavares.adv.br"
                pending="Enviando"
                complete="Entregue"
            >
                <p className="mt-3 border-t pt-3 text-xs text-muted-foreground">Contrato assinado e NFS-e 128 em anexo (PDF e XML)</p>
            </Stage>
        </ol>
    );
}

function Stage({
    index,
    done,
    icon,
    title,
    meta,
    pending,
    complete,
    children,
}: {
    index: number;
    done: number;
    icon: ReactNode;
    title: string;
    meta: string;
    pending: string;
    complete: string;
    children: ReactNode;
}) {
    const isDone = done > index;
    const isActive = done === index;
    const last = index === DONE_AT.length - 1;

    return (
        <li className="relative grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-4">
            {/* A trilha: o trecho até a próxima etapa acende quando esta conclui. */}
            {!last && (
                <span aria-hidden="true" className="absolute left-[1.0625rem] top-10 h-[calc(100%-1.5rem)] w-0.5 rounded-full bg-border">
                    <span className={cn('block h-full w-full origin-top rounded-full bg-signed transition-transform duration-700 ease-out', isDone ? 'scale-y-100' : 'scale-y-0')} />
                </span>
            )}
            <span
                aria-hidden="true"
                className={cn(
                    'relative z-10 mt-3 flex size-9 items-center justify-center rounded-full border-2 bg-card transition-colors duration-500 [&>svg]:size-4',
                    isDone ? 'border-signed bg-signed text-white' : isActive ? 'border-primary text-primary' : 'border-border text-muted-foreground',
                )}
            >
                {icon}
            </span>
            <article
                className={cn(
                    'rounded-xl border bg-card px-5 py-4 transition-shadow duration-500',
                    isActive || isDone
                        ? 'shadow-[0_1px_2px_rgb(27_34_48/0.05),0_18px_40px_-24px_rgb(27_34_48/0.35)]'
                        : 'shadow-none',
                )}
            >
                <header className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate text-sm font-semibold">{title}</h3>
                        <p className="truncate text-xs text-muted-foreground">{meta}</p>
                    </div>
                    <span
                        className={cn(
                            'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors duration-500',
                            isDone ? 'bg-signed-soft text-signed' : isActive ? 'bg-pending-soft text-pending' : 'bg-idle-soft text-idle',
                        )}
                    >
                        <span aria-live="polite">{isDone ? complete : pending}</span>
                    </span>
                </header>
                {children}
            </article>
        </li>
    );
}
