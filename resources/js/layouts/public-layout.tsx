import AppLogo from '@/components/app-logo';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { cn } from '@/lib/utils';
import type { Flash, TenantBranding } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export interface PublicTenant {
    name?: string | null;
    branding?: TenantBranding | null;
}

/**
 * Layout das páginas abertas a quem não tem conta (assinatura pública, verificação, convites).
 * Quando há tenant, a marca dele lidera; o Moduhub aparece só como "processado por".
 */
export default function PublicLayout({
    title,
    tenant,
    flash,
    width = 'default',
    footer,
    children,
}: {
    title: string;
    tenant?: PublicTenant | null;
    flash?: Flash;
    width?: 'narrow' | 'default';
    footer?: ReactNode;
    children: ReactNode;
}) {
    useFlashToasts(flash);
    const primary = tenant?.branding?.primary_color ?? null;
    const container = width === 'narrow' ? 'max-w-xl' : 'max-w-5xl';

    return (
        <div className="min-h-svh bg-background">
            <Head title={title} />
            <header
                className="border-b bg-card"
                style={primary ? { boxShadow: `inset 0 3px 0 ${primary}` } : undefined}
            >
                <div className={cn('mx-auto flex items-center justify-between gap-4 px-4 py-4 sm:px-6', container)}>
                    {tenant?.name ? (
                        <div className="flex min-w-0 items-center gap-3">
                            {tenant.branding?.logo_url ? (
                                <img
                                    src={tenant.branding.logo_url}
                                    alt={tenant.name}
                                    className="h-9 w-auto"
                                />
                            ) : (
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-md font-semibold text-white"
                                    style={{ backgroundColor: primary ?? 'var(--primary)' }}
                                    aria-hidden="true"
                                >
                                    {tenant.name.charAt(0).toUpperCase()}
                                </span>
                            )}
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Enviado por</p>
                                <p className="truncate font-semibold">{tenant.name}</p>
                            </div>
                        </div>
                    ) : (
                        <AppLogo />
                    )}
                    {tenant?.name && (
                        <p className="hidden shrink-0 text-xs text-muted-foreground sm:block">
                            Processado por Moduhub
                        </p>
                    )}
                </div>
            </header>
            <main className={cn('mx-auto px-4 py-8 sm:px-6', container)}>{children}</main>
            {footer && (
                <footer className={cn('mx-auto px-4 pb-10 pt-2 text-center text-xs text-muted-foreground sm:px-6', container)}>
                    {footer}
                </footer>
            )}
            <Toaster richColors position="top-center" />
        </div>
    );
}
