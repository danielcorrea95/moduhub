import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { formatDateTime } from '@/lib/utils';
import { CircleX } from 'lucide-react';

interface Props {
    signer: { rejected_at: string | null; rejection_reason: string | null };
    document: { name: string };
    tenant: PublicTenant | null;
    [key: string]: unknown;
}

export default function Rejected({ signer, document, tenant }: Props) {
    // O motivo só existe quando foi esta pessoa quem recusou; se outra pessoa recusou, ele vem vazio.
    const rejectedBySigner = Boolean(signer.rejection_reason);

    return (
        <PublicLayout title="Documento recusado" tenant={tenant} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-rejected-soft text-rejected">
                    <CircleX className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Este documento foi recusado</h1>
                <p className="mt-2 text-muted-foreground">
                    {rejectedBySigner ? 'Você recusou' : 'Alguém recusou'}{' '}
                    <span className="font-medium text-foreground">{document.name}</span>
                    {rejectedBySigner && signer.rejected_at ? ` em ${formatDateTime(signer.rejected_at)}` : ''}. Por
                    isso, ele não pode mais ser assinado.
                </p>
                {signer.rejection_reason && (
                    <div className="mt-5 rounded-lg bg-rejected-soft px-4 py-3">
                        <p className="text-sm font-medium text-rejected">Motivo informado</p>
                        <p className="mt-1 whitespace-pre-line text-sm text-foreground/85">{signer.rejection_reason}</p>
                    </div>
                )}
                <p className="mt-5 text-muted-foreground">
                    Se precisar de uma nova versão, fale com {tenant?.name ?? 'quem enviou o documento'}.
                </p>
            </section>
        </PublicLayout>
    );
}
