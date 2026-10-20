import { AccountReceipt } from '@/components/dashboard/account-receipt';
import { Attention } from '@/components/dashboard/attention';
import { EmailHealth } from '@/components/dashboard/email-health';
import { InvoicePace } from '@/components/dashboard/invoice-pace';
import { SignaturesPending } from '@/components/dashboard/signatures-pending';
import type { Billing, DashboardAlert, DashboardSections } from '@/components/dashboard/types';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { ModuleKey, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid, Plus } from 'lucide-react';

interface Props {
    // Sem nenhuma seção o Laravel manda [] em vez de {}.
    sections: DashboardSections | [];
    alerts: DashboardAlert[];
    billing: Billing | null;
    [key: string]: unknown;
}

const MODULE_NAMES: Record<ModuleKey, string> = {
    signatures: 'assinatura de documentos',
    invoices: 'notas fiscais',
    mailing: 'disparo de e-mails',
};

const today = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });

export default function Dashboard({ sections: rawSections, alerts, billing }: Props) {
    const { auth, currentTenant } = usePage<SharedData>().props;
    const firstName = auth.user?.name.trim().split(/\s+/)[0];

    if (!currentTenant) {
        return (
            <AppLayout breadcrumbs={[{ title: 'Início' }]} width="narrow">
                <Head title="Início" />
                <section className="rounded-xl border bg-card shadow-xs">
                    <EmptyState
                        icon={Building2}
                        title={firstName ? `Boas-vindas, ${firstName}` : 'Boas-vindas ao Moduhub'}
                        description="Para começar, crie um workspace para a sua empresa. É nele que ficam seus documentos, sua equipe e suas integrações."
                        action={
                            <Button asChild>
                                <Link href={route('workspaces.index')}>
                                    <Plus aria-hidden="true" /> Criar workspace
                                </Link>
                            </Button>
                        }
                    />
                </section>
            </AppLayout>
        );
    }

    const sections: DashboardSections = Array.isArray(rawSections) ? {} : rawSections;
    const { signatures, invoices, mailing } = sections;
    const hasMain = Boolean(invoices || signatures);
    const hasSide = Boolean(billing || mailing);
    const missing = (['invoices', 'signatures'] as const).filter((key) => !currentTenant.modules.includes(key));

    return (
        <AppLayout breadcrumbs={[{ title: 'Início' }]}>
            <Head title="Início" />

            <PageHeader
                title={firstName ? `Olá, ${firstName}` : 'Início'}
                description={<span className="first-letter:uppercase">{`${today.format(new Date())} em ${currentTenant.name}`}</span>}
            />

            <Attention alerts={alerts} />

            {!hasMain && !hasSide ? (
                <section className="rounded-xl border bg-card shadow-xs">
                    <EmptyState
                        icon={LayoutGrid}
                        title="Nenhum módulo ativo neste workspace"
                        description="Quando assinaturas, notas fiscais ou e-mails estiverem ativos, o resumo de cada um aparece aqui."
                    />
                </section>
            ) : (
                <div className={cn('grid items-start gap-6', hasMain && hasSide && 'lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem]')}>
                    {hasMain && (
                        <div className="flex min-w-0 flex-col gap-6">
                            {invoices && <InvoicePace invoices={invoices} />}
                            {signatures && <SignaturesPending signatures={signatures} />}
                        </div>
                    )}
                    {hasSide && (
                        <div className={cn('flex flex-col gap-6', !hasMain && 'max-w-md')}>
                            {billing && <AccountReceipt billing={billing} />}
                            {mailing && <EmailHealth mailing={mailing} />}
                        </div>
                    )}
                </div>
            )}

            {missing.length > 0 && (
                <p className="text-sm text-muted-foreground">
                    Este workspace ainda não usa {missing.map((key) => MODULE_NAMES[key]).join(' nem ')}. Para ativar, fale com o suporte da Moduhub.
                </p>
            )}
        </AppLayout>
    );
}
