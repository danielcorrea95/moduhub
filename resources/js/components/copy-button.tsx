import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-react';
import { useEffect, useState, type ComponentProps } from 'react';
import { toast } from 'sonner';

/** Copia `value` para a área de transferência e confirma por 2s no próprio botão. */
export function CopyButton({
    value,
    label = 'Copiar',
    copiedLabel = 'Copiado',
    variant = 'outline',
    size = 'sm',
    ...props
}: Omit<ComponentProps<typeof Button>, 'onClick' | 'children'> & {
    value: string;
    label?: string;
    copiedLabel?: string;
}) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) return;
        const t = setTimeout(() => setCopied(false), 2000);
        return () => clearTimeout(t);
    }, [copied]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
        } catch {
            toast.error('Não foi possível copiar. Selecione o texto e copie manualmente.');
        }
    };

    return (
        <Button type="button" variant={variant} size={size} onClick={copy} {...props}>
            {copied ? <Check aria-hidden="true" /> : <Copy aria-hidden="true" />}
            <span aria-live="polite">{copied ? copiedLabel : label}</span>
        </Button>
    );
}
