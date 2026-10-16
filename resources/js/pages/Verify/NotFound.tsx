import PublicLayout from '@/layouts/public-layout';
import { SearchX } from 'lucide-react';

interface Props {
    short: string;
    [key: string]: unknown;
}

export default function VerifyNotFound({ short }: Props) {
    return (
        <PublicLayout title="Documento não encontrado" width="narrow">
            <section className="rounded-xl border bg-card p-6 sm:p-8">
                <div className="mb-5 flex size-11 items-center justify-center rounded-full bg-idle-soft text-idle">
                    <SearchX className="size-6" aria-hidden="true" />
                </div>
                <h1 className="text-xl font-semibold tracking-tight">Nenhum documento com este código</h1>
                <p className="mt-2 text-muted-foreground">
                    Não encontramos um documento com o identificador{' '}
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm break-all text-foreground">
                        {short}
                    </code>
                    .
                </p>
                <ul className="mt-4 list-disc space-y-1.5 pl-5 text-muted-foreground">
                    <li>Escaneie o QR code da versão mais recente do PDF.</li>
                    <li>Se digitou o endereço, confira se o link está completo. O identificador tem 12 letras e números.</li>
                </ul>
            </section>
        </PublicLayout>
    );
}
