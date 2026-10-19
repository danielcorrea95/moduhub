import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

export function Field({
    id,
    label,
    optional,
    hint,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    optional?: boolean;
    hint?: ReactNode;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid content-start gap-2', className)}>
            <Label htmlFor={id}>
                {label} {optional && <span className="font-normal text-muted-foreground">(opcional)</span>}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

const regionNames = typeof Intl !== 'undefined' && 'DisplayNames' in Intl ? new Intl.DisplayNames(['pt-BR'], { type: 'region' }) : null;

/** Nome do país pelo código ISO de 2 letras, ou null se o código não existe. */
export function countryName(code: string): string | null {
    if (!/^[A-Za-z]{2}$/.test(code) || regionNames === null) return null;
    try {
        const name = regionNames.of(code.toUpperCase());
        return name && name.toUpperCase() !== code.toUpperCase() ? name : null;
    } catch {
        return null;
    }
}
