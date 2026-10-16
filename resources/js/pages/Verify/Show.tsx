import { SignerProgress } from '@/components/signer-progress';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import { Ban, Clock, Download, ShieldCheck, ShieldX, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface VerifyDocument {
    id: string;
    name: string;
    status: string;
    sign_order: 'parallel' | 'sequential';
    page_count: number | null;
    file_hash: string | null;
    final_file_hash: string | null;
    created_at: string | null;
    sent_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
    seal: {
        sealed_at: string | null;
        certificate_subject: string | null;
        certificate_issuer: string | null;
        certificate_valid_to: string | null;
    } | null;
}

interface VerifySigner {
    id: number | string;
    order: number;
    name: string;
    role: string | null;
    email: string;
    cpf: string | null;
    cnpj: string | null;
    auth_method: string;
    signature_type: string | null;
    status: string;
    signed_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    ip_address: string | null;
    icp_certificate_subject: string | null;
    icp_certificate_issuer: string | null;
    icp_certificate_valid_to: string | null;
}

interface VerifyEvent {
    id: number | string;
    type: string;
    created_at: string | null;
    ip_address: string | null;
}

interface Props {
    document: VerifyDocument;
    signers: VerifySigner[];
    events: VerifyEvent[];
    tenant: PublicTenant | null;
    short: string;
    can_download: boolean;
    [key: string]: unknown;
}

const AUTH_LABELS: Record<string, string> = {
    email: 'Código por e-mail',
    sms: 'Código por SMS',
    email_and_sms: 'Código por e-mail e SMS',
    icp: 'Certificado ICP-Brasil',
};

const EVENT_LABELS: Record<string, string> = {
    created: 'Documento criado',
    updated: 'Documento atualizado',
    sent: 'Enviado para assinatura',
    viewed: 'Visualizado por signatário',
    code_sent: 'Código de verificação enviado',
    code_verified: 'Código verificado',
    signed: 'Assinatura registrada',
    rejected: 'Recusado',
    cancelled: 'Cancelado',
    expired: 'Expirado',
    downloaded: 'Baixado',
    finalized: 'Finalizado',
    copies_sent: 'Cópia assinada enviada aos envolvidos',
};

type Tone = 'signed' | 'pending' | 'rejected' | 'idle';

const TONE: Record<Tone, string> = {
    signed: 'bg-signed-soft text-signed',
    pending: 'bg-pending-soft text-pending',
    rejected: 'bg-rejected-soft text-rejected',
    idle: 'bg-idle-soft text-idle',
};

const DOT: Record<string, string> = {
    signed: 'bg-signed',
    rejected: 'bg-rejected',
    viewed: 'bg-sent',
    pending: 'bg-transparent ring-1 ring-inset ring-idle/60',
};

function verdict(status: string): { tone: Tone; icon: LucideIcon; title: string; text: string } {
    switch (status) {
        case 'completed':
            return {
                tone: 'signed',
                icon: ShieldCheck,
                title: 'Documento autêntico e concluído',
                text: 'Todas as assinaturas foram registradas por esta plataforma.',
            };
        case 'sent':
        case 'partially_signed':
            return {
                tone: 'pending',
                icon: Clock,
                title: 'Documento autêntico, assinaturas em andamento',
                text: 'O documento está registrado, mas ainda faltam assinaturas.',
            };
        case 'rejected':
            return {
                tone: 'rejected',
                icon: ShieldX,
                title: 'Documento recusado',
                text: 'Um signatário recusou o documento. Ele não tem todas as assinaturas e não pode mais ser assinado.',
            };
        case 'expired':
            return {
                tone: 'idle',
                icon: Ban,
                title: 'Prazo de assinatura encerrado',
                text: 'O prazo terminou antes de todas as assinaturas serem registradas.',
            };
        case 'cancelled':
            return {
                tone: 'idle',
                icon: Ban,
                title: 'Documento cancelado',
                text: 'O emissor cancelou o documento antes da conclusão.',
            };
        default:
            return {
                tone: 'idle',
                icon: Clock,
                title: 'Documento ainda não enviado',
                text: 'O documento está registrado, mas ainda não foi enviado para assinatura.',
            };
    }
}

// Na verificação pública os segundos importam: são o que se compara com a trilha do PDF.
const timestampFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

function formatTimestamp(value: string | null | undefined): string {
    return value ? timestampFormatter.format(new Date(value)) : '—';
}

export default function VerifyShow({ document, signers, events, tenant, short, can_download }: Props) {
    const v = verdict(document.status);
    const VerdictIcon = v.icon;

    return (
        <PublicLayout title={`Verificação de ${document.name}`} tenant={tenant}>
            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <div className={cn('flex gap-3 px-5 py-4 sm:px-6', TONE[v.tone])} role="status">
                            <VerdictIcon className="mt-0.5 size-6 shrink-0" aria-hidden="true" />
                            <div>
                                <p className="font-semibold">{v.title}</p>
                                <p className="mt-0.5 text-sm text-foreground/80">{v.text}</p>
                            </div>
                        </div>

                        <div className="px-5 py-5 sm:px-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <h1 className="min-w-0 text-2xl font-semibold tracking-tight break-words">
                                    {document.name}
                                </h1>
                                <StatusBadge status={document.status} />
                            </div>

                            <dl className="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <Field label="Identificador">
                                    <code className="font-mono">{short}</code>
                                </Field>
                                <Field label="Páginas">{document.page_count ?? '—'}</Field>
                                <Field label="Ordem de assinatura">
                                    {document.sign_order === 'sequential' ? 'Sequencial' : 'Paralela'}
                                </Field>
                                <Field label="Criado em">{formatTimestamp(document.created_at)}</Field>
                                <Field label="Enviado em">{formatTimestamp(document.sent_at)}</Field>
                                {document.completed_at && (
                                    <Field label="Concluído em">{formatTimestamp(document.completed_at)}</Field>
                                )}
                                {document.expires_at && (
                                    <Field label="Prazo até">{formatTimestamp(document.expires_at)}</Field>
                                )}
                                <Field label="SHA-256 do PDF original" wide>
                                    <span className="font-mono text-xs break-all">{document.file_hash ?? '—'}</span>
                                </Field>
                                {document.final_file_hash && (
                                    <Field label="SHA-256 do PDF final (assinado)" wide>
                                        <span className="font-mono text-xs break-all">{document.final_file_hash}</span>
                                    </Field>
                                )}
                            </dl>

                            {document.seal && (
                                <div className="mt-6 flex gap-3 rounded-lg bg-sent-soft px-4 py-3 text-sm text-sent">
                                    <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                    <div className="min-w-0">
                                        <p className="font-semibold">Documento selado com certificado ICP-Brasil</p>
                                        <p className="mt-0.5 text-foreground/80">
                                            O PDF final foi assinado digitalmente pela plataforma em{' '}
                                            <span className="font-mono">{formatTimestamp(document.seal.sealed_at)}</span>.
                                            Qualquer alteração no arquivo invalida o selo, e leitores como o Adobe
                                            Reader mostram o aviso.
                                        </p>
                                        <dl className="mt-2 space-y-1 text-xs text-foreground/80">
                                            <div>
                                                <dt className="inline">Certificado: </dt>
                                                <dd className="inline font-mono break-all">
                                                    {document.seal.certificate_subject ?? '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="inline">Emitido por: </dt>
                                                <dd className="inline font-mono break-all">
                                                    {document.seal.certificate_issuer ?? '—'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            )}

                            {can_download && (
                                <Button asChild size="lg" className="mt-6 w-full sm:w-auto">
                                    <a href={route('verify.download', short)}>
                                        <Download /> Baixar PDF assinado
                                    </a>
                                </Button>
                            )}
                        </div>
                    </section>

                    <section className="rounded-xl border bg-card shadow-xs" aria-labelledby="signers-title">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                            <h2 id="signers-title" className="font-semibold">
                                Signatários
                            </h2>
                            <SignerProgress signers={signers} />
                        </div>
                        <ul className="divide-y">
                            {signers.map((s) => (
                                <li key={s.id} className="px-5 py-4 sm:px-6">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-start gap-2.5">
                                            <span
                                                className={cn(
                                                    'mt-1.5 size-2 shrink-0 rounded-full',
                                                    DOT[s.status] ?? DOT.pending,
                                                )}
                                                aria-hidden="true"
                                            />
                                            <div className="min-w-0">
                                                <p className="font-medium break-words">
                                                    <span className="text-muted-foreground tabular-nums">{s.order}.</span>{' '}
                                                    {s.name}
                                                </p>
                                                {s.role && (
                                                    <p className="text-sm text-foreground/80">
                                                        {s.status === 'signed' ? 'Assinou como' : 'Assina como'} {s.role}
                                                    </p>
                                                )}
                                                <p className="text-sm break-all text-muted-foreground">{s.email}</p>
                                                {(s.cpf || s.cnpj) && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {s.cpf && <>CPF {s.cpf}</>}
                                                        {s.cpf && s.cnpj && ', '}
                                                        {s.cnpj && <>CNPJ {s.cnpj}</>}
                                                    </p>
                                                )}
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Autenticação: {AUTH_LABELS[s.auth_method] ?? s.auth_method}
                                                </p>
                                            </div>
                                        </div>
                                        <StatusBadge status={s.status} kind="signer" />
                                    </div>

                                    {s.status === 'signed' && (
                                        <div className="mt-3 space-y-3 pl-4.5 text-xs">
                                            <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-3">
                                                <Field label="Assinado em" small>
                                                    <span className="font-mono">{formatTimestamp(s.signed_at)}</span>
                                                </Field>
                                                {s.ip_address && (
                                                    <Field label="IP" small>
                                                        <span className="font-mono break-all">{s.ip_address}</span>
                                                    </Field>
                                                )}
                                                <Field label="Tipo" small>
                                                    {s.signature_type === 'icp' ? 'ICP-Brasil' : 'Eletrônica'}
                                                </Field>
                                            </dl>
                                            {s.signature_type === 'icp' && (
                                                <dl className="space-y-1.5 rounded-lg bg-muted px-3 py-2.5">
                                                    <Field label="Certificado" small>
                                                        <span className="font-mono break-all">
                                                            {s.icp_certificate_subject ?? '—'}
                                                        </span>
                                                    </Field>
                                                    <Field label="Emitido por" small>
                                                        <span className="font-mono break-all">
                                                            {s.icp_certificate_issuer ?? '—'}
                                                        </span>
                                                    </Field>
                                                    <Field label="Válido até" small>
                                                        <span className="font-mono">
                                                            {formatTimestamp(s.icp_certificate_valid_to)}
                                                        </span>
                                                    </Field>
                                                </dl>
                                            )}
                                        </div>
                                    )}

                                    {s.status === 'rejected' && (
                                        <div className="mt-3 ml-4.5 rounded-lg bg-rejected-soft px-3 py-2.5 text-xs">
                                            <p className="text-rejected">
                                                Recusado em{' '}
                                                <span className="font-mono">{formatTimestamp(s.rejected_at)}</span>
                                            </p>
                                            {s.rejection_reason && (
                                                <p className="mt-1 whitespace-pre-line text-foreground/80">
                                                    “{s.rejection_reason}”
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <aside className="min-w-0 space-y-4">
                    <section className="rounded-xl border bg-card px-5 py-5" aria-labelledby="audit-title">
                        <h2 id="audit-title" className="font-semibold">
                            Trilha de auditoria
                        </h2>
                        {events.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">Nenhum evento registrado.</p>
                        ) : (
                            <ol className="mt-4 space-y-3 border-l pl-4">
                                {events.map((e) => (
                                    <li key={e.id} className="text-sm">
                                        <p className="text-foreground/90">{EVENT_LABELS[e.type] ?? e.type}</p>
                                        <p className="text-xs text-muted-foreground">
                                            <time className="font-mono" dateTime={e.created_at ?? undefined}>
                                                {formatTimestamp(e.created_at)}
                                            </time>
                                            {e.ip_address && (
                                                <>
                                                    {', IP '}
                                                    <span className="font-mono break-all">{e.ip_address}</span>
                                                </>
                                            )}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    <p className="px-1 text-xs leading-relaxed text-muted-foreground">
                        Qualquer pessoa com o link ou com o QR code do PDF pode abrir esta página. Para conferir a
                        integridade, compare os códigos SHA-256 acima com o arquivo que você tem: qualquer alteração
                        no PDF muda o código.
                    </p>
                </aside>
            </div>
        </PublicLayout>
    );
}

function Field({
    label,
    wide = false,
    small = false,
    children,
}: {
    label: string;
    wide?: boolean;
    small?: boolean;
    children: ReactNode;
}) {
    return (
        <div className={cn('min-w-0', wide && 'sm:col-span-2')}>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('text-foreground', !small && 'mt-0.5')}>{children}</dd>
        </div>
    );
}
