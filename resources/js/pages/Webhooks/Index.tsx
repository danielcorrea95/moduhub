import { CopyButton } from '@/components/copy-button';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, ChevronRight, Plus, ShieldAlert, Webhook, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

export interface WebhookEndpointRow {
    id: string;
    url: string;
    events: string[];
    is_active: boolean;
    last_success_at: string | null;
    last_failure_at: string | null;
    consecutive_failures: number;
}

interface Reveal {
    id: string;
    url: string;
    secret: string;
}

interface Props {
    endpoints: WebhookEndpointRow[];
    eventsByModule: Record<string, Record<string, string>>;
    reveal: Reveal | null;
    [key: string]: unknown;
}

interface CreateEndpointForm {
    url: string;
    events: string[];
    is_active: boolean;
}

const MODULE_LABEL: Record<string, string> = {
    signatures: 'Assinaturas',
    invoices: 'Notas fiscais',
};

const ALL_EVENTS = '*';

function EventOption({
    id,
    label,
    event,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    event: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-start gap-3 px-3.5 py-3 hover:bg-muted/40"
        >
            <Checkbox id={id} className="mt-0.5" checked={checked} onCheckedChange={(c) => onChange(c === true)} />
            <span className="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                <span className="text-sm">{label}</span>
                <code className="font-mono text-xs text-muted-foreground">{event}</code>
            </span>
        </label>
    );
}

export default function WebhooksIndex({ endpoints, eventsByModule, reveal }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<CreateEndpointForm>({
        url: '',
        events: [],
        is_active: true,
    });

    const [revealOpen, setRevealOpen] = useState(Boolean(reveal));

    useEffect(() => {
        setRevealOpen(Boolean(reveal));
    }, [reveal]);

    const toggle = (event: string, checked: boolean) =>
        setData('events', checked ? [...data.events, event] : data.events.filter((e) => e !== event));

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('webhooks.store'), { onSuccess: () => reset() });
    };

    const eventsError = errors.events ?? Object.entries(errors).find(([k]) => k.startsWith('events.'))?.[1];

    return (
        <AppLayout breadcrumbs={[{ title: 'Integrações' }, { title: 'Webhooks' }]}>
            <Head title="Webhooks" />

            <PageHeader
                title="Notificações por webhook"
                description="Avisamos o seu sistema por HTTPS assim que algo acontece, como um documento assinado. Cada envio leva uma assinatura HMAC para você confirmar que veio do Moduhub."
                actions={
                    <Button asChild variant="outline">
                        <Link href={`${route('docs.api')}#webhooks`}>
                            <BookOpen /> Documentação da API
                        </Link>
                    </Button>
                }
            />

            {revealOpen && reveal && (
                <section
                    aria-labelledby="reveal-title"
                    className="rounded-xl border border-pending/30 bg-pending-soft p-5 md:p-6"
                >
                    <div className="flex items-start gap-3">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-pending" aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <h2 id="reveal-title" className="font-semibold text-foreground">
                                Copie o segredo deste webhook agora
                            </h2>
                            <p className="mt-1 text-sm text-foreground/80">
                                Use-o para validar a assinatura HMAC de cada envio para{' '}
                                <span className="font-mono break-all">{reveal.url}</span>. Esta é a única vez que o
                                segredo aparece.
                            </p>
                            <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <code className="min-w-0 flex-1 rounded-md border bg-card px-3 py-2 font-mono text-sm break-all text-foreground select-all">
                                    {reveal.secret}
                                </code>
                                <CopyButton
                                    value={reveal.secret}
                                    variant="default"
                                    size="default"
                                    label="Copiar segredo"
                                    copiedLabel="Segredo copiado"
                                />
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="-mt-1 -mr-2 shrink-0"
                            onClick={() => setRevealOpen(false)}
                            aria-label="Fechar aviso do segredo"
                        >
                            <X />
                        </Button>
                    </div>
                </section>
            )}

            <section className="rounded-xl border bg-card p-5 md:p-6">
                <h2 className="text-base font-medium">Adicionar endpoint</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    O endereço do seu sistema que vai receber os avisos.
                </p>

                <form onSubmit={submit} className="mt-5 flex flex-col gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="url">URL de destino</Label>
                        <Input
                            id="url"
                            type="url"
                            value={data.url}
                            onChange={(e) => setData('url', e.target.value)}
                            placeholder="https://api.suaempresa.com.br/webhooks/moduhub"
                            spellCheck={false}
                            aria-invalid={Boolean(errors.url)}
                            className="font-mono"
                            required
                        />
                        <p className="text-sm text-muted-foreground">Precisa começar com https://.</p>
                        <InputError message={errors.url} />
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Eventos</legend>
                        <p className="text-sm text-muted-foreground">Escolha quais acontecimentos geram um aviso.</p>
                        <div className="mt-1 divide-y rounded-lg border">
                            <EventOption
                                id="event-all"
                                event={ALL_EVENTS}
                                label="Todos os eventos, inclusive os que surgirem no futuro"
                                checked={data.events.includes(ALL_EVENTS)}
                                onChange={(c) => toggle(ALL_EVENTS, c)}
                            />
                            {Object.entries(eventsByModule).map(([moduleKey, events]) => (
                                <div key={moduleKey}>
                                    <p className="bg-muted/40 px-3.5 py-2 text-sm font-medium">
                                        {MODULE_LABEL[moduleKey] ?? moduleKey}
                                    </p>
                                    <div className="divide-y border-t">
                                        {Object.entries(events).map(([event, label]) => (
                                            <EventOption
                                                key={event}
                                                id={`event-${event}`}
                                                event={event}
                                                label={label}
                                                checked={data.events.includes(event)}
                                                onChange={(c) => toggle(event, c)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <InputError message={eventsError} />
                    </fieldset>

                    <div className="flex items-start gap-3">
                        <Switch
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(c) => setData('is_active', c)}
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <Label htmlFor="is_active">Começar a enviar agora</Label>
                            <p className="text-sm text-muted-foreground">
                                Desligado, o endpoint fica salvo mas não recebe avisos.
                            </p>
                        </div>
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                            Adicionar endpoint
                        </Button>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <h2 className="border-b px-5 py-3.5 text-base font-medium md:px-6">Endpoints</h2>
                {endpoints.length === 0 ? (
                    <EmptyState
                        icon={Webhook}
                        title="Nenhum endpoint cadastrado"
                        description="Adicione um endereço acima para começar a receber avisos."
                    />
                ) : (
                    <ul className="divide-y">
                        {endpoints.map((e) => (
                            <li key={e.id} className="group relative">
                                <div className="flex items-center gap-4 px-5 py-4 transition-colors group-hover:bg-muted/40 md:px-6">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={route('webhooks.show', e.id)}
                                                className="min-w-0 truncate font-mono text-sm font-medium text-foreground after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-ring"
                                            >
                                                {e.url}
                                            </Link>
                                            {!e.is_active && (
                                                <span className="rounded-md bg-idle-soft px-2 py-0.5 text-xs font-semibold text-idle">
                                                    Pausado
                                                </span>
                                            )}
                                            {e.consecutive_failures > 0 && (
                                                <span className="rounded-md bg-rejected-soft px-2 py-0.5 text-xs font-semibold text-rejected">
                                                    {e.consecutive_failures === 1
                                                        ? '1 falha seguida'
                                                        : `${e.consecutive_failures} falhas seguidas`}
                                                </span>
                                            )}
                                        </div>
                                        <ul className="mt-2 flex flex-wrap gap-1.5">
                                            {e.events.map((ev) => (
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
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
