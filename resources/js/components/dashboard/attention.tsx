import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { CircleAlert, TriangleAlert } from 'lucide-react';
import type { DashboardAlert } from './types';

/** Tudo o que trava ou ameaça o trabalho do cliente, de todos os módulos, no topo da página. */
export function Attention({ alerts }: { alerts: DashboardAlert[] }) {
    if (alerts.length === 0) return null;

    return (
        <section aria-labelledby="attention-title" className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <h2 id="attention-title" className="sr-only">
                Precisa de atenção
            </h2>
            <ul className="divide-y">
                {alerts.map((a) => {
                    const Icon = a.tone === 'danger' ? CircleAlert : TriangleAlert;
                    return (
                        <li
                            key={a.title}
                            className={cn(
                                'flex flex-col gap-3 border-l-4 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-4',
                                a.tone === 'danger' ? 'border-l-rejected' : 'border-l-pending',
                            )}
                        >
                            <Icon className={cn('hidden size-5 shrink-0 sm:block', a.tone === 'danger' ? 'text-rejected' : 'text-pending')} aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{a.title}</p>
                                {a.description && <p className="text-sm text-muted-foreground">{a.description}</p>}
                            </div>
                            {a.href && (
                                <Button asChild variant="outline" size="sm" className="shrink-0 self-start sm:self-center">
                                    <Link href={a.href}>{a.action ?? 'Resolver'}</Link>
                                </Button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
