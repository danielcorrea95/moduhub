import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { formatDateTime } from '@/lib/utils';
import { CalendarX2 } from 'lucide-react';

interface Props {
    document: { name: string; expires_at: string | null };
    tenant: PublicTenant | null;
    [key: string]: unknown;
}

export default function Expired({ document, tenant }: Props) {
    return (
        <PublicLayout title="Prazo encerrado" tenant={tenant} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-idle-soft text-idle">
                    <CalendarX2 className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">O prazo para assinar terminou</h1>
                <p className="mt-2 text-muted-foreground">
                    O prazo de <span className="font-medium text-foreground">{document.name}</span> terminou em{' '}
                    {formatDateTime(document.expires_at)}. Por isso, este link não aceita mais assinaturas.
                </p>
                <p className="mt-3 text-muted-foreground">
                    Se você ainda precisa assinar, peça a {tenant?.name ?? 'quem enviou o documento'} um novo envio.
                </p>
            </section>
        </PublicLayout>
    );
}
