import { useInView } from '@/hooks/use-in-view';
import { cn } from '@/lib/utils';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';

// Um aviso de cobrança que o sistema do cliente manda pelo SMTP da Moduhub.
const STEPS = [
    { label: 'Enviado pelo seu sistema', time: '08:00:02', detail: 'Pelo SMTP da Moduhub, com o seu domínio' },
    { label: 'Entregue', time: '08:00:04', detail: 'O Gmail da Marina aceitou a mensagem' },
    { label: 'Aberto', time: '08:41:15', detail: 'Marina abriu o e-mail' },
    { label: 'Clicou em “Ver boleto”', time: '08:41:32', detail: 'Link rastreado sem expor dados do cliente' },
];

/** Intervalo entre um passo e o próximo: devagar o bastante para ler cada um. */
const STEP_MS = 750;

/**
 * Um e-mail disparado pelo sistema do cliente com o domínio dele, e a entrega se confirmando passo
 * a passo, como aparece em E-mail → Enviados. Começa quando entra na tela e roda uma vez.
 */
export function EmailDelivery({ className }: { className?: string }) {
    const [ref, inView] = useInView<HTMLDivElement>();
    const [reached, setReached] = useState(-1);

    useEffect(() => {
        if (!inView) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setReached(STEPS.length - 1);
            return;
        }
        const timers = STEPS.map((_, i) => setTimeout(() => setReached(i), 350 + i * STEP_MS));
        return () => timers.forEach(clearTimeout);
    }, [inView]);

    const done = reached === STEPS.length - 1;

    return (
        <div ref={ref} className={cn('rounded-xl border bg-card shadow-[0_1px_2px_rgb(27_34_48/0.05),0_24px_48px_-24px_rgb(27_34_48/0.25)]', className)}>
            <dl className="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 border-b px-6 py-5 text-sm">
                <dt className="text-muted-foreground">De</dt>
                <dd className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="min-w-0">
                        Acme Consultoria <span className="block break-all text-muted-foreground">financeiro@acmeconsultoria.com.br</span>
                    </span>
                    <span className="inline-flex items-center gap-1 rounded-full bg-signed-soft px-2 py-0.5 text-xs font-medium text-signed">
                        <ShieldCheck className="size-3.5" aria-hidden="true" />
                        domínio verificado
                    </span>
                </dd>
                <dt className="text-muted-foreground">Para</dt>
                <dd className="min-w-0">
                    Marina Albuquerque <span className="block break-all text-muted-foreground">marina.albuquerque@gmail.com</span>
                </dd>
                <dt className="text-muted-foreground">Assunto</dt>
                <dd className="font-medium">Seu boleto de outubro vence dia 10</dd>
            </dl>

            <ol className="px-6 py-5" aria-label="Situação da entrega">
                {STEPS.map((step, i) => {
                    const on = i <= reached;
                    const last = i === STEPS.length - 1;
                    return (
                        <li key={step.label} className="relative grid grid-cols-[1.25rem_minmax(0,1fr)_auto] gap-x-3 pb-5 last:pb-0">
                            {!last && (
                                <span aria-hidden="true" className="absolute left-[0.5625rem] top-5 h-[calc(100%-1.25rem)] w-px bg-border">
                                    <span
                                        className={cn(
                                            'block h-full w-full origin-top bg-signed transition-transform ease-out',
                                            i < reached ? 'scale-y-100' : 'scale-y-0',
                                        )}
                                        style={{ transitionDuration: `${STEP_MS}ms` }}
                                    />
                                </span>
                            )}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'mt-0.5 size-[1.125rem] rounded-full border-2 transition-all duration-300',
                                    on ? 'scale-100 border-signed bg-signed' : 'scale-90 border-border bg-card',
                                )}
                            />
                            <div className={cn('transition-opacity duration-300', on ? 'opacity-100' : 'opacity-40')}>
                                <p className="text-sm font-medium">{step.label}</p>
                                <p className="text-xs text-muted-foreground">{step.detail}</p>
                            </div>
                            <time className={cn('font-mono text-xs tabular-nums text-muted-foreground transition-opacity duration-300', on ? 'opacity-100' : 'opacity-0')}>
                                {step.time}
                            </time>
                        </li>
                    );
                })}
            </ol>

            <p className="border-t px-6 py-3 text-xs text-muted-foreground" aria-live="polite">
                {done ? 'Aberto 41 minutos depois do envio. Cada mensagem fica no relatório do painel.' : 'Acompanhando a entrega…'}
            </p>
        </div>
    );
}
