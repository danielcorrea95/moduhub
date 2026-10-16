import PublicLayout from '@/layouts/public-layout';
import { Link2Off } from 'lucide-react';

export default function Invalid() {
    return (
        <PublicLayout title="Link inválido" tenant={null} width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-idle-soft text-idle">
                    <Link2Off className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Este link não está mais ativo</h1>
                <p className="mt-2 text-muted-foreground">
                    Não encontramos um documento disponível para assinatura neste endereço. O link pode estar
                    incompleto, ou o documento foi cancelado.
                </p>
                <ul className="mt-4 list-disc space-y-1.5 pl-5 text-muted-foreground">
                    <li>Abra de novo o link direto do e-mail que você recebeu.</li>
                    <li>Se continuar assim, peça a quem enviou o documento um novo link.</li>
                </ul>
            </section>
        </PublicLayout>
    );
}
