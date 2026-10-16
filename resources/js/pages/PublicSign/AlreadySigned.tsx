import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { formatDateTime } from '@/lib/utils';
import { CheckCircle2 } from 'lucide-react';

interface Props {
    signer: { signed_at: string | null };
    document: { name: string };
    tenant: PublicTenant | null;
    [key: string]: unknown;
}

export default function AlreadySigned({ signer, document, tenant }: Props) {
    return (
        <PublicLayout title="Documento já assinado" tenant={tenant} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-signed-soft text-signed">
                    <CheckCircle2 className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Você já assinou este documento</h1>
                <p className="mt-2 text-muted-foreground">
                    Sua assinatura em <span className="font-medium text-foreground">{document.name}</span> foi
                    registrada em {formatDateTime(signer.signed_at)}.
                </p>
                <p className="mt-3 text-muted-foreground">Não é preciso fazer mais nada.</p>
            </section>
        </PublicLayout>
    );
}
