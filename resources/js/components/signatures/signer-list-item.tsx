import { ConfirmAction } from '@/components/confirm-action';
import {
    AUTH_METHOD_LABEL,
    SignerFields,
    type Signer,
    type SignerFormData,
} from '@/components/signatures/signer-fields';
import { SignerShareLink } from '@/components/signatures/signer-share-link';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn, formatDateTime } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Trash2 } from 'lucide-react';
import { useState, type SubmitEvent } from 'react';

function statusDetail(signer: Signer): string | null {
    if (signer.status === 'signed' && signer.signed_at) return `Assinou em ${formatDateTime(signer.signed_at)}`;
    if (signer.status === 'rejected' && signer.rejected_at) return `Recusou em ${formatDateTime(signer.rejected_at)}`;
    if (signer.status === 'viewed' && signer.viewed_at) return `Abriu em ${formatDateTime(signer.viewed_at)}`;
    return null;
}

export function SignerListItem({
    documentId,
    documentName,
    signer,
    index,
    signerIds,
    editable,
    reorderable,
}: {
    documentId: string;
    documentName: string;
    signer: Signer;
    index: number;
    /** Ids na ordem atual — usados para montar a nova ordem ao mover. */
    signerIds: string[];
    editable: boolean;
    reorderable: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors, clearErrors } = useForm<SignerFormData>({
        name: signer.name,
        role: signer.role ?? '',
        email: signer.email,
        cpf: signer.cpf ?? '',
        cnpj: signer.cnpj ?? '',
        phone: signer.phone ?? '',
        auth_method: signer.auth_method,
    });

    const submit = (e: SubmitEvent) => {
        e.preventDefault();
        patch(route('signatures.signers.update', [documentId, signer.id]), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const remove = () => {
        router.delete(route('signatures.signers.destroy', [documentId, signer.id]), {
            preserveScroll: true,
        });
    };

    const move = (direction: 'up' | 'down') => {
        const ids = [...signerIds];
        const i = ids.indexOf(signer.id);
        const j = direction === 'up' ? i - 1 : i + 1;
        if (i < 0 || j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        router.patch(route('signatures.signers.reorder', documentId), { order: ids }, { preserveScroll: true });
    };

    if (editing) {
        return (
            <li className="bg-accent/40 px-5 py-5 md:px-6">
                <form onSubmit={submit} className="space-y-4">
                    <h3 className="text-sm font-semibold">Editar {signer.name}</h3>
                    <SignerFields
                        idPrefix={`signer-${signer.id}`}
                        data={data}
                        setData={setData}
                        errors={errors}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Salvar alterações
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                clearErrors();
                                setEditing(false);
                            }}
                        >
                            Descartar
                        </Button>
                    </div>
                </form>
            </li>
        );
    }

    const detail = statusDetail(signer);
    const total = signerIds.length;

    return (
        <li className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:gap-4 md:px-6">
            <div className="flex min-w-0 flex-1 items-start gap-3">
                <span
                    className={cn(
                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                        signer.status === 'signed'
                            ? 'bg-signed-soft text-signed'
                            : signer.status === 'rejected'
                              ? 'bg-rejected-soft text-rejected'
                              : 'bg-muted text-muted-foreground',
                    )}
                    aria-label={`Posição ${index + 1}`}
                >
                    {index + 1}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="font-medium text-foreground">{signer.name}</span>
                        {signer.role && <span className="text-sm text-muted-foreground">{signer.role}</span>}
                        <StatusBadge kind="signer" status={signer.status} />
                    </div>
                    <p className="truncate text-sm text-muted-foreground">{signer.email}</p>
                    <dl className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
                        <div className="flex gap-1">
                            <dt className="sr-only">Autenticação</dt>
                            <dd>{AUTH_METHOD_LABEL[signer.auth_method] ?? signer.auth_method}</dd>
                        </div>
                        {signer.cpf && (
                            <div className="flex gap-1">
                                <dt>CPF</dt>
                                <dd className="tabular-nums text-foreground/80">{signer.cpf}</dd>
                            </div>
                        )}
                        {signer.cnpj && (
                            <div className="flex gap-1">
                                <dt>CNPJ</dt>
                                <dd className="tabular-nums text-foreground/80">{signer.cnpj}</dd>
                            </div>
                        )}
                        {signer.phone && (
                            <div className="flex gap-1">
                                <dt className="sr-only">Telefone</dt>
                                <dd className="tabular-nums">{signer.phone}</dd>
                            </div>
                        )}
                    </dl>
                    {detail && <p className="mt-1 text-xs text-muted-foreground">{detail}</p>}
                    {signer.status === 'rejected' && signer.rejection_reason && (
                        <p className="mt-1 text-sm text-rejected">Motivo: {signer.rejection_reason}</p>
                    )}
                    {signer.sign_url && (
                        <SignerShareLink
                            documentId={documentId}
                            documentName={documentName}
                            signer={signer}
                            signUrl={signer.sign_url}
                        />
                    )}
                </div>
            </div>

            {editable && (
                <div className="flex shrink-0 items-center gap-1 pl-10 sm:pl-0">
                    {reorderable && (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => move('up')}
                                disabled={index === 0}
                                aria-label={`Mover ${signer.name} para cima`}
                            >
                                <ChevronUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => move('down')}
                                disabled={index === total - 1}
                                aria-label={`Mover ${signer.name} para baixo`}
                            >
                                <ChevronDown />
                            </Button>
                        </>
                    )}
                    <Button type="button" variant="ghost" size="sm" onClick={() => setEditing(true)}>
                        <Pencil /> Editar
                    </Button>
                    <ConfirmAction
                        trigger={
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 /> Remover
                            </Button>
                        }
                        title={`Remover ${signer.name}?`}
                        description="Essa pessoa sai da lista de signatários deste documento. Você pode adicioná-la de novo enquanto o documento for um rascunho."
                        confirmLabel="Remover signatário"
                        destructive
                        onConfirm={remove}
                    />
                </div>
            )}
        </li>
    );
}
