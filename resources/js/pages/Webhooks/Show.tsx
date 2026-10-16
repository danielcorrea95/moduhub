import EmptyState from '@/components/empty-state';
import { ConfirmAction } from '@/components/confirm-action';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDateTime } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, Inbox, Send, Trash2 } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type DeliveryStatus = 'pending' | 'succeeded' | 'failed';

interface Endpoint {
    id: string;
    url: string;
    events: string[];
    is_active: boolean;
    last_success_at: string | null;
    last_failure_at: string | null;
    consecutive_failures: number;
}

interface Delivery {
    id: string;
    event: string;
    status: string;
    attempt: number;
    response_status: number | null;
    response_body: string | null;
    next_attempt_at: string | null;
    delivered_at: string | null;
    created_at: string;
}

interface Props {
    endpoint: Endpoint;
    deliveries: Delivery[];
    [key: string]: unknown;
}

const DELIVERY_STATUS: Record<DeliveryStatus, { label: string; className: string }> = {
    pending: { label: 'Aguardando', className: 'bg-pending-soft text-pending' },
    succeeded: { label: 'Entregue', className: 'bg-signed-soft text-signed' },
    failed: { label: 'Falhou', className: 'bg-rejected-soft text-rejected' },
};

const ALL_EVENTS = '*';

function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

function Fact({ label, children, tone }: { label: string; children: ReactNode; tone?: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className={cn('mt-0.5 font-medium text-foreground', tone)}>{children}</dd>
        </div>
    );
}

function DeliveryItem({ delivery: d }: { delivery: Delivery }) {
    const [open, setOpen] = useState(false);
    const status = DELIVERY_STATUS[d.status as DeliveryStatus] ?? {
        label: d.status,
        className: 'bg-idle-soft text-idle',
    };

    return (
        <Collapsible open={open} onOpenChange={setOpen} asChild>
            <li>
                <CollapsibleTrigger className="flex w-full items-center gap-4 px-5 py-3.5 text-left transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring md:px-6">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <code className="font-mono text-sm text-foreground">{d.event}</code>
                            <span className={cn('rounded-md px-2 py-0.5 text-xs font-semibold', status.className)}>
                                {status.label}
                            </span>
                            {d.response_status !== null && (
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    Resposta HTTP {d.response_status}
                                </span>
                            )}
                            {d.attempt > 1 && (
                                <span className="text-sm text-pending">Tentativa {d.attempt}</span>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {formatDateTime(d.created_at)}
                            {d.next_attempt_at && <>, nova tentativa em {formatDateTime(d.next_attempt_at)}</>}
                        </p>
                    </div>
                    <ChevronDown
                        className={cn('size-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')}
                        aria-hidden="true"
                    />
                    <span className="sr-only">{open ? 'Ocultar resposta' : 'Ver resposta'}</span>
                </CollapsibleTrigger>
                <CollapsibleContent className="px-5 pb-4 md:px-6">
                    <p className="mb-1.5 text-sm font-medium">Resposta do seu servidor</p>
                    {d.response_body ? (
                        <pre className="max-h-80 overflow-auto rounded-lg border bg-muted/50 p-3 font-mono text-xs whitespace-pre-wrap break-all text-foreground">
                            {d.response_body}
                        </pre>
                    ) : (
                        <p className="rounded-lg border border-dashed px-3 py-2.5 text-sm text-muted-foreground">
                            O servidor não devolveu corpo de resposta.
                        </p>
                    )}
                </CollapsibleContent>
            </li>
        </Collapsible>
    );
}

export default function WebhookShow({ endpoint, deliveries }: Props) {
    const [pinging, setPinging] = useState(false);

    const ping = () =>
        router.post(route('webhooks.ping', endpoint.id), {}, {
            preserveScroll: true,
            onStart: () => setPinging(true),
            onFinish: () => setPinging(false),
        });

    const remove = () => router.delete(route('webhooks.destroy', endpoint.id));

    const host = hostOf(endpoint.url);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Integrações' },
                { title: 'Webhooks', href: route('webhooks.index') },
                { title: host },
            ]}
        >
            <Head title={`Webhook ${host}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Endpoint de webhook</h1>
                    <p className="mt-1 font-mono text-sm break-all text-muted-foreground">{endpoint.url}</p>
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    <Button onClick={ping} disabled={pinging}>
                        {pinging ? <Spinner /> : <Send aria-hidden="true" />}
                        Enviar teste
                    </Button>
                    <ConfirmAction
                        trigger={
                            <Button variant="outline" className="text-destructive hover:text-destructive">
                                <Trash2 aria-hidden="true" />
                                Remover
                            </Button>
                        }
                        title="Remover este endpoint?"
                        description={
                            <>
                                <span className="font-mono break-all text-foreground">{endpoint.url}</span> deixa de
                                receber avisos na hora, e o histórico de entregas abaixo é apagado junto. Para voltar a
                                receber, será preciso cadastrar o endpoint de novo e trocar o segredo no seu sistema.
                            </>
                        }
                        confirmLabel="Remover endpoint"
                        destructive
                        onConfirm={remove}
                    />
                </div>
            </div>

            {!endpoint.is_active && (
                <p className="rounded-lg bg-idle-soft px-4 py-3 text-sm text-foreground/80">
                    Este endpoint está pausado. Enquanto estiver assim, nenhum aviso é entregue, nem o de teste.
                </p>
            )}

            <section className="rounded-xl border bg-card p-5 md:p-6">
                <dl className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                    <Fact label="Situação" tone={endpoint.is_active ? 'text-signed' : 'text-idle'}>
                        {endpoint.is_active ? 'Enviando' : 'Pausado'}
                    </Fact>
                    <Fact
                        label="Falhas seguidas"
                        tone={endpoint.consecutive_failures > 0 ? 'text-rejected' : undefined}
                    >
                        <span className="tabular-nums">{endpoint.consecutive_failures}</span>
                    </Fact>
                    <Fact label="Última entrega">{formatDateTime(endpoint.last_success_at)}</Fact>
                    <Fact label="Última falha">{formatDateTime(endpoint.last_failure_at)}</Fact>
                </dl>

                <div className="mt-5 border-t pt-4">
                    <p className="text-sm text-muted-foreground">Eventos assinados</p>
                    <ul className="mt-2 flex flex-wrap gap-1.5">
                        {endpoint.events.map((ev) => (
                            <li
                                key={ev}
                                className={cn(
                                    'rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground',
                                    ev !== ALL_EVENTS && 'font-mono',
                                )}
                            >
                                {ev === ALL_EVENTS ? 'Todos os eventos' : ev}
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <header className="border-b px-5 py-3.5 md:px-6">
                    <h2 className="text-base font-medium">Últimas entregas</h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Abra uma entrega para ver o que o seu servidor respondeu.
                    </p>
                </header>
                {deliveries.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title="Nenhuma entrega ainda"
                        description="Use “Enviar teste” para mandar um aviso de exemplo e conferir se o seu servidor responde."
                    />
                ) : (
                    <ul className="divide-y">
                        {deliveries.map((d) => (
                            <DeliveryItem key={d.id} delivery={d} />
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
