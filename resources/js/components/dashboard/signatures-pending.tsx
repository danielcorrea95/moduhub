import EmptyState from '@/components/empty-state';
import { SignerNames, SignerProgress } from '@/components/signer-progress';
import { Button } from '@/components/ui/button';
import { cn, formatDate } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { FileSignature } from 'lucide-react';
import type { SignaturesSection } from './types';

/** O que ainda depende de alguém assinar, com o placar do mês em uma linha acima. */
export function SignaturesPending({ signatures }: { signatures: SignaturesSection }) {
    const counts = signatures.statusCounts;
    const inProgress = (counts.sent ?? 0) + (counts.partially_signed ?? 0);
    const rejected = counts.rejected ?? 0;
    const hasAny = Object.values(counts).some((n) => (n ?? 0) > 0);

    const tallies = [
        { label: 'em andamento', value: inProgress, status: 'in_progress', dot: 'bg-pending' },
        { label: signatures.month.completed === 1 ? 'concluído no mês' : 'concluídos no mês', value: signatures.month.completed, status: 'completed', dot: 'bg-signed' },
        { label: rejected === 1 ? 'recusado' : 'recusados', value: rejected, status: 'rejected', dot: 'bg-rejected' },
    ];

    return (
        <section aria-labelledby="signatures-title" className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <header className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 pt-5 pb-4 md:px-7">
                <div className="min-w-0">
                    <h2 id="signatures-title" className="text-base font-medium">
                        Esperando assinatura
                    </h2>
                    <ul className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        {tallies.map((t) => (
                            <li key={t.status}>
                                <Link
                                    href={route('signatures.index', { status: t.status })}
                                    className="flex items-center gap-1.5 text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2"
                                >
                                    <span aria-hidden="true" className={cn('size-1.5 rounded-full', t.dot)} />
                                    <span className="font-medium text-foreground tabular-nums">{t.value}</span> {t.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </header>

            {signatures.inProgress.length === 0 ? (
                <EmptyState
                    className="border-t py-10"
                    icon={FileSignature}
                    title={hasAny ? 'Ninguém está devendo assinatura' : 'Envie seu primeiro documento'}
                    description={
                        hasAny
                            ? 'Todos os documentos enviados já foram concluídos ou encerrados.'
                            : 'Suba um PDF, diga quem precisa assinar e acompanhe cada assinatura por aqui.'
                    }
                />
            ) : (
                <>
                    <ul className="divide-y border-t">
                        {signatures.inProgress.map((d) => (
                            <li key={d.id} className="group relative">
                                <div className="flex flex-col gap-2.5 px-5 py-3.5 transition-colors group-hover:bg-muted/40 sm:flex-row sm:items-center sm:gap-6 md:px-7">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('signatures.show', d.id)}
                                            className="block truncate font-medium after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-ring"
                                        >
                                            {d.name}
                                        </Link>
                                        <SignerNames signers={d.signers} className="mt-1.5" />
                                    </div>
                                    <div className="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-1.5">
                                        <SignerProgress signers={d.signers} />
                                        <span className="text-xs text-muted-foreground tabular-nums">enviado em {formatDate(d.sent_at ?? d.created_at)}</span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                    {inProgress > signatures.inProgress.length && (
                        <div className="border-t px-5 py-2.5 md:px-7">
                            <Button asChild variant="ghost" size="sm" className="-ml-3">
                                <Link href={route('signatures.index', { status: 'in_progress' })}>Ver os {inProgress} em andamento</Link>
                            </Button>
                        </div>
                    )}
                </>
            )}
        </section>
    );
}
