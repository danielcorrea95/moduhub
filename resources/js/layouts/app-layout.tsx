import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /** Largura do conteúdo: `default` para listagens/forms, `wide` para telas com PDF/editor. */
    width?: 'default' | 'narrow' | 'wide';
}

const WIDTHS = {
    narrow: 'max-w-3xl',
    default: 'max-w-6xl',
    wide: 'max-w-[96rem]',
};

export default function AppLayout({
    children,
    breadcrumbs = [],
    width = 'default',
}: AppLayoutProps) {
    const { sidebarOpen } = usePage<SharedData>().props;
    useFlashToasts();

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>
            <AppSidebar />
            <SidebarInset className="min-w-0 overflow-hidden bg-card">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <main
                    className={cn(
                        'mx-auto flex w-full flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8',
                        WIDTHS[width],
                    )}
                >
                    {children}
                </main>
            </SidebarInset>
            <Toaster richColors position="top-right" closeButton />
        </SidebarProvider>
    );
}
