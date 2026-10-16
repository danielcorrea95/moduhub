import { cn } from '@/lib/utils';
import type { SVGProps } from 'react';

export function AppLogoIcon({ className, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className={cn('size-6', className)}
            {...props}
        >
            <rect x="2" y="2" width="9" height="9" rx="2" stroke="currentColor" strokeWidth="2" />
            <rect x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
            <rect x="2" y="13" width="9" height="9" rx="2" stroke="currentColor" strokeWidth="2" />
            <rect x="13" y="13" width="9" height="9" rx="2" stroke="currentColor" strokeWidth="2" />
        </svg>
    );
}

export default function AppLogo({ className }: { className?: string }) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            <AppLogoIcon className="size-6 shrink-0 text-primary" />
            <span className="truncate text-[1.05rem] font-semibold tracking-tight text-foreground">
                Moduhub
            </span>
        </span>
    );
}
