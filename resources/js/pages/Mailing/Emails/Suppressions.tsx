import { ConfirmAction } from '@/components/confirm-action';
import EmptyState from '@/components/empty-state';
import { EmailsShell } from '@/components/mailing/emails-shell';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Lock, Search, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SuppressionRow {
    id: string;
    email: string;
    reason: 'bounce' | 'complaint';
    detail: string | null;
    removable: boolean;
    delivery_id: string | null;
    updated_at: string | null;
}

interface Props {
    search: string | null;
    suppressed: number;
    suppressions: Paginated<SuppressionRow>;
    [key: string]: unknown;
}

const REASONS: Record<SuppressionRow['reason'], { label: string; explain: string }> = {
    bounce: { label: 'Devolveu', explain: 'O servidor do destinatário disse que o endereço não existe ou não aceita mais e-mails.' },
    complaint: { label: 'Marcou como spam', explain: 'A pessoa clicou em "é spam". Insistir prejudica a entrega de todos os seus e-mails.' },
};

export default function EmailSuppressions({ search: initialSearch, suppressed, suppressions }: Props) {
    const [search, setSearch] = useState(initialSearch ?? '');
    const firstRender = useRef(true);
    const { errors } = usePage().props as { errors: Record<string, string> };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(
            () => router.get(route('emails.suppressions'), search.trim() ? { busca: search.trim() } : {}, { preserveState: true, replace: true }),
            300,
        );
        return () => clearTimeout(t);
    }, [search]);

    return (
        <EmailsShell section="suppressions" suppressed={suppressed}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-prose text-sm text-muted-foreground">
                    Endereços que devolveram de vez ou marcaram seus e-mails como spam. Novos envios para eles são descartados para proteger a
                    reputação do seu domínio.
                </p>
                <div className="relative shrink-0 sm:w-72">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar endereço"
                        aria-label="Buscar endereço bloqueado"
                        className="h-10 bg-card pl-9"
                    />
                </div>
            </div>

            {errors.suppression && (
                <p className="rounded-lg bg-rejected-soft px-4 py-3 text-sm text-rejected" role="alert">
                    {errors.suppression}
                </p>
            )}

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {suppressions.data.length === 0 ? (
                    <EmptyState
                        icon={search ? Search : ShieldCheck}
                        title={search ? 'Nenhum endereço encontrado' : 'Nenhum endereço bloqueado'}
                        description={search ? `Nada corresponde a “${search}”.` : 'Quando um e-mail devolver de vez ou for marcado como spam, o endereço aparece aqui.'}
                    />
                ) : (
                    <ul className="divide-y">
                        {suppressions.data.map((s) => (
                            <li key={s.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:gap-6 md:px-6">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{s.email}</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        <span className="font-medium text-rejected">{REASONS[s.reason].label}</span> em {formatDate(s.updated_at)}.{' '}
                                        {REASONS[s.reason].explain}
                                    </p>
                                    {s.detail && <p className="mt-1 truncate font-mono text-xs text-muted-foreground" title={s.detail}>{s.detail}</p>}
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    {s.delivery_id && (
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={route('emails.messages', { mensagem: s.delivery_id, periodo: 90 })}>Ver e-mail</Link>
                                        </Button>
                                    )}
                                    {s.removable ? (
                                        <ConfirmAction
                                            trigger={<Button variant="outline" size="sm">Liberar</Button>}
                                            title={`Liberar ${s.email}?`}
                                            description="Faça isso só se o endereço foi corrigido ou a caixa voltou a existir. Se devolver de novo, ele volta para a lista."
                                            confirmLabel="Liberar endereço"
                                            onConfirm={() => router.delete(route('emails.suppressions.release', s.id), { preserveScroll: true })}
                                        />
                                    ) : (
                                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground" title="Só sai da lista se a pessoa pedir">
                                            <Lock className="size-3.5" aria-hidden="true" /> Permanente
                                        </span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Pagination page={suppressions} />
        </EmailsShell>
    );
}
