import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { formatDateTime } from '@/lib/utils';
import { CheckCircle2 } from 'lucide-react';

interface Props {
    signer: { name: string; signed_at: string | null };
    document: { name: string; status: string };
    tenant: PublicTenant | null;
    [key: string]: unknown;
}

export default function Thanks({ signer, document, tenant }: Props) {
    const firstName = signer.name.split(' ')[0];
    const completed = document.status === 'completed';

    return (
        <PublicLayout title="Documento assinado" tenant={tenant} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-signed-soft text-signed">
                    <CheckCircle2 className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Pronto, {firstName}. Sua assinatura foi registrada.</h1>
                <p className="mt-2 text-muted-foreground">
                    Você assinou <span className="font-medium text-foreground">{document.name}</span>{' '}
                    {signer.signed_at ? `em ${formatDateTime(signer.signed_at)}` : 'agora'}.
                </p>
                <p className="mt-3 text-muted-foreground">
                    {completed
                        ? 'Todas as pessoas já assinaram. O documento está finalizado.'
                        : 'Agora é a vez das outras pessoas assinarem. Você não precisa fazer mais nada.'}
                </p>
                <p className="mt-6 rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                    O PDF foi atualizado com o seu nome, a data e o IP na página de assinaturas. A trilha de auditoria
                    completa fica com {tenant?.name ?? 'o emissor'}.
                </p>
            </section>
        </PublicLayout>
    );
}
