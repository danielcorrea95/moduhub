import { SignerNames, SignerProgress, type SignerSummary } from '@/components/signer-progress';
import { StatusBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

const CLAUSES = [
    'Cláusula 1. O presente contrato tem por objeto a prestação de serviços de consultoria descritos no Anexo I.',
    'Cláusula 2. O prazo de vigência é de doze meses a contar da data da última assinatura.',
    'Cláusula 3. As partes reconhecem a validade das assinaturas eletrônicas apostas neste instrumento.',
];

/**
 * O momento central do site: a assinatura sendo escrita em tinta no contrato e a barra de
 * signatários se completando. Roda uma vez; com movimento reduzido, mostra direto o estado final.
 */
export function SigningContract({ className }: { className?: string }) {
    const [signed, setSigned] = useState(false);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setSigned(true);
            return;
        }
        const t = setTimeout(() => setSigned(true), 2300);
        return () => clearTimeout(t);
    }, []);

    const signers: SignerSummary[] = [
        { id: 1, name: 'Marina Albuquerque', status: 'signed' },
        { id: 2, name: 'Rafael Tavares', status: signed ? 'signed' : 'viewed' },
    ];

    return (
        <div className={cn('relative', className)}>
            <article
                aria-label="Exemplo de contrato sendo assinado"
                className="relative rounded-xl border bg-card px-7 pb-8 pt-7 shadow-[0_1px_2px_rgb(27_34_48/0.05),0_24px_48px_-24px_rgb(27_34_48/0.25)] sm:px-9"
            >
                <header className="flex items-start justify-between gap-4 border-b pb-4">
                    <div>
                        <p className="text-xs text-muted-foreground">Contrato nº 2026-10-0019</p>
                        <h3 className="mt-0.5 text-base font-semibold">Prestação de serviços de consultoria</h3>
                    </div>
                    <StatusBadge status={signed ? 'completed' : 'partially_signed'} className="transition-colors duration-500" />
                </header>

                <div className="mt-4 space-y-2.5 text-[0.8125rem] leading-relaxed text-foreground/60" aria-hidden="true">
                    {CLAUSES.map((c) => (
                        <p key={c}>{c}</p>
                    ))}
                </div>

                <div className="mt-8 grid grid-cols-2 gap-6">
                    <SignatureLine name="Marina Albuquerque" role="Contratante" />
                    <SignatureLine name="Rafael Tavares" role="Contratado" animate />
                </div>
            </article>

            <aside className="relative -mt-6 ml-auto mr-4 w-[min(20rem,90%)] rounded-xl border bg-card p-4 shadow-[0_12px_32px_-16px_rgb(27_34_48/0.35)] sm:-mr-6">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-medium">Signatários</p>
                    <SignerProgress signers={signers} />
                </div>
                <SignerNames signers={signers} className="mt-2.5" />
                <p className="mt-3 border-t pt-3 text-xs text-muted-foreground" aria-live="polite">
                    {signed ? 'Todos assinaram. PDF final gerado com trilha de auditoria.' : 'Rafael abriu o documento e está assinando.'}
                </p>
            </aside>
        </div>
    );
}

function SignatureLine({ name, role, animate = false }: { name: string; role: string; animate?: boolean }) {
    return (
        <div>
            <div className="relative flex h-12 items-end">
                <span
                    className={cn('block whitespace-nowrap pb-1 text-[1.65rem] leading-none text-primary', animate && 'signature-ink')}
                    style={{ fontFamily: "'Great Vibes', cursive" }}
                    aria-hidden="true"
                >
                    {name.split(' ')[0]} {name.split(' ')[1]?.charAt(0)}.
                </span>
            </div>
            <div className="border-t border-foreground/25 pt-1.5">
                <p className="text-xs font-medium text-foreground">{name}</p>
                <p className="text-xs text-muted-foreground">{role}</p>
            </div>
        </div>
    );
}
