import type { CatalogProduct, ProductKey } from '@/components/billing/types';
import {
    Container,
    Developers,
    FaqSection,
    FeatureList,
    Plans,
    PRODUCT_ROUTE,
    PRODUCTS,
    SigningSteps,
    SiteFooter,
    SiteHeader,
    type FaqItem,
    type MailingFreeQuota,
} from '@/components/site/sections';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

interface Props {
    product: ProductKey;
    headline: string;
    canRegister: boolean;
    products: CatalogProduct[];
    faq: FaqItem[];
    seoTitle: string;
    apiBaseUrl: string;
    mailingFreeQuota: MailingFreeQuota;
    [key: string]: unknown;
}

/**
 * Página de um produto, para quem chega pela busca ("assinatura eletrônica", "emissor de NFS-e",
 * "disparo de e-mail"). Mesmo conteúdo da seção da página inicial, com o termo de busca no h1, as
 * perguntas do produto e os planos dele primeiro.
 */
export default function ProductShow({ product, headline, canRegister, products, faq, seoTitle, apiBaseUrl, mailingFreeQuota }: Props) {
    const p = PRODUCTS[product];
    const Visual = p.visual;
    const others = (Object.keys(PRODUCTS) as ProductKey[]).filter((k) => k !== product);
    // Os planos deste produto primeiro; os outros continuam ali para quem quiser combinar.
    const ordered = [...products].sort((a, b) => (a.key === product ? -1 : b.key === product ? 1 : 0));

    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={seoTitle} />
            <SiteHeader canRegister={canRegister} />
            <main>
                <section className="overflow-hidden">
                    <Container className="grid items-center gap-14 pb-20 pt-14 md:pt-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,30rem)] lg:gap-16 lg:pb-24">
                        <div>
                            <nav aria-label="Você está em" className="text-sm text-muted-foreground">
                                <Link href={route('home')} className="hover:text-foreground">
                                    Moduhub
                                </Link>
                                <span aria-hidden="true"> / </span>
                                <span className="text-foreground">{p.label}</span>
                            </nav>
                            <h1 className="mt-4 max-w-[20ch] text-[2.25rem] font-semibold leading-[1.08] tracking-[-0.02em] sm:text-[2.75rem] lg:text-[3.1rem]">
                                {headline}
                            </h1>
                            <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-foreground/70">{p.body(mailingFreeQuota)}</p>
                            <div className="mt-9 flex flex-wrap items-center gap-3">
                                {canRegister && (
                                    <Button asChild size="lg" className="h-11 px-6 text-[0.9375rem]">
                                        <Link href={route('register')}>Testar grátis por 14 dias</Link>
                                    </Button>
                                )}
                                <Button asChild size="lg" variant="outline" className="h-11 bg-card px-6 text-[0.9375rem]">
                                    <a href="#planos">Ver planos e preços</a>
                                </Button>
                            </div>
                        </div>
                        <Visual className="mx-auto w-full max-w-[30rem] lg:mx-0" />
                    </Container>
                </section>

                <section className="border-y bg-card">
                    <Container className="flex flex-col gap-14 py-20 lg:py-24">
                        <h2 className="max-w-xl text-3xl font-semibold tracking-tight sm:text-[2.25rem] sm:leading-tight">{p.title}</h2>
                        {product === 'signatures' && <SigningSteps />}
                        <FeatureList items={p.features} />
                    </Container>
                </section>

                <Developers baseUrl={apiBaseUrl} initial={product} />
                <Plans products={ordered} canRegister={canRegister} title="Planos e preços" />
                <FaqSection items={faq} title="Perguntas frequentes" />

                <section className="border-t">
                    <Container className="py-16">
                        <h2 className="text-xl font-semibold">Também no Moduhub</h2>
                        <ul className="mt-6 grid gap-6 sm:grid-cols-2">
                            {others.map((key) => {
                                const o = PRODUCTS[key];
                                return (
                                    <li key={key}>
                                        <Link href={route(PRODUCT_ROUTE[key])} className="group flex gap-3">
                                            <o.icon className="mt-0.5 size-5 shrink-0 text-primary" />
                                            <span>
                                                <span className="block font-semibold group-hover:text-primary">{o.label}</span>
                                                <span className="mt-1 block text-sm leading-relaxed text-foreground/70">{o.title}</span>
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </Container>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
