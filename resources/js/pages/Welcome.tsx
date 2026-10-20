import type { CatalogProduct } from '@/components/billing/types';
import { ClientTrail } from '@/components/site/client-trail';
import {
    Container,
    Developers,
    FaqSection,
    Plans,
    PRODUCT_ROUTE,
    PRODUCTS,
    ProductSection,
    SiteFooter,
    SiteHeader,
    VerifyDocument,
    type FaqItem,
    type MailingFreeQuota,
} from '@/components/site/sections';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

interface Props {
    canRegister: boolean;
    products: CatalogProduct[];
    faq: FaqItem[];
    seoTitle: string;
    apiBaseUrl: string;
    mailingFreeQuota: MailingFreeQuota;
    [key: string]: unknown;
}

function Hero({ canRegister }: { canRegister: boolean }) {
    return (
        <section className="overflow-hidden">
            <Container className="grid items-center gap-14 pb-20 pt-14 md:pt-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,29rem)] lg:gap-16 lg:pb-28">
                <div>
                    <h1 className="max-w-[17ch] text-[2.5rem] font-semibold leading-[1.05] tracking-[-0.025em] text-foreground sm:text-[3.25rem] lg:text-[3.6rem]">
                        Assine o contrato, emita a nota e avise o cliente.
                    </h1>
                    <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-foreground/70">
                        O Moduhub junta as rotinas que se repetem a cada cliente novo: assinatura eletrônica de contratos, emissão de NFS-e pelo
                        padrão nacional e disparo de e-mails com o domínio da sua empresa. Você contrata só o que usar.
                    </p>
                    <div className="mt-9 flex flex-wrap items-center gap-3">
                        {canRegister && (
                            <Button asChild size="lg" className="h-11 px-6 text-[0.9375rem]">
                                <Link href={route('register')}>Testar grátis</Link>
                            </Button>
                        )}
                        <Button asChild size="lg" variant="outline" className="h-11 bg-card px-6 text-[0.9375rem]">
                            <a href="#planos">Montar meu plano</a>
                        </Button>
                    </div>
                    <nav aria-label="Produtos" className="mt-10 flex flex-wrap gap-x-6 gap-y-3 border-t pt-6">
                        {(Object.keys(PRODUCTS) as (keyof typeof PRODUCTS)[]).map((key) => {
                            const p = PRODUCTS[key];
                            return (
                                <Link key={key} href={route(PRODUCT_ROUTE[key])} className="flex items-center gap-2 text-sm font-medium text-foreground/80 transition-colors hover:text-primary">
                                    <p.icon className="size-4 text-primary" />
                                    {p.label}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                <ClientTrail className="mx-auto w-full max-w-[29rem] lg:mx-0" />
            </Container>
        </section>
    );
}

export default function Welcome({ canRegister, products, faq, seoTitle, apiBaseUrl, mailingFreeQuota }: Props) {
    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={seoTitle} />
            <SiteHeader canRegister={canRegister} />
            <main>
                <Hero canRegister={canRegister} />
                <ProductSection product="signatures" quota={mailingFreeQuota} tinted />
                <ProductSection product="invoices" quota={mailingFreeQuota} flip />
                <ProductSection product="mailing" quota={mailingFreeQuota} tinted />
                <Developers baseUrl={apiBaseUrl} />
                <Plans products={products} canRegister={canRegister} />
                <FaqSection items={faq} />
                <VerifyDocument />
            </main>
            <SiteFooter />
        </div>
    );
}
