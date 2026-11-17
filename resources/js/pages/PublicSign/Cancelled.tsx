import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { Ban } from 'lucide-react';

interface Props {
    document: { name: string };
    tenant: PublicTenant | null;
    [key: string]: unknown;
}

export default function Cancelled({ document, tenant }: Props) {
    return (
        <PublicLayout title="Documento cancelado" tenant={tenant} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-idle-soft text-idle">
                    <Ban className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Este documento foi cancelado</h1>
                <p className="mt-2 text-muted-foreground">
                    {tenant?.name ?? 'Quem enviou'} cancelou <span className="font-medium text-foreground">{document.name}</span> antes de
                    todos assinarem. Por isso, este link não aceita mais assinaturas.
                </p>
                <p className="mt-3 text-muted-foreground">Se tiver dúvidas, fale com {tenant?.name ?? 'quem enviou o documento'}.</p>
            </section>
        </PublicLayout>
    );
}
