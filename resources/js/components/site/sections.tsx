import AppLogo from '@/components/app-logo';
import type { CatalogProduct, ProductKey } from '@/components/billing/types';
import { EmailDelivery } from '@/components/site/email-delivery';
import { NfsePreview } from '@/components/site/nfse-preview';
import { PlanBuilder } from '@/components/site/plan-builder';
import { SigningContract } from '@/components/site/signing-contract';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInView } from '@/hooks/use-in-view';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AtSign,
    BadgeCheck,
    BarChart3,
    Ban,
    ChevronDown,
    FileLock2,
    FileSignature,
    Inbox,
    KeyRound,
    Mail,
    Menu,
    ReceiptText,
    RefreshCcw,
    ScrollText,
    ShieldCheck,
    Unplug,
    X,
} from 'lucide-react';
import { useState, type ComponentType, type ReactNode, type SubmitEvent } from 'react';

export interface MailingFreeQuota {
    daily: number;
    monthly: number;
}

export interface FaqItem {
    question: string;
    answer: string;
}

type Icon = ComponentType<{ className?: string }>;

export function Container({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mx-auto w-full max-w-6xl px-5 sm:px-8', className)}>{children}</div>;
}

/** Links do menu: páginas reais de cada produto, que o buscador segue, e os planos da página inicial. */
function navLinks() {
    return [
        { href: route('products.signatures'), label: 'Assinatura eletrônica' },
        { href: route('products.invoices'), label: 'Emissor de NFS-e' },
        { href: route('products.mailing'), label: 'Disparo de e-mails' },
        { href: `${route('home')}#planos`, label: 'Planos' },
    ];
}

export function SiteHeader({ canRegister }: { canRegister: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);

    const links = (
        <>
            {navLinks().map((n) => (
                <Link key={n.href} href={n.href} onClick={() => setOpen(false)} className="rounded-md px-3 py-2 text-sm text-foreground/75 transition-colors hover:text-foreground">
                    {n.label}
                </Link>
            ))}
            <Link href={route('docs.api')} className="rounded-md px-3 py-2 text-sm text-foreground/75 transition-colors hover:text-foreground">
                API
            </Link>
        </>
    );

    const actions = auth.user ? (
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
                    <Link href={route('register')}>Testar grátis</Link>
                </Button>
            )}
        </>
    );

    return (
        <header className="sticky top-0 z-30 border-b border-border/70 bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70">
            <Container className="flex h-16 items-center justify-between gap-4">
                <Link href={route('home')} aria-label="Moduhub, página inicial">
                    <AppLogo />
                </Link>

                <nav aria-label="Principal" className="hidden items-center xl:flex">
                    {links}
                </nav>

                <div className="hidden items-center gap-2 xl:flex">{actions}</div>

                <Button variant="ghost" size="icon" className="xl:hidden" onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-label={open ? 'Fechar menu' : 'Abrir menu'}>
                    {open ? <X /> : <Menu />}
                </Button>
            </Container>

            {open && (
                <div className="border-t bg-background xl:hidden">
                    <Container className="flex flex-col py-3">
                        {links}
                        <div className="mt-3 flex gap-2 border-t pt-3 *:flex-1">{actions}</div>
                    </Container>
                </div>
            )}
        </header>
    );
}

/** O que cada produto é, faz e mostra: usado na página inicial e na página do produto. */
export const PRODUCTS: Record<
    ProductKey,
    { icon: Icon; label: string; more: string; title: string; body: (q: MailingFreeQuota) => string; visual: ComponentType<{ className?: string }>; features: { icon: Icon; title: string; body: string }[] }
> = {
    signatures: {
        icon: FileSignature,
        label: 'Assinatura eletrônica',
        more: 'Tudo sobre a assinatura eletrônica',
        title: 'Contrato enviado de manhã, assinado à tarde',
        body: () =>
            'Suba o PDF, diga quem assina e o Moduhub manda o link para cada pessoa. Ela assina pelo celular, sem conta nem certificado digital, e você recebe o documento final com a prova de quem assinou.',
        visual: SigningContract,
        features: [
            { icon: ScrollText, title: 'Trilha de auditoria', body: 'Quem abriu, quando confirmou o código, de qual IP e a que horas assinou, numa página anexada ao PDF.' },
            { icon: FileLock2, title: 'Hash do original e do final', body: 'Impressão digital SHA-256 no envio e na conclusão. Qualquer alteração depois disso fica evidente.' },
            { icon: KeyRound, title: 'Selo ICP-Brasil', body: 'O PDF concluído é assinado com o certificado ICP-Brasil da plataforma. Mexeu no arquivo, o selo invalida.' },
            { icon: BadgeCheck, title: 'Verificação pública', body: 'Cada documento traz um código. Quem recebe confere em segundos se ele é autêntico e quem assinou.' },
        ],
    },
    invoices: {
        icon: ReceiptText,
        label: 'Emissor de NFS-e',
        more: 'Tudo sobre o emissor de NFS-e',
        title: 'A nota de serviço sai junto com o contrato',
        body: () =>
            'Preencha o tomador e o serviço uma vez e a NFS-e é transmitida pela Sefin Nacional, autorizada e enviada ao cliente, sem abrir o site da prefeitura.',
        visual: NfsePreview,
        features: [
            { icon: ReceiptText, title: 'Padrão nacional', body: 'Emissão pela Sefin Nacional, o emissor do governo, para os municípios que aderiram ao padrão nacional da NFS-e.' },
            { icon: ShieldCheck, title: 'Seu certificado A1 protegido', body: 'O certificado fica cifrado e cada uso é registrado. Avisamos antes de ele vencer.' },
            { icon: RefreshCcw, title: 'Cancelar e substituir', body: 'Cancele ou substitua uma nota pelo painel, com o motivo exigido pelo leiaute.' },
            { icon: Inbox, title: 'Notas recebidas', body: 'As notas emitidas contra o seu CNPJ chegam sozinhas, com XML e PDF, para o financeiro conferir.' },
            { icon: Mail, title: 'DANFSe para o tomador', body: 'A nota autorizada vai por e-mail para o cliente, com PDF e XML, saindo do seu domínio.' },
            { icon: Unplug, title: 'Do seu sistema, pela API', body: 'O ERP ou o CRM emite a nota com uma chamada e acompanha a autorização pelo webhook.' },
        ],
    },
    mailing: {
        icon: Mail,
        label: 'Disparo de e-mails',
        more: 'Tudo sobre o disparo de e-mails',
        title: 'O e-mail do seu sistema, com o nome da sua empresa no remetente',
        body: (q) =>
            `Cobranças, avisos, recuperação de senha: o seu sistema manda por SMTP ou pela API e o Moduhub entrega com o seu domínio e conta o que aconteceu. ${q.daily.toLocaleString('pt-BR')} e-mails por dia e ${q.monthly.toLocaleString('pt-BR')} por mês sem custo, em qualquer conta.`,
        visual: EmailDelivery,
        features: [
            { icon: AtSign, title: 'Com o domínio da sua empresa', body: 'Confirme o domínio uma vez, com DKIM e SPF, e todo e-mail sai assinado por ele. Chega na caixa de entrada, não no spam.' },
            { icon: Unplug, title: 'SMTP e API', body: 'Troque o servidor de e-mail do seu sistema pelo da Moduhub, ou mande pela API. Funciona com qualquer linguagem.' },
            { icon: BarChart3, title: 'Entregue, aberto, clicado', body: 'O relatório mostra o que aconteceu com cada mensagem, destinatário por destinatário.' },
            { icon: Ban, title: 'Lista de bloqueio automática', body: 'Endereço que devolveu ou marcou como spam para de receber, e a reputação do seu domínio fica protegida.' },
        ],
    },
};

export const PRODUCT_ROUTE: Record<ProductKey, string> = {
    signatures: 'products.signatures',
    invoices: 'products.invoices',
    mailing: 'products.mailing',
};

export function FeatureList({ items }: { items: { icon: Icon; title: string; body: string }[] }) {
    return (
        <dl className="grid gap-x-10 gap-y-8 sm:grid-cols-2">
            {items.map((f) => (
                <div key={f.title}>
                    <dt className="flex items-center gap-3 font-semibold">
                        <f.icon className="size-5 shrink-0 text-primary" aria-hidden="true" />
                        {f.title}
                    </dt>
                    <dd className="mt-2 leading-relaxed text-foreground/70">{f.body}</dd>
                </div>
            ))}
        </dl>
    );
}

const SIGN_STEPS = [
    { title: 'Envie o PDF', body: 'Arraste o contrato, adicione quem assina e escolha se todos assinam juntos ou em ordem.' },
    { title: 'Cada pessoa assina pelo link', body: 'Por e-mail, com código de confirmação, desenhando ou digitando o nome. Pelo celular, sem instalar nada.' },
    { title: 'Receba o PDF final', body: 'Com as assinaturas, a trilha de auditoria, o selo ICP-Brasil e um código para qualquer pessoa conferir.' },
];

/** Os três passos da assinatura, com a linha entre eles se desenhando ao aparecer. */
export function SigningSteps() {
    const [ref, inView] = useInView<HTMLOListElement>(0.5);

    return (
        <ol ref={ref} className="grid gap-10 md:grid-cols-3 md:gap-8">
            {SIGN_STEPS.map((step, i) => (
                <li key={step.title}>
                    <div className="flex items-center gap-3">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums">
                            {i + 1}
                        </span>
                        {i < SIGN_STEPS.length - 1 && (
                            // A linha entre os passos se desenha da esquerda para a direita, um trecho depois do outro.
                            <span className="hidden h-px flex-1 bg-border md:block" aria-hidden="true">
                                <span
                                    className={cn('block h-full origin-left bg-primary transition-transform duration-700 ease-out', inView ? 'scale-x-100' : 'scale-x-0')}
                                    style={{ transitionDelay: `${i * 700}ms` }}
                                />
                            </span>
                        )}
                    </div>
                    <h3 className="mt-5 text-lg font-semibold">{step.title}</h3>
                    <p className="mt-2 leading-relaxed text-foreground/70">{step.body}</p>
                </li>
            ))}
        </ol>
    );
}

/**
 * Um produto na página inicial: rótulo, promessa (h2), texto, ilustração e recursos, com o link para
 * a página própria dele.
 */
export function ProductSection({ product, quota, flip = false, tinted = false }: { product: ProductKey; quota: MailingFreeQuota; flip?: boolean; tinted?: boolean }) {
    const p = PRODUCTS[product];
    const Visual = p.visual;
    const anchor = { signatures: 'assinaturas', invoices: 'notas', mailing: 'email' }[product];

    return (
        <section id={anchor} className={cn('scroll-mt-16', tinted && 'border-y bg-card')}>
            <Container className="flex flex-col gap-16 py-20 lg:py-24">
                <div className={cn('grid items-center gap-14 lg:gap-16', flip ? 'lg:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)_minmax(0,30rem)]')}>
                    <div className={cn(flip && 'lg:order-last')}>
                        <p className="flex items-center gap-2 text-sm font-medium text-primary">
                            <p.icon className="size-4" />
                            {p.label}
                        </p>
                        <h2 className="mt-3 max-w-xl text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">{p.title}</h2>
                        <p className="mt-4 max-w-lg leading-relaxed text-foreground/70">{p.body(quota)}</p>
                        <Link href={route(PRODUCT_ROUTE[product])} className="mt-6 inline-block text-sm font-medium text-primary underline-offset-4 hover:underline">
                            {p.more}
                        </Link>
                    </div>
                    <Visual className="mx-auto w-full max-w-[30rem] lg:mx-0" />
                </div>
                {product === 'signatures' && <SigningSteps />}
                <FeatureList items={p.features} />
            </Container>
        </section>
    );
}

const SAMPLES: Record<ProductKey, { label: string; code: string }> = {
    signatures: {
        label: 'Assinatura',
        code: `curl -X POST {base}/signatures/documents \\
  -H "Authorization: Bearer $MODUHUB_API_KEY" \\
  -F "file=@contrato.pdf" \\
  -F "send=1" \\
  -F "signers[0][name]=Rafael Tavares" \\
  -F "signers[0][email]=rafael@tavares.adv.br"`,
    },
    invoices: {
        label: 'NFS-e',
        code: `curl -X POST {base}/invoices \\
  -H "Authorization: Bearer $MODUHUB_API_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{
    "external_id": "PED-4821",
    "taker": { "document": "12345678000190", "name": "Tavares Advocacia",
               "email": "financeiro@tavares.adv.br" },
    "service": { "national_tax_code": "170101",
                 "description": "Consultoria em gestão empresarial" },
    "values": { "amount": 4800.00 },
    "notify_taker": true
  }'`,
    },
    mailing: {
        label: 'E-mail (SMTP)',
        code: `MAIL_MAILER=smtp
MAIL_HOST=smtp.moduhub.com.br
MAIL_PORT=587
MAIL_USERNAME=mdhb_smtp_xxxxxxxx
MAIL_PASSWORD=sua-senha-smtp
MAIL_FROM_ADDRESS=financeiro@suaempresa.com.br`,
    },
};

export function Developers({ baseUrl, initial = 'signatures' }: { baseUrl: string; initial?: ProductKey }) {
    const [tab, setTab] = useState<ProductKey>(initial);

    return (
        <section className="bg-[#1b2230] text-white">
            <Container className="grid items-center gap-12 py-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:py-24">
                <div>
                    <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">Direto do seu sistema</h2>
                    <p className="mt-4 max-w-md leading-relaxed text-white/70">
                        Uma API com a mesma chave para assinar, emitir e acompanhar. O seu CRM ou ERP fica sabendo de cada assinatura e de cada
                        nota autorizada por webhook, sem precisar ficar consultando.
                    </p>
                    <Button asChild variant="outline" className="mt-8 border-white/25 bg-transparent text-white hover:bg-white/10 hover:text-white">
                        <Link href={route('docs.api')}>Ler a documentação da API</Link>
                    </Button>
                </div>
                <div className="min-w-0 rounded-xl border border-white/10 bg-white/[0.04]">
                    <div role="tablist" aria-label="Exemplos de integração" className="flex gap-1 border-b border-white/10 px-2 pt-2">
                        {(Object.keys(SAMPLES) as ProductKey[]).map((key) => (
                            <button
                                key={key}
                                role="tab"
                                type="button"
                                aria-selected={tab === key}
                                onClick={() => setTab(key)}
                                className={cn(
                                    '-mb-px rounded-t-md border-b-2 px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-white/60',
                                    tab === key ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white',
                                )}
                            >
                                {SAMPLES[key].label}
                            </button>
                        ))}
                    </div>
                    <pre className="overflow-x-auto p-5 font-mono text-[0.8125rem] leading-relaxed text-white/85">
                        <code>{SAMPLES[tab].code.replaceAll('{base}', baseUrl)}</code>
                    </pre>
                </div>
            </Container>
        </section>
    );
}

export function Plans({ products, canRegister, title = 'Monte o seu plano' }: { products: CatalogProduct[]; canRegister: boolean; title?: string }) {
    return (
        <section id="planos" className="scroll-mt-16">
            <Container className="py-20 lg:py-24">
                <div className="max-w-2xl">
                    <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">{title}</h2>
                    <p className="mt-4 leading-relaxed text-foreground/70">
                        Escolha um plano em cada produto que quiser e pague tudo numa mensalidade só. Passou da cota, você paga só o que usou a
                        mais, sem trocar de plano.
                    </p>
                </div>

                {products.length === 0 ? (
                    <p className="mt-10 text-muted-foreground">Os planos estão sendo atualizados.</p>
                ) : (
                    <div className="mt-12">
                        <PlanBuilder products={products} canRegister={canRegister} />
                    </div>
                )}
            </Container>
        </section>
    );
}

/**
 * Perguntas frequentes com <details>: o texto inteiro vai no HTML (buscadores e IAs leem as
 * respostas fechadas) e abre sem JavaScript. As mesmas perguntas vão no JSON-LD FAQPage.
 */
export function FaqSection({ items, title = 'Perguntas frequentes' }: { items: FaqItem[]; title?: string }) {
    if (items.length === 0) return null;

    return (
        <section id="perguntas" className="scroll-mt-16 border-t bg-card">
            <Container className="grid gap-10 py-20 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] lg:gap-16 lg:py-24">
                <h2 className="text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">{title}</h2>
                <div className="divide-y border-y">
                    {items.map((item) => (
                        <details key={item.question} className="group py-5 [&_summary::-webkit-details-marker]:hidden">
                            <summary className="flex cursor-pointer list-none items-start justify-between gap-4 rounded-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">
                                <h3>{item.question}</h3>
                                <ChevronDown className="mt-0.5 size-5 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
                            </summary>
                            <p className="mt-3 max-w-2xl leading-relaxed text-foreground/70">{item.answer}</p>
                        </details>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function VerifyDocument() {
    const [code, setCode] = useState('');
    const normalized = code.replace(/[^a-z0-9]/gi, '').toUpperCase();

    const submit = (e: SubmitEvent) => {
        e.preventDefault();
        if (normalized.length === 12) router.visit(route('verify.show', normalized));
    };

    return (
        <section id="verificar" className="scroll-mt-16 border-t">
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

export function SiteFooter() {
    return (
        <footer className="border-t">
            <Container className="flex flex-col gap-6 py-10 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <AppLogo />
                <nav aria-label="Rodapé" className="flex flex-wrap gap-x-6 gap-y-2">
                    {navLinks().map((n) => (
                        <Link key={n.href} href={n.href} className="hover:text-foreground">
                            {n.label}
                        </Link>
                    ))}
                    <Link href={route('docs.api')} className="hover:text-foreground">
                        Documentação da API
                    </Link>
                    <a href={`${route('home')}#verificar`} className="hover:text-foreground">
                        Verificar documento
                    </a>
                </nav>
                <p>© {new Date().getFullYear()} Moduhub</p>
            </Container>
        </footer>
    );
}
