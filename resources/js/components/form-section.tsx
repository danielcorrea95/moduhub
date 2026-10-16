import type { ReactNode } from 'react';

/** Bloco de formulário com título e explicação à esquerda e campos à direita (empilha no celular). */
export function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-6 md:grid-cols-[14rem_1fr] md:gap-10">
            <header>
                <h2 className="text-base font-medium">{title}</h2>
                <p className="mt-1 text-sm text-muted-foreground">{description}</p>
            </header>
            <div className="flex min-w-0 flex-col gap-5">{children}</div>
        </section>
    );
}
