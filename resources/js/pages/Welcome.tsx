import AppLogo from '@/components/app-logo';
import { SigningContract } from '@/components/site/signing-contract';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, FileLock2, KeyRound, Menu, ScrollText, X } from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';

interface PlanModule {
    key: string;
    name: string;
    released: boolean;
    included_quota: number | null;
    overage_cents: number | null;
}

interface Plan {
    key: string;
    name: string;
    description: string | null;
    price_cents: number;
    currency: string;
    billing_period: string;
    trial_days: number;
    modules: PlanModule[];
}

interface Props {
    canRegister: boolean;
    plans: Plan[];
    [key: string]: unknown;
}

const NAV = [
    { href: '#como-funciona', label: 'Como funciona' },
    { href: '#seguranca', label: 'Segurança' },
    { href: '#planos', label: 'Planos' },
];

const money = (cents: number, currency = 'BRL') =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency, minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

const UNIT: Record<string, [string, string]> = {
    signatures: ['documento', 'documentos'],
    invoices: ['nota fiscal', 'notas fiscais'],
};

const PERIOD: Record<string, string> = { monthly: 'mês', yearly: 'ano' };

function Container({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mx-auto w-full max-w-6xl px-5 sm:px-8', className)}>{children}</div>;
}

function SiteHeader({ canRegister }: { canRegister: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);

    const links = (
        <>
            {NAV.map((n) => (
                <a key={n.href} href={n.href} onClick={() => setOpen(false)} className="rounded-md px-3 py-2 text-sm text-foreground/75 transition-colors hover:text-foreground">
                    {n.label}
                </a>
            ))}
            <Link href={route('docs.api')} className="rounded-md px-3 py-2 text-sm text-foreground/75 transition-colors hover:text-foreground">
                Documentação da API
            </Link>
        </>
    );

    return (
        <header className="sticky top-0 z-30 border-b border-border/70 bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70">
            <Container className="flex h-16 items-center justify-between gap-4">
                <Link href={route('home')} aria-label="Moduhub, página inicial">
                    <AppLogo />
                </Link>

                <nav aria-label="Principal" className="hidden items-center md:flex">
                    {links}
                </nav>

                <div className="hidden items-center gap-2 md:flex">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={route('dashboard')}>Abrir o painel</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="ghost">
                                <Link href={route('login')}>Entrar</Link>
                            </Button>
                            {canRegister && (
                                <Button asChild>
                                    <Link href={route('register')}>Começar teste grátis</Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>

                <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-label={open ? 'Fechar menu' : 'Abrir menu'}>
                    {open ? <X /> : <Menu />}
                </Button>
            </Container>

            {open && (
                <div className="border-t bg-background md:hidden">
                    <Container className="flex flex-col py-3">
                        {links}
                        <div className="mt-3 flex gap-2 border-t pt-3">
                            {auth.user ? (
                                <Button asChild className="flex-1">
                                    <Link href={route('dashboard')}>Abrir o painel</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="outline" className="flex-1">
                                        <Link href={route('login')}>Entrar</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild className="flex-1">
                                            <Link href={route('register')}>Começar teste grátis</Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    </Container>
                </div>
            )}
        </header>
    );
}

function Hero({ canRegister, trialDays }: { canRegister: boolean; trialDays: number }) {
    return (
        <section className="overflow-hidden">
            <Container className="grid items-center gap-14 pb-20 pt-14 md:pt-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,30rem)] lg:gap-16 lg:pb-28">
                <div>
                    <h1 className="max-w-[16ch] text-[2.5rem] font-semibold leading-[1.05] tracking-[-0.025em] text-foreground sm:text-[3.25rem] lg:text-[3.75rem]">
                        Contrato enviado de manhã, assinado à tarde.
                    </h1>
                    <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-foreground/70">
                        Suba o PDF, diga quem assina e o Moduhub manda o link para cada pessoa. Você acompanha quem já assinou e recebe o
                        documento final com trilha de auditoria, pronto para guardar.
                    </p>
                    <div className="mt-9 flex flex-wrap items-center gap-3">
                        {canRegister && (
                            <Button asChild size="lg" className="h-11 px-6 text-[0.9375rem]">
                                <Link href={route('register')}>Começar teste grátis</Link>
                            </Button>
                        )}
                        <Button asChild size="lg" variant="outline" className="h-11 bg-card px-6 text-[0.9375rem]">
                            <a href="#planos">Ver planos</a>
                        </Button>
                    </div>
                    <p className="mt-4 text-sm text-muted-foreground">
                        {trialDays} dias grátis, sem cartão de crédito.
                    </p>
                </div>

                <SigningContract className="mx-auto w-full max-w-[30rem] lg:mx-0" />
            </Container>
        </section>
    );
}

const STEPS = [
    {
        title: 'Envie o PDF',
        body: 'Arraste o contrato, adicione quem precisa assinar e escolha se todos assinam ao mesmo tempo ou um depois do outro.',
    },
    {
        title: 'Cada pessoa assina pelo link',
        body: 'O signatário recebe um e-mail, confirma com um código e assina desenhando ou digitando o nome. Pelo celular, sem instalar nada.',
    },
    {
        title: 'Receba o PDF final',
        body: 'Quando o último assina, o documento é concluído com as assinaturas, a trilha de auditoria e um código para qualquer pessoa conferir.',
    },
];

function HowItWorks() {
    return (
        <section id="como-funciona" className="scroll-mt-16 border-y bg-card">
            <Container className="py-20 lg:py-24">
                <h2 className="max-w-xl text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">
                    Três passos entre o PDF e o contrato assinado
                </h2>
                <ol className="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
                    {STEPS.map((step, i) => (
                        <li key={step.title} className="relative">
                            <div className="flex items-center gap-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums">
                                    {i + 1}
                                </span>
                                {i < STEPS.length - 1 && <span className="hidden h-px flex-1 bg-border md:block" aria-hidden="true" />}
                            </div>
                            <h3 className="mt-5 text-lg font-semibold">{step.title}</h3>
                            <p className="mt-2 leading-relaxed text-foreground/70">{step.body}</p>
                        </li>
                    ))}
                </ol>
            </Container>
        </section>
    );
}

const TRUST = [
    {
        icon: ScrollText,
        title: 'Trilha de auditoria em cada documento',
        body: 'Quem abriu, quando confirmou o código, de qual IP e a que horas assinou. Tudo vai numa página final anexada ao PDF.',
    },
    {
        icon: FileLock2,
        title: 'Hash do original e do final',
        body: 'O PDF recebe uma impressão digital SHA-256 no envio e outra na conclusão. Qualquer alteração depois disso fica evidente.',
    },
    {
        icon: KeyRound,
        title: 'Selo ICP-Brasil em todo documento',
        body: 'Ao concluir, o PDF é assinado digitalmente com o certificado ICP-Brasil da plataforma. Qualquer alteração depois disso invalida o selo.',
    },
    {
        icon: BadgeCheck,
        title: 'Verificação pública',
        body: 'Cada PDF traz um código. Quem recebe o documento confere em segundos se ele é autêntico e quem assinou.',
    },
];

function Security() {
    return (
        <section id="seguranca" className="scroll-mt-16">
            <Container className="grid gap-12 py-20 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:gap-16 lg:py-24">
                <div>
                    <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">
                        Assinatura que você consegue provar depois
                    </h2>
                    <p className="mt-4 leading-relaxed text-foreground/70">
                        O valor de um contrato aparece no dia em que alguém questiona. Por isso cada assinatura deixa evidência
                        registrada, não só um desenho no papel.
                    </p>
                </div>
                <dl className="grid gap-x-10 gap-y-9 sm:grid-cols-2">
                    {TRUST.map((t) => (
                        <div key={t.title}>
                            <dt className="flex items-center gap-3 font-semibold">
                                <t.icon className="size-5 shrink-0 text-primary" aria-hidden="true" />
                                {t.title}
                            </dt>
                            <dd className="mt-2 leading-relaxed text-foreground/70">{t.body}</dd>
                        </div>
                    ))}
                </dl>
            </Container>
        </section>
    );
}

const API_SAMPLE = `curl -X POST https://app.moduhub.com.br/api/v1/signatures/documents \\
  -H "Authorization: Bearer $MODUHUB_API_KEY" \\
  -F "file=@contrato.pdf" \\
  -F "external_id=CRM-0019" \\
  -F "send=1" \\
  -F "signers[0][name]=Marina Albuquerque" \\
  -F "signers[0][email]=marina@empresa.com.br"`;

function Developers({ baseUrl }: { baseUrl: string }) {
    const sample = API_SAMPLE.replace('https://app.moduhub.com.br/api/v1', baseUrl);

    return (
        <section className="bg-[#1b2230] text-white">
            <Container className="grid items-center gap-12 py-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:py-24">
                <div>
                    <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">
                        Assinatura direto do seu sistema
                    </h2>
                    <p className="mt-4 max-w-md leading-relaxed text-white/70">
                        Uma chamada cria o documento, adiciona os signatários e envia. O seu CRM ou ERP fica sabendo de cada assinatura
                        por webhook, sem precisar ficar consultando.
                    </p>
                    <Button asChild variant="outline" className="mt-8 border-white/25 bg-transparent text-white hover:bg-white/10 hover:text-white">
                        <Link href={route('docs.api')}>Ler a documentação da API</Link>
                    </Button>
                </div>
                <pre className="overflow-x-auto rounded-xl border border-white/10 bg-white/[0.04] p-5 font-mono text-[0.8125rem] leading-relaxed text-white/85">
                    <code>{sample}</code>
                </pre>
            </Container>
        </section>
    );
}

function quotaLine(m: PlanModule): string {
    const [one, many] = UNIT[m.key] ?? ['uso', 'usos'];
    if (m.included_quota === null) return `${m.name} sem limite`;
    return `${m.included_quota.toLocaleString('pt-BR')} ${m.included_quota === 1 ? one : many} por mês`;
}

function PlanCard({ plan, canRegister, isEntry }: { plan: Plan; canRegister: boolean; isEntry: boolean }) {
    const available = plan.modules.some((m) => m.released);
    const period = PERIOD[plan.billing_period] ?? plan.billing_period;

    return (
        <article
            className={cn(
                'flex flex-col rounded-xl border bg-card p-6 sm:p-7',
                isEntry && 'border-primary/40 shadow-[0_0_0_1px_var(--primary)]',
                !available && 'bg-card/60',
            )}
        >
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-lg font-semibold">{plan.name}</h3>
                {!available && <span className="rounded-full bg-sent-soft px-2.5 py-0.5 text-xs font-medium text-sent">Em breve</span>}
            </div>
            {plan.description && <p className="mt-1.5 text-sm text-muted-foreground">{plan.description}</p>}

            <p className="mt-6 flex items-baseline gap-1">
                <span className="text-4xl font-semibold tracking-tight tabular-nums">{money(plan.price_cents, plan.currency)}</span>
                <span className="text-sm text-muted-foreground">por {period}</span>
            </p>

            <ul className="mt-6 flex-1 space-y-4 border-t pt-6">
                {plan.modules.map((m) => (
                    <li key={m.key} className="text-sm">
                        <p className="flex flex-wrap items-center gap-2 font-medium">
                            {m.name}
                            {!m.released && <span className="rounded-full bg-sent-soft px-2 py-0.5 text-xs font-medium text-sent">em breve</span>}
                        </p>
                        <p className="mt-0.5 text-foreground/70">{quotaLine(m)}</p>
                        {m.overage_cents !== null && (
                            <p className="text-muted-foreground">
                                {money(m.overage_cents)} por {(UNIT[m.key] ?? ['uso'])[0]} além disso
                            </p>
                        )}
                    </li>
                ))}
            </ul>

            <div className="mt-7">
                {available && canRegister ? (
                    <Button asChild className="w-full" variant={isEntry ? 'default' : 'outline'}>
                        <Link href={route('register')}>Começar teste grátis</Link>
                    </Button>
                ) : (
                    <Button className="w-full" variant="outline" disabled>
                        Disponível em breve
                    </Button>
                )}
            </div>
        </article>
    );
}

function Plans({ plans, canRegister }: { plans: Plan[]; canRegister: boolean }) {
    const entryKey = plans.find((p) => p.key === 'starter')?.key;

    return (
        <section id="planos" className="scroll-mt-16 border-y bg-card">
            <Container className="py-20 lg:py-24">
                <div className="max-w-2xl">
                    <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">Planos</h2>
                    <p className="mt-4 leading-relaxed text-foreground/70">
                        Mensalidade fixa com uma cota por mês. Passou da cota, você paga só o que usou a mais, sem trocar de plano. Todo
                        cadastro começa no Starter, com período de teste grátis.
                    </p>
                </div>

                {plans.length === 0 ? (
                    <p className="mt-10 text-muted-foreground">Os planos estão sendo atualizados.</p>
                ) : (
                    <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard key={plan.key} plan={plan} canRegister={canRegister} isEntry={plan.key === entryKey} />
                        ))}
                    </div>
                )}
                <p className="mt-6 text-sm text-muted-foreground">Valores em reais.</p>
            </Container>
        </section>
    );
}

function VerifyDocument() {
    const [code, setCode] = useState('');
    const normalized = code.replace(/[^a-z0-9]/gi, '').toUpperCase();

    const submit = (e: SubmitEvent) => {
        e.preventDefault();
        if (normalized.length === 12) router.visit(route('verify.show', normalized));
    };

    return (
        <section id="verificar" className="scroll-mt-16">
            <Container className="grid items-center gap-8 py-16 md:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:py-20">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight">Recebeu um documento assinado pelo Moduhub?</h2>
                    <p className="mt-2 max-w-lg leading-relaxed text-foreground/70">
                        Digite o código de 12 caracteres que aparece no rodapé do PDF para conferir se ele é autêntico e quem assinou.
                    </p>
                </div>
                <form onSubmit={submit} className="flex gap-2">
                    <label htmlFor="verify-code" className="sr-only">
                        Código de verificação
                    </label>
                    <Input
                        id="verify-code"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        placeholder="Ex.: P37ACKSJH0JJ"
                        autoComplete="off"
                        spellCheck={false}
                        maxLength={20}
                        className="h-11 bg-card font-mono uppercase tracking-wider placeholder:font-sans placeholder:normal-case placeholder:tracking-normal"
                    />
                    <Button type="submit" className="h-11 px-5" disabled={normalized.length !== 12}>
                        Verificar
                    </Button>
                </form>
            </Container>
        </section>
    );
}

function SiteFooter() {
    return (
        <footer className="border-t">
            <Container className="flex flex-col gap-6 py-10 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <AppLogo />
                <nav aria-label="Rodapé" className="flex flex-wrap gap-x-6 gap-y-2">
                    <a href="#planos" className="hover:text-foreground">
                        Planos
                    </a>
                    <Link href={route('docs.api')} className="hover:text-foreground">
                        Documentação da API
                    </Link>
                    <a href="#verificar" className="hover:text-foreground">
                        Verificar documento
                    </a>
                    <Link href={route('login')} className="hover:text-foreground">
                        Entrar
                    </Link>
                </nav>
                <p>© {new Date().getFullYear()} Moduhub</p>
            </Container>
        </footer>
    );
}

export default function Welcome({ canRegister, plans }: Props) {
    const trialDays = plans.find((p) => p.key === 'starter')?.trial_days ?? 14;
    const baseUrl = `${window.location.origin}/api/v1`;

    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title="Assinatura eletrônica de contratos" />
            <SiteHeader canRegister={canRegister} />
            <main>
                <Hero canRegister={canRegister} trialDays={trialDays} />
                <HowItWorks />
                <Security />
                <Developers baseUrl={baseUrl} />
                <Plans plans={plans} canRegister={canRegister} />
                <VerifyDocument />
            </main>
            <SiteFooter />
        </div>
    );
}
