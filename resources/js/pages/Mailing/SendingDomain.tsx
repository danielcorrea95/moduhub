import { ConfirmAction } from '@/components/confirm-action';
import InputError from '@/components/input-error';
import { DnsRecords, recordProgress } from '@/components/mailing/dns-records';
import { EnvelopePreview } from '@/components/mailing/envelope-preview';
import { SenderFields } from '@/components/mailing/sender-fields';
import { SmtpAccess } from '@/components/mailing/smtp-access';
import { TestEmailForm } from '@/components/mailing/test-email-form';
import type { EmailUsageData, SenderForm, SendingDomainData, SmtpData } from '@/components/mailing/types';
import { UsageMeter } from '@/components/mailing/usage-meter';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDateTime } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, CircleAlert, RefreshCw, Trash2 } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface Props {
    tenantName: string;
    platformSender: { name: string; address: string };
    domain: SendingDomainData | null;
    smtp: SmtpData;
    usage: EmailUsageData;
    [key: string]: unknown;
}

const SAMPLE_SUBJECT = 'Contrato de prestação de serviços aguarda sua assinatura';

export default function SendingDomain({ tenantName, platformSender, domain, smtp, usage }: Props) {
    // Domínio que já foi verificado uma vez continua na gestão, mesmo se o DNS quebrar depois.
    const managing = domain !== null && (domain.status === 'verified' || domain.verified_at !== null);

    return (
        <AppLayout breadcrumbs={[{ title: 'Workspace' }, { title: 'Domínio de envio' }]}>
            <Head title="Domínio de envio" />

            <PageHeader
                title="Domínio de envio"
                description={
                    managing
                        ? `Convites de assinatura, códigos de verificação e NFS-e saem como ${domain.from_local_part}@${domain.domain}.`
                        : 'Convites de assinatura, códigos de verificação e NFS-e podem sair com o endereço da sua empresa, em vez do endereço da Moduhub.'
                }
            />

            {managing ? (
                <ManageDomain tenantName={tenantName} platformSender={platformSender} domain={domain} smtp={smtp} usage={usage} />
            ) : (
                <SetupGuide tenantName={tenantName} platformSender={platformSender} domain={domain} />
            )}
        </AppLayout>
    );
}

/* ------------------------------------------------------------------ */
/* Configuração: uma sequência de três passos num trilho contínuo.     */
/* ------------------------------------------------------------------ */

type StepState = 'done' | 'current' | 'error' | 'upcoming';

function SetupGuide({ tenantName, platformSender, domain }: Pick<Props, 'tenantName' | 'platformSender' | 'domain'>) {
    const dnsState: StepState = !domain ? 'upcoming' : domain.status === 'failed' ? 'error' : 'current';

    return (
        <section className="rounded-xl border bg-card px-5 py-6 md:px-8 md:py-8" aria-label="Configuração do domínio de envio">
            <ol>
                <Step
                    number={1}
                    title="Domínio e remetente"
                    state={domain ? 'done' : 'current'}
                    summary={domain ? `${domain.domain}, enviando como ${domain.from_local_part}@${domain.domain}` : undefined}
                    aside={domain ? <RemoveDomainButton domain={domain.domain} label="Trocar domínio" /> : undefined}
                >
                    {!domain && <CreateDomainForm tenantName={tenantName} />}
                </Step>

                <Step
                    number={2}
                    title="Registros no DNS"
                    state={dnsState}
                    summary={!domain ? 'Mostramos os registros depois que você informar o domínio.' : undefined}
                >
                    {domain && <DnsStep domain={domain} />}
                </Step>

                <Step number={3} title="Pronto para enviar" state="upcoming" last>
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {domain
                            ? `Quando os registros forem encontrados, os e-mails passam a sair como ${domain.from_local_part}@${domain.domain}. Até lá, saem como ${platformSender.name} <${platformSender.address}>.`
                            : `Até lá, os e-mails saem como ${platformSender.name} <${platformSender.address}>.`}
                    </p>
                </Step>
            </ol>
        </section>
    );
}

function Step({
    number,
    title,
    state,
    summary,
    aside,
    last = false,
    children,
}: {
    number: number;
    title: string;
    state: StepState;
    summary?: string;
    aside?: ReactNode;
    last?: boolean;
    children?: ReactNode;
}) {
    const open = state === 'current' || state === 'error';

    return (
        <li className="relative grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-4" aria-current={open ? 'step' : undefined}>
            {!last && (
                <span
                    aria-hidden="true"
                    className={cn('absolute top-10 bottom-1 left-[1.0625rem] w-0.5 rounded-full', state === 'done' ? 'bg-signed/40' : 'bg-border')}
                />
            )}
            <StepMarker number={number} state={state} />

            <div className={cn('min-w-0', last ? 'pb-0' : 'pb-9')}>
                <div className="flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div className="min-w-0">
                        <h2 className={cn('text-base font-medium', state === 'upcoming' && 'text-muted-foreground')}>{title}</h2>
                        {summary && <p className="text-sm break-all text-muted-foreground">{summary}</p>}
                    </div>
                    {aside}
                </div>
                {children && <div className={cn(open ? 'mt-5' : 'mt-1')}>{children}</div>}
            </div>
        </li>
    );
}

function StepMarker({ number, state }: { number: number; state: StepState }) {
    const base = 'relative z-10 flex size-9 items-center justify-center rounded-full text-sm font-semibold';

    if (state === 'done') {
        return (
            <span className={cn(base, 'bg-signed text-white')}>
                <Check className="size-4" aria-hidden="true" />
                <span className="sr-only">Passo {number} concluído</span>
            </span>
        );
    }

    if (state === 'error') {
        return (
            <span className={cn(base, 'bg-rejected text-white')}>
                <CircleAlert className="size-4" aria-hidden="true" />
                <span className="sr-only">Passo {number} com problema</span>
            </span>
        );
    }

    return (
        <span
            className={cn(
                base,
                state === 'current' ? 'bg-primary text-primary-foreground ring-4 ring-accent' : 'border-2 border-border bg-card text-muted-foreground',
            )}
        >
            {number}
        </span>
    );
}

function CreateDomainForm({ tenantName }: { tenantName: string }) {
    const { data, setData, post, processing, errors } = useForm<SenderForm & { domain: string }>({
        domain: '',
        from_name: tenantName,
        from_local_part: 'contato',
        reply_to: '',
    });
    const domain = cleanDomain(data.domain);

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('sending-domain.store'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="flex flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="domain">Domínio da empresa</Label>
                    <Input
                        id="domain"
                        value={data.domain}
                        onChange={(e) => setData('domain', e.target.value)}
                        placeholder="suaempresa.com.br"
                        spellCheck={false}
                        autoCapitalize="none"
                        aria-invalid={Boolean(errors.domain)}
                        aria-describedby="domain_hint"
                        className="font-mono"
                        autoFocus
                        required
                    />
                    <p id="domain_hint" className="text-sm text-muted-foreground">
                        O mesmo do site e do e-mail da empresa. Você vai precisar acessar o DNS dele. Gmail, Outlook e afins não servem.
                    </p>
                    <InputError message={errors.domain} />
                </div>

                <SenderFields data={data} setData={(k, v) => setData(k, v)} errors={errors} domain={domain} />

                <div>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Continuar para o DNS
                    </Button>
                </div>
            </div>

            <aside className="lg:pt-7">
                <p className="mb-2 text-sm text-muted-foreground">Como o signatário vai ver</p>
                <EnvelopePreview
                    fromName={data.from_name}
                    fromAddress={`${data.from_local_part || 'contato'}@${domain || 'suaempresa.com.br'}`}
                    replyTo={data.reply_to || null}
                    subject={`${tenantName} · ${SAMPLE_SUBJECT}`}
                />
            </aside>
        </form>
    );
}

function DnsStep({ domain }: { domain: SendingDomainData }) {
    return (
        <div className="flex flex-col gap-6">
            {domain.status === 'failed' ? (
                <Notice tone="error">
                    A verificação falhou: o provedor de envio não encontrou os registros DKIM por 72 horas. Confira os nomes e valores abaixo. Se
                    continuar falhando, troque o domínio para gerar registros novos.
                </Notice>
            ) : (
                <p className="max-w-prose text-sm text-muted-foreground">
                    Copie cada registro para o painel de DNS do seu domínio. A propagação costuma levar de minutos a algumas horas, e conferimos
                    sozinhos a cada hora.
                </p>
            )}

            <DnsRecords records={domain.records} domain={domain.domain} />

            <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                <VerifyButton variant="default" />
                {domain.last_checked_at && (
                    <p className="text-sm text-muted-foreground">Última conferência em {formatDateTime(domain.last_checked_at)}.</p>
                )}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Gestão: domínio verificado, uma aba por assunto.                    */
/* ------------------------------------------------------------------ */

const TABS = [
    { value: 'geral', label: 'Visão geral' },
    { value: 'remetente', label: 'Remetente' },
    { value: 'dns', label: 'Registros DNS' },
    { value: 'smtp', label: 'SMTP' },
] as const;

type TabValue = (typeof TABS)[number]['value'];

function initialTab(): TabValue {
    if (typeof window === 'undefined') return 'geral';
    const value = new URLSearchParams(window.location.search).get('aba');

    return TABS.some((t) => t.value === value) ? (value as TabValue) : 'geral';
}

function ManageDomain({ tenantName, platformSender, domain, smtp, usage }: Props & { domain: SendingDomainData }) {
    const [tab, setTab] = useState<TabValue>(initialTab);

    const changeTab = (value: string) => {
        setTab(value as TabValue);
        // Mantém a aba na URL para o voltar e o recarregar, sem nova visita ao servidor.
        const url = new URL(window.location.href);
        if (value === 'geral') url.searchParams.delete('aba');
        else url.searchParams.set('aba', value);
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <Tabs value={tab} onValueChange={changeTab} className="flex flex-col gap-0">
            <div className="-mx-1 overflow-x-auto px-1">
                <TabsList className="h-auto w-full justify-start gap-1 rounded-none border-b bg-transparent p-0">
                    {TABS.map((t) => (
                        <TabsTrigger
                            key={t.value}
                            value={t.value}
                            className="relative rounded-none border-b-2 border-transparent px-3 pt-2 pb-3 text-muted-foreground shadow-none data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                        >
                            {t.label}
                            {t.value === 'dns' && domain.status === 'failed' && (
                                <span className="ml-2 size-2 rounded-full bg-rejected" aria-label="com problema" />
                            )}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </div>

            <TabsContent value="geral" className="mt-6">
                <Overview tenantName={tenantName} platformSender={platformSender} domain={domain} smtp={smtp} usage={usage} onNavigate={changeTab} />
            </TabsContent>

            <TabsContent value="remetente" className="mt-6">
                <SenderSettings tenantName={tenantName} domain={domain} />
            </TabsContent>

            <TabsContent value="dns" className="mt-6">
                <Panel>
                    <DnsRecords records={domain.records} domain={domain.domain} />
                    <div className="mt-6 flex flex-wrap items-center gap-3 border-t pt-5">
                        <VerifyButton />
                        {domain.last_checked_at && (
                            <p className="text-sm text-muted-foreground">
                                Última conferência em {formatDateTime(domain.last_checked_at)}. Conferimos sozinhos a cada hora.
                            </p>
                        )}
                    </div>
                </Panel>

                <section className="mt-6 flex flex-col gap-4 rounded-xl border border-rejected/25 p-5 sm:flex-row sm:items-center sm:justify-between md:p-6">
                    <div>
                        <h3 className="font-medium">Remover domínio</h3>
                        <p className="mt-0.5 max-w-prose text-sm text-muted-foreground">
                            Os e-mails voltam a sair como {platformSender.name} &lt;{platformSender.address}&gt;, e as credenciais SMTP deixam de
                            funcionar.
                        </p>
                    </div>
                    <RemoveDomainButton domain={domain.domain} label="Remover domínio" destructive />
                </section>
            </TabsContent>

            <TabsContent value="smtp" className="mt-6">
                <Panel>
                    <SmtpAccess smtp={smtp} domain={domain} usage={usage} />
                </Panel>
            </TabsContent>
        </Tabs>
    );
}

function Overview({
    tenantName,
    platformSender,
    domain,
    smtp,
    usage,
    onNavigate,
}: Props & { domain: SendingDomainData; onNavigate: (tab: string) => void }) {
    const active = domain.status === 'verified';
    const { found, total } = recordProgress(domain.records);

    return (
        <div className="flex flex-col gap-6">
            {!active && (
                <Notice tone="error">
                    Os registros DKIM não estão mais no DNS de {domain.domain}. Enquanto isso, os e-mails saem como {platformSender.name} &lt;
                    {platformSender.address}&gt;.{' '}
                    <button type="button" onClick={() => onNavigate('dns')} className="font-medium underline underline-offset-2">
                        Ver registros
                    </button>
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <Panel>
                    <h3 className="text-base font-medium">Como o signatário vê</h3>
                    <EnvelopePreview
                        className="mt-4"
                        fromName={active ? domain.from_name : platformSender.name}
                        fromAddress={active ? `${domain.from_local_part}@${domain.domain}` : platformSender.address}
                        replyTo={active ? domain.reply_to : null}
                        subject={`${tenantName} · ${SAMPLE_SUBJECT}`}
                        muted={!active}
                    />
                    <div className="mt-6 border-t pt-5">
                        <TestEmailForm />
                    </div>
                </Panel>

                <Panel>
                    <dl className="flex flex-col divide-y text-sm">
                        <SummaryRow label="Domínio">
                            <span className="flex flex-wrap items-center gap-2">
                                <span className="font-mono break-all">{domain.domain}</span>
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        active ? 'bg-signed-soft text-signed' : 'bg-rejected-soft text-rejected',
                                    )}
                                >
                                    {active ? 'Verificado' : 'Falhou'}
                                </span>
                            </span>
                        </SummaryRow>
                        {domain.verified_at && <SummaryRow label="Verificado em">{formatDateTime(domain.verified_at)}</SummaryRow>}
                        <SummaryRow label="Registros DNS">
                            <button type="button" onClick={() => onNavigate('dns')} className="text-left text-primary underline-offset-2 hover:underline">
                                {found} de {total} encontrados
                            </button>
                        </SummaryRow>
                        <SummaryRow label="SMTP">
                            <button type="button" onClick={() => onNavigate('smtp')} className="text-left text-primary underline-offset-2 hover:underline">
                                {smtp.credentials.length === 0
                                    ? 'Nenhuma credencial'
                                    : `${smtp.credentials.length} ${smtp.credentials.length === 1 ? 'credencial' : 'credenciais'}`}
                            </button>
                        </SummaryRow>
                        {domain.last_checked_at && <SummaryRow label="Última conferência">{formatDateTime(domain.last_checked_at)}</SummaryRow>}
                    </dl>
                    <div className="mt-5">
                        <VerifyButton />
                    </div>
                </Panel>
            </div>

            <Panel>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] lg:gap-10">
                    <div>
                        <h3 className="text-base font-medium">Cota de envio</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {usage.free ? 'Plano gratuito.' : `Plano ${usage.plan}.`} Conta cada e-mail que o seu sistema envia pelo SMTP ou pela
                            API. Convites de assinatura, códigos e NFS-e não entram.
                        </p>
                        <button
                            type="button"
                            onClick={() => onNavigate('smtp')}
                            className="mt-3 text-sm text-primary underline-offset-2 hover:underline"
                        >
                            Configurar o SMTP
                        </button>
                    </div>
                    <UsageMeter usage={usage} compact />
                </div>
            </Panel>
        </div>
    );
}

function SenderSettings({ tenantName, domain }: { tenantName: string; domain: SendingDomainData }) {
    const { data, setData, patch, processing, errors, recentlySuccessful, isDirty } = useForm<SenderForm>({
        from_name: domain.from_name,
        from_local_part: domain.from_local_part,
        reply_to: domain.reply_to ?? '',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(route('sending-domain.update'), { preserveScroll: true });
    };

    return (
        <Panel>
            <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="flex flex-col gap-6">
                    <SenderFields data={data} setData={(k, v) => setData(k, v)} errors={errors} domain={domain.domain} />
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing && <Spinner />}
                            Salvar remetente
                        </Button>
                        {recentlySuccessful && (
                            <p className="flex items-center gap-1.5 text-sm text-signed" role="status">
                                <Check className="size-4" aria-hidden="true" />
                                Remetente salvo
                            </p>
                        )}
                    </div>
                </div>
                <aside className="lg:pt-7">
                    <p className="mb-2 text-sm text-muted-foreground">Como o signatário vai ver</p>
                    <EnvelopePreview
                        fromName={data.from_name}
                        fromAddress={`${data.from_local_part || 'contato'}@${domain.domain}`}
                        replyTo={data.reply_to || null}
                        subject={`${tenantName} · ${SAMPLE_SUBJECT}`}
                    />
                </aside>
            </form>
        </Panel>
    );
}

/* ------------------------------------------------------------------ */
/* Peças compartilhadas                                                 */
/* ------------------------------------------------------------------ */

function Panel({ children }: { children: ReactNode }) {
    return <section className="rounded-xl border bg-card p-5 md:p-6">{children}</section>;
}

function SummaryRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[8.5rem_minmax(0,1fr)] gap-3 py-3 first:pt-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

function Notice({ tone, children }: { tone: 'error'; children: ReactNode }) {
    return (
        <div
            role="alert"
            className={cn('flex items-start gap-3 rounded-lg border p-4 text-sm', tone === 'error' && 'border-rejected/30 bg-rejected-soft text-rejected')}
        >
            <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>{children}</p>
        </div>
    );
}

function VerifyButton({ variant = 'outline' }: { variant?: 'default' | 'outline' }) {
    const [checking, setChecking] = useState(false);

    return (
        <Button
            type="button"
            variant={variant}
            disabled={checking}
            onClick={() =>
                router.post(route('sending-domain.verify'), {}, {
                    preserveScroll: true,
                    onStart: () => setChecking(true),
                    onFinish: () => setChecking(false),
                })
            }
        >
            {checking ? <Spinner /> : <RefreshCw aria-hidden="true" />}
            Verificar agora
        </Button>
    );
}

function RemoveDomainButton({ domain, label, destructive = false }: { domain: string; label: string; destructive?: boolean }) {
    return (
        <ConfirmAction
            trigger={
                <Button type="button" variant="outline" size="sm" className="shrink-0">
                    {destructive && <Trash2 aria-hidden="true" />}
                    {label}
                </Button>
            }
            title={`Remover ${domain}?`}
            description="Os e-mails voltam a sair pelo endereço da Moduhub e as credenciais SMTP deixam de funcionar. Para usar o domínio de novo, será preciso refazer a verificação no DNS."
            confirmLabel="Remover domínio"
            destructive
            onConfirm={() => router.delete(route('sending-domain.destroy'))}
        />
    );
}

function cleanDomain(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/^[a-z]+:\/\//, '')
        .replace(/\/.*$/, '')
        .replace(/^@/, '')
        .replace(/^www\./, '');
}
