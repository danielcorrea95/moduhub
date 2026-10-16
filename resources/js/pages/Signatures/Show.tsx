import { SignerProgress } from '@/components/signer-progress';
import { AddSignerForm } from '@/components/signatures/add-signer-form';
import { ConfirmAction } from '@/components/confirm-action';
import { EventTimeline, type DocumentEvent } from '@/components/signatures/event-timeline';
import type { Signer } from '@/components/signatures/signer-fields';
import { SignerListItem } from '@/components/signatures/signer-list-item';
import { StatusBadge, type DocumentStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { Download, FileText, Send, Trash2, Users } from 'lucide-react';
import type { ReactNode } from 'react';

interface ShowDocument {
    id: string;
    name: string;
    status: DocumentStatus;
    page_count: number | null;
    file_size: number | null;
    file_hash: string | null;
    sign_order: 'parallel' | 'sequential';
    message: string | null;
    external_id: string | null;
    expires_at: string | null;
    sent_at: string | null;
    completed_at: string | null;
    created_at: string;
    has_final_file: boolean;
    creator: { name: string; email: string } | null;
    signers: Signer[];
    fields: unknown[];
    events: DocumentEvent[];
}

interface Props {
    document: ShowDocument;
    [key: string]: unknown;
}

function formatBytes(bytes: number | null): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let value = bytes;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[i]}`;
}

function headerSubtitle(d: ShowDocument): string {
    if (d.status === 'completed' && d.completed_at) return `Concluído em ${formatDate(d.completed_at)}`;
    if (d.sent_at) return `Enviado em ${formatDate(d.sent_at)}`;
    return `Criado em ${formatDate(d.created_at)}`;
}

function Meta({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium text-foreground">{children}</dd>
        </div>
    );
}

export default function SignaturesShow({ document }: Props) {
    const isDraft = document.status === 'draft';
    const hasWorkingPdf = document.has_final_file;
    const canSend = isDraft && document.signers.length > 0;
    const isSequential = document.sign_order === 'sequential';
    const signerIds = document.signers.map((s) => s.id);

    const remove = () => {
        router.delete(route('signatures.destroy', document.id));
    };

    const send = () => {
        router.post(route('signatures.send', document.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Documentos', href: route('signatures.index') },
                { title: document.name },
            ]}
        >
            <Head title={document.name} />

            <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <h1 className="break-words text-2xl font-semibold tracking-tight text-foreground">
                            {document.name}
                        </h1>
                        <StatusBadge status={document.status} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {headerSubtitle(document)}
                        {document.creator && <> por {document.creator.name}</>}
                    </p>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {isDraft && (
                        <ConfirmAction
                            trigger={
                                <Button
                                    variant="ghost"
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2 /> Excluir documento
                                </Button>
                            }
                            title="Excluir este documento?"
                            description="O PDF e os signatários cadastrados serão apagados. Essa ação não pode ser desfeita."
                            confirmLabel="Excluir documento"
                            destructive
                            onConfirm={remove}
                        />
                    )}
                    {hasWorkingPdf && (
                        <Button variant="outline" asChild>
                            <a href={route('signatures.download-working', document.id)}>
                                <Download /> Baixar PDF com assinaturas
                            </a>
                        </Button>
                    )}
                    {isDraft && (
                        <ConfirmAction
                            trigger={
                                <Button disabled={!canSend}>
                                    <Send /> Enviar para assinatura
                                </Button>
                            }
                            title="Enviar para assinatura?"
                            description="Os signatários passam a poder assinar o documento. Depois do envio, não é mais possível editar, reordenar ou remover signatários."
                            confirmLabel="Enviar para assinatura"
                            cancelLabel="Revisar antes"
                            onConfirm={send}
                        />
                    )}
                </div>
            </header>

            {isDraft && document.signers.length === 0 && (
                <p className="-mt-2 text-sm text-muted-foreground">
                    Adicione pelo menos um signatário para poder enviar o documento.
                </p>
            )}

            <section aria-labelledby="signers-heading" className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <div className="flex flex-col gap-2 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
                    <div>
                        <h2 id="signers-heading" className="text-base font-medium">
                            Signatários
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {isSequential
                                ? 'Assinam um de cada vez, na ordem abaixo.'
                                : 'Todos podem assinar ao mesmo tempo.'}
                            {isDraft && isSequential && document.signers.length > 1 && (
                                <> Use as setas para mudar a ordem.</>
                            )}
                        </p>
                    </div>
                    <SignerProgress signers={document.signers} />
                </div>

                {document.signers.length === 0 ? (
                    <div className="flex items-center gap-3 px-5 py-6 text-sm text-muted-foreground md:px-6">
                        <Users className="size-4 shrink-0" />
                        Nenhum signatário adicionado ainda.
                    </div>
                ) : (
                    <ul className="divide-y">
                        {document.signers.map((s, i) => (
                            <SignerListItem
                                key={s.id}
                                documentId={document.id}
                                documentName={document.name}
                                signer={s}
                                index={i}
                                signerIds={signerIds}
                                editable={isDraft}
                                reorderable={isSequential}
                            />
                        ))}
                    </ul>
                )}

                {isDraft && <AddSignerForm documentId={document.id} />}
            </section>

            <section aria-labelledby="file-heading" className="rounded-xl border bg-card px-5 py-5 md:px-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                            <FileText className="size-4" />
                        </div>
                        <h2 id="file-heading" className="text-base font-medium">
                            Arquivo
                        </h2>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href={route('signatures.download', document.id)}>
                            <Download /> Baixar PDF original
                        </a>
                    </Button>
                </div>

                <dl className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Meta label="Páginas">{document.page_count ?? '—'}</Meta>
                    <Meta label="Tamanho">{formatBytes(document.file_size)}</Meta>
                    <Meta label="Ordem de assinatura">{isSequential ? 'Sequencial' : 'Paralela'}</Meta>
                    <Meta label="Prazo">
                        {document.expires_at ? formatDateTime(document.expires_at) : 'Sem prazo'}
                    </Meta>
                </dl>

                {document.message && (
                    <div className="mt-5">
                        <h3 className="text-xs text-muted-foreground">Mensagem aos signatários</h3>
                        <p className="mt-1 whitespace-pre-line text-sm text-foreground">{document.message}</p>
                    </div>
                )}

                <div className="mt-5 rounded-lg bg-muted/50 px-3 py-2.5">
                    <h3 className="text-xs text-muted-foreground">Hash SHA-256 do PDF original</h3>
                    <code className="mt-1 block break-all font-mono text-xs text-foreground/80">
                        {document.file_hash ?? '—'}
                    </code>
                </div>
            </section>

            <section aria-labelledby="events-heading" className="px-1">
                <h2 id="events-heading" className="mb-4 text-base font-medium">
                    Trilha de auditoria
                </h2>
                <EventTimeline events={document.events} />
            </section>
        </AppLayout>
    );
}
