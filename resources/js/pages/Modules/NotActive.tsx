import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { ModuleKey } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, PackageX } from 'lucide-react';

interface Props {
    module: ModuleKey | string;
    [key: string]: unknown;
}

const MODULE_LABEL: Record<string, string> = {
    signatures: 'Assinatura de documentos',
    invoices: 'Notas fiscais',
};

export default function NotActive({ module }: Props) {
    const label = MODULE_LABEL[module] ?? module;

    return (
        <AppLayout breadcrumbs={[{ title: label }]} width="narrow">
            <Head title="Módulo não contratado" />

            <section className="rounded-xl border bg-card shadow-xs">
                <EmptyState
                    icon={PackageX}
                    title={`${label} não está ativo neste workspace`}
                    description="Para liberar o acesso, fale com quem administra este workspace ou com a equipe do Moduhub."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={route('dashboard')}>
                                <ArrowLeft aria-hidden="true" />
                                Voltar ao início
                            </Link>
                        </Button>
                    }
                />
            </section>
        </AppLayout>
    );
}
