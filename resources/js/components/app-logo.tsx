import { cn } from '@/lib/utils';
import type { SVGProps } from 'react';

/**
 * Ligadura "mh": a haste alta com o primeiro arco forma o h; com o segundo arco, o m.
 * O segundo arco usa opacidade em vez de cor fixa para funcionar sobre qualquer fundo.
 */
export function AppLogoIcon({ className, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 48 48"
            fill="none"
            stroke="currentColor"
            strokeWidth="7"
            strokeLinecap="round"
            aria-hidden="true"
            className={cn('size-6', className)}
            {...props}
        >
            <path d="M24 23C24 18.5 27.5 16 32 16C36.5 16 40 18.5 40 23V43" strokeOpacity="0.38" />
            <path d="M8 5V43M8 23C8 18.5 11.5 16 16 16C20.5 16 24 18.5 24 23V43" />
        </svg>
    );
}

export default function AppLogo({ className }: { className?: string }) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            <AppLogoIcon className="size-6 shrink-0 text-primary" />
            {/* O peso do ícone fica nos arcos de baixo; descer o texto compensa o centro óptico. */}
            <span className="translate-y-[0.1em] truncate text-[1.05rem] font-semibold tracking-tight text-foreground">
                moduhub
            </span>
        </span>
    );
}
