import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) return null;

    return (
        <nav
            className="flex items-center justify-between gap-4 text-sm"
            aria-label="Paginação"
        >
            <p className="tabular-nums text-muted-foreground">
                {page.from}–{page.to} de {page.total}
            </p>
            <div className="flex items-center gap-1">
                <Button variant="outline" size="sm" asChild>
                    {page.prev_page_url ? (
                        <Link href={page.prev_page_url} preserveScroll preserveState>
                            <ChevronLeft /> Anterior
                        </Link>
                    ) : (
                        <span aria-disabled="true" className="pointer-events-none opacity-50">
                            <ChevronLeft /> Anterior
                        </span>
                    )}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    {page.next_page_url ? (
                        <Link href={page.next_page_url} preserveScroll preserveState>
                            Próxima <ChevronRight />
                        </Link>
                    ) : (
                        <span aria-disabled="true" className="pointer-events-none opacity-50">
                            Próxima <ChevronRight />
                        </span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
