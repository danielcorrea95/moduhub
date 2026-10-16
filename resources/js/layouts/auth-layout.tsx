import AppLogo from '@/components/app-logo';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AuthLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
}) {
    useFlashToasts();

    return (
        <div className="flex min-h-svh flex-col items-center bg-background px-4 py-10 sm:justify-center">
            <div className="w-full max-w-sm">
                <Link href="/" className="mb-8 inline-flex">
                    <AppLogo />
                </Link>
                <div className="rounded-xl border bg-card p-6 shadow-[0_1px_2px_rgb(27_34_48/0.04)] sm:p-8">
                    <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    {description && (
                        <p className="mt-1.5 text-sm text-muted-foreground">{description}</p>
                    )}
                    <div className="mt-6">{children}</div>
                </div>
            </div>
            <Toaster richColors position="top-center" />
        </div>
    );
}
