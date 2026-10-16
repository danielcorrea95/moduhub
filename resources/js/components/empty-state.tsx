import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export default function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center px-6 py-14 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="mb-4 flex size-11 items-center justify-center rounded-lg bg-accent text-primary">
                    <Icon className="size-5" />
                </div>
            )}
            <h3 className="text-base font-medium">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
