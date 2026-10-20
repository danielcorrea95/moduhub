import { cn } from '@/lib/utils';

const integer = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });

/**
 * Valor em reais com hierarquia: o "R$" e os centavos ficam menores e apagados, para o olho cair
 * direto nos reais. O texto lido por leitor de tela continua sendo o valor inteiro.
 */
export function Money({ cents, className }: { cents: number; className?: string }) {
    const negative = cents < 0;
    const abs = Math.abs(cents);
    const reais = integer.format(Math.floor(abs / 100));
    const centavos = String(abs % 100).padStart(2, '0');
    const label = `${negative ? 'menos ' : ''}R$ ${reais},${centavos}`;

    return (
        <span className={cn('inline-flex items-baseline font-semibold tracking-tight tabular-nums', className)} aria-label={label}>
            <span aria-hidden="true" className="mr-[0.15em] text-[0.42em] font-medium text-muted-foreground">
                R$
            </span>
            <span aria-hidden="true">
                {negative && '−'}
                {reais}
            </span>
            <span aria-hidden="true" className="text-[0.5em] font-medium text-muted-foreground">
                ,{centavos}
            </span>
        </span>
    );
}
