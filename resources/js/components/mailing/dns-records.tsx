import { CopyButton } from '@/components/copy-button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { Check, CircleAlert, Clock, Minus } from 'lucide-react';
import { useState } from 'react';
import type { DnsRecord, RecordPurpose, RecordStatus } from './types';

const GROUPS: { purpose: RecordPurpose; title: string; description: string }[] = [
    {
        purpose: 'dkim',
        title: 'Assinatura DKIM, obrigatória',
        description: 'Prova que a Moduhub pode enviar em nome do seu domínio. A verificação acontece quando os três aparecem.',
    },
    {
        purpose: 'mail_from',
        title: 'Endereço de retorno, recomendado',
        description: 'Melhora a entrega. Sem ele, o envio funciona com o endereço de retorno da Amazon.',
    },
    {
        purpose: 'dmarc',
        title: 'DMARC, recomendado',
        description: 'Gmail e Yahoo exigem. Se o domínio já tem um registro _dmarc, mantenha o seu e não crie outro.',
    },
];

const STATUS: Record<RecordStatus, { label: string; className: string; Icon: typeof Check }> = {
    ok: { label: 'Encontrado', className: 'text-signed', Icon: Check },
    pending: { label: 'Aguardando', className: 'text-pending', Icon: Clock },
    failed: { label: 'Não encontrado', className: 'text-rejected', Icon: CircleAlert },
    recommended: { label: 'Não conferido', className: 'text-muted-foreground', Icon: Minus },
};

/** Quantos dos registros que a Moduhub confere já estão no ar. O DMARC não é conferido. */
export function recordProgress(records: DnsRecord[]) {
    const checked = records.filter((r) => r.status !== 'recommended');

    return { found: checked.filter((r) => r.status === 'ok').length, total: checked.length };
}

/**
 * Registros a publicar. O nome sai como o painel do provedor pede (só "bounce", sem o domínio),
 * com a opção de ver o nome completo para painéis que exigem.
 */
export function DnsRecords({ records, domain }: { records: DnsRecord[]; domain: string }) {
    const [fullNames, setFullNames] = useState(false);
    const { found, total } = recordProgress(records);

    const displayName = (name: string) => (fullNames ? name : name.replace(new RegExp(`\\.${domain.replaceAll('.', '\\.')}$`), ''));

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <ProgressRing found={found} total={total} />
                    <p className="text-sm">
                        <span className="font-medium">
                            {found} de {total} registros encontrados
                        </span>
                        <span className="block text-muted-foreground">Crie no painel onde o domínio está hospedado (Registro.br, Locaweb, Cloudflare…).</span>
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Switch id="dns-full-names" checked={fullNames} onCheckedChange={setFullNames} />
                    <Label htmlFor="dns-full-names" className="text-sm font-normal text-muted-foreground">
                        Nome completo
                    </Label>
                </div>
            </div>

            {GROUPS.map((group) => {
                const rows = records.filter((r) => r.purpose === group.purpose);
                if (rows.length === 0) return null;

                return (
                    <section key={group.purpose} aria-labelledby={`dns-${group.purpose}`}>
                        <h4 id={`dns-${group.purpose}`} className="text-sm font-medium">
                            {group.title}
                        </h4>
                        <p className="mt-0.5 text-sm text-muted-foreground">{group.description}</p>

                        <div className="mt-3 overflow-hidden rounded-lg border">
                            <div className="hidden grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,1.4fr)_7.5rem] gap-4 border-b bg-muted/50 px-4 py-2 text-xs text-muted-foreground md:grid">
                                <span>Tipo</span>
                                <span>Nome</span>
                                <span>Valor</span>
                                <span className="text-right">Situação</span>
                            </div>
                            <ul className="divide-y">
                                {rows.map((record) => (
                                    <RecordRow key={`${record.type}-${record.name}`} record={record} name={displayName(record.name)} />
                                ))}
                            </ul>
                        </div>
                    </section>
                );
            })}
        </div>
    );
}

function RecordRow({ record, name }: { record: DnsRecord; name: string }) {
    const status = STATUS[record.status];

    return (
        <li className="grid gap-3 px-4 py-3 md:grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,1.4fr)_7.5rem] md:items-center md:gap-4">
            <div className="flex items-center justify-between md:block">
                <span className="rounded bg-muted px-2 py-0.5 font-mono text-xs font-medium">{record.type}</span>
                <StatusLabel status={status} className="md:hidden" />
            </div>
            <CopyableValue label="Nome" value={name} />
            <CopyableValue label="Valor" value={record.value} />
            <StatusLabel status={status} className="hidden justify-end md:flex" />
        </li>
    );
}

function CopyableValue({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-w-0 items-center gap-1">
            <span className="w-12 shrink-0 text-xs text-muted-foreground md:sr-only">{label}</span>
            <code className="min-w-0 flex-1 font-mono text-sm break-all">{value}</code>
            <CopyButton value={value} label="" copiedLabel="" variant="ghost" size="icon" aria-label={`Copiar ${label.toLowerCase()}`} className="size-8 shrink-0" />
        </div>
    );
}

function StatusLabel({ status, className }: { status: (typeof STATUS)[RecordStatus]; className?: string }) {
    const { label, className: tone, Icon } = status;

    return (
        <span className={cn('flex items-center gap-1.5 text-xs font-medium', tone, className)}>
            <Icon className="size-3.5" aria-hidden="true" />
            {label}
        </span>
    );
}

function ProgressRing({ found, total }: { found: number; total: number }) {
    const ratio = total === 0 ? 0 : found / total;
    const circumference = 2 * Math.PI * 16;
    const complete = found === total && total > 0;

    return (
        <svg viewBox="0 0 40 40" className="size-10 shrink-0 -rotate-90" aria-hidden="true">
            <circle cx="20" cy="20" r="16" fill="none" strokeWidth="4" className="stroke-muted" />
            <circle
                cx="20"
                cy="20"
                r="16"
                fill="none"
                strokeWidth="4"
                strokeLinecap="round"
                strokeDasharray={circumference}
                strokeDashoffset={circumference * (1 - ratio)}
                className={cn('transition-[stroke-dashoffset] duration-500', complete ? 'stroke-signed' : 'stroke-pending')}
            />
        </svg>
    );
}
