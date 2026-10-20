import { cn } from '@/lib/utils';

const ROWS: [string, string][] = [
    ['Prestador', 'Acme Consultoria Ltda'],
    ['Tomador', 'Tavares Advocacia'],
    ['Serviço', '17.01.01 Consultoria em gestão empresarial'],
    ['Município', 'Novo Hamburgo (RS)'],
];

/** Uma NFS-e autorizada, resumida como no DANFSe. Estática: a página já anima a emissão no topo. */
export function NfsePreview({ className }: { className?: string }) {
    return (
        <article
            aria-label="Exemplo de NFS-e autorizada"
            className={cn('rounded-xl border bg-card shadow-[0_1px_2px_rgb(27_34_48/0.05),0_24px_48px_-24px_rgb(27_34_48/0.25)]', className)}
        >
            <header className="flex items-start justify-between gap-4 border-b px-6 py-5">
                <div>
                    <p className="text-xs text-muted-foreground">Nota Fiscal de Serviço eletrônica</p>
                    <h3 className="mt-0.5 text-lg font-semibold">NFS-e nº 128</h3>
                </div>
                <span className="rounded-full bg-signed-soft px-2.5 py-0.5 text-xs font-medium text-signed">Autorizada</span>
            </header>

            <dl className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 px-6 py-5 text-sm">
                {ROWS.map(([label, value]) => (
                    <div key={label} className="contents">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>

            <dl className="grid grid-cols-3 border-y text-sm">
                <div className="px-6 py-4">
                    <dt className="text-xs text-muted-foreground">Valor</dt>
                    <dd className="mt-0.5 font-semibold tabular-nums">R$ 4.800,00</dd>
                </div>
                <div className="border-x px-4 py-4">
                    <dt className="text-xs text-muted-foreground">ISS (2%)</dt>
                    <dd className="mt-0.5 tabular-nums">R$ 96,00</dd>
                </div>
                <div className="px-4 py-4">
                    <dt className="text-xs text-muted-foreground">Líquido</dt>
                    <dd className="mt-0.5 tabular-nums">R$ 4.704,00</dd>
                </div>
            </dl>

            <footer className="px-6 py-4">
                <p className="text-xs text-muted-foreground">Chave de acesso</p>
                <p className="mt-1 break-all font-mono text-xs text-foreground/80">4314607 2 26 1 01234567000189 000000000000128 4 202610</p>
            </footer>
        </article>
    );
}
