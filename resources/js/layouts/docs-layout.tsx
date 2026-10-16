import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';

export interface DocsNavGroup {
    title: string;
    items: { id: string; title: string }[];
}

/** Marca no menu a seção que está no topo da leitura. */
function useActiveSection(ids: string[]) {
    const [active, setActive] = useState(ids[0]);

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible[0]) setActive(visible[0].target.id);
            },
            { rootMargin: '-15% 0px -70% 0px' },
        );
        ids.forEach((id) => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });
        return () => observer.disconnect();
    }, [ids]);

    return active;
}

export default function DocsLayout({
    title,
    nav,
    children,
}: {
    title: string;
    nav: DocsNavGroup[];
    children: ReactNode;
}) {
    const { auth } = usePage<SharedData>().props;
    const ids = nav.flatMap((g) => g.items.map((i) => i.id));
    const active = useActiveSection(ids);

    return (
        <div className="min-h-svh bg-sidebar">
            <Head title={title} />

            <div className="flex">
                <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col px-4 py-5 lg:flex">
                    <Link href="/" className="flex items-center gap-2 px-2">
                        <AppLogo />
                        <span className="rounded-full bg-card px-2 py-0.5 text-xs text-muted-foreground">API</span>
                    </Link>

                    <nav aria-label="Seções da documentação" className="mt-6 flex-1 space-y-5 overflow-y-auto pr-1">
                        {nav.map((group) => (
                            <div key={group.title}>
                                <p className="px-2 text-xs text-muted-foreground">{group.title}</p>
                                <ul className="mt-1.5 space-y-0.5">
                                    {group.items.map((item) => (
                                        <li key={item.id}>
                                            <a
                                                href={`#${item.id}`}
                                                aria-current={active === item.id ? 'location' : undefined}
                                                className={cn(
                                                    'block rounded-lg px-2 py-1.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                                                    active === item.id
                                                        ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
                                                        : 'text-sidebar-foreground hover:bg-sidebar-accent',
                                                )}
                                            >
                                                {item.title}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </nav>

                    <Button asChild variant="outline" className="mt-4 bg-card">
                        <Link href={auth.user ? route('api-keys.index') : route('login')}>
                            {auth.user ? 'Gerenciar chaves de API' : 'Entrar no painel'}
                        </Link>
                    </Button>
                </aside>

                <div className="min-w-0 flex-1 lg:py-2 lg:pr-2">
                    <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-card px-4 py-3 lg:hidden">
                        <Link href="/" className="flex items-center gap-2">
                            <AppLogo />
                            <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-muted-foreground">API</span>
                        </Link>
                        <select
                            aria-label="Ir para a seção"
                            value={active}
                            onChange={(e) => {
                                window.location.hash = e.target.value;
                            }}
                            className="h-9 max-w-[55%] rounded-lg border bg-card px-2 text-sm"
                        >
                            {nav.map((g) => (
                                <optgroup key={g.title} label={g.title}>
                                    {g.items.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.title}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                        </select>
                    </header>

                    <main className="min-h-[calc(100svh-1rem)] bg-card lg:rounded-xl lg:shadow-sm">{children}</main>
                </div>
            </div>
        </div>
    );
}
