import { cn } from '@/lib/utils';
import { Info, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

const METHOD_TONE = {
    GET: 'bg-sent-soft text-sent',
    POST: 'bg-signed-soft text-signed',
    PUT: 'bg-pending-soft text-pending',
    DELETE: 'bg-rejected-soft text-rejected',
} as const;

export function MethodBadge({ method }: { method: keyof typeof METHOD_TONE }) {
    return (
        <span className={cn('inline-flex shrink-0 rounded-md px-1.5 py-0.5 font-mono text-xs font-medium', METHOD_TONE[method])}>
            {method}
        </span>
    );
}

/** Código inline no texto. */
export function C({ children }: { children: ReactNode }) {
    return <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em] text-foreground">{children}</code>;
}

export function Prose({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('max-w-prose space-y-3 text-[0.9375rem] leading-relaxed text-foreground/85 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_a]:text-primary [&_a]:underline-offset-2 hover:[&_a]:underline', className)}>
            {children}
        </div>
    );
}

export function Callout({ tone = 'info', children }: { tone?: 'info' | 'warning'; children: ReactNode }) {
    const Icon = tone === 'warning' ? TriangleAlert : Info;
    return (
        <div
            className={cn(
                'flex max-w-prose gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed',
                tone === 'warning' ? 'border-pending/25 bg-pending-soft/60 text-foreground' : 'border-primary/15 bg-accent/60 text-foreground',
            )}
        >
            <Icon className={cn('mt-0.5 size-4 shrink-0', tone === 'warning' ? 'text-pending' : 'text-primary')} aria-hidden="true" />
            <div className="space-y-1">{children}</div>
        </div>
    );
}

/** Seção de topo (âncora do menu). */
export function Section({ id, title, children }: { id: string; title: string; children: ReactNode }) {
    return (
        <section id={id} className="scroll-mt-20 border-b px-5 py-12 last:border-b-0 md:px-10 lg:scroll-mt-6">
            <h2 className="text-xl font-semibold tracking-tight">
                <a href={`#${id}`} className="hover:text-primary focus-visible:outline-2 focus-visible:outline-ring">
                    {title}
                </a>
            </h2>
            <div className="mt-4 space-y-5">{children}</div>
        </section>
    );
}

/** Endpoint: explicação e parâmetros à esquerda, exemplos à direita (empilha em telas menores). */
export function Endpoint({
    id,
    title,
    method,
    path,
    scope,
    children,
    code,
}: {
    id: string;
    title: string;
    method: keyof typeof METHOD_TONE;
    path: string;
    scope: string;
    children: ReactNode;
    code: ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-20 border-b px-5 py-12 last:border-b-0 md:px-10 lg:scroll-mt-6">
            <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                <div className="min-w-0 space-y-5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        <a href={`#${id}`} className="hover:text-primary focus-visible:outline-2 focus-visible:outline-ring">
                            {title}
                        </a>
                    </h2>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span className="flex min-w-0 items-center gap-2">
                            <MethodBadge method={method} />
                            <code className="font-mono text-sm break-all text-foreground">{path}</code>
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Escopo <C>{scope}</C>
                        </span>
                    </div>
                    {children}
                </div>
                <div className="min-w-0 space-y-4 xl:sticky xl:top-6 xl:self-start">{code}</div>
            </div>
        </section>
    );
}

export interface Param {
    name: string;
    type: string;
    required?: boolean;
    description: ReactNode;
}

export function ParamTable({ title, params }: { title: string; params: Param[] }) {
    return (
        <div>
            <h3 className="text-sm font-medium">{title}</h3>
            <dl className="mt-2 divide-y rounded-xl border">
                {params.map((p) => (
                    <div key={p.name} className="grid gap-1 px-4 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4">
                        <dt className="min-w-0">
                            <code className="font-mono text-[0.8125rem] break-all text-foreground">{p.name}</code>
                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                {p.type}
                                {p.required && <span className="text-pending"> obrigatório</span>}
                            </span>
                        </dt>
                        <dd className="text-sm leading-relaxed text-foreground/85">{p.description}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
