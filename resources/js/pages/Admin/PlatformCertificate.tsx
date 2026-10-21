import { ConfirmAction } from '@/components/confirm-action';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatCnpj } from '@/lib/fiscal';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { FileKey2, History, ServerCog, ShieldAlert, ShieldCheck, ShieldX, Upload } from 'lucide-react';
import { useRef, type FormEvent } from 'react';

interface PlatformCertificateData {
    id: string;
    subject: string;
    cnpj: string;
    issuer: string;
    serial: string;
    fingerprint: string;
    valid_from: string;
    valid_until: string;
    days_left: number;
    uploaded_at: string | null;
    uploaded_by: string | null;
    uploaded_ip: string | null;
}

interface HistoryEntry extends PlatformCertificateData {
    retired_at: string | null;
    retired_reason: 'replaced' | 'removed' | null;
    retired_by: string | null;
    retired_ip: string | null;
}

interface Props {
    current: PlatformCertificateData | null;
    hasServerCertificate: boolean;
    platformCnpj: string | null;
    history: HistoryEntry[];
    [key: string]: unknown;
}

interface UploadForm {
    certificate: File | null;
    password: string;
    current_password: string;
}

function CurrentStatus({ certificate }: { certificate: PlatformCertificateData }) {
    const state =
        certificate.days_left <= 0
            ? { label: 'Expirado', icon: ShieldX, className: 'border-rejected/30 bg-rejected-soft text-rejected' }
            : certificate.days_left <= 30
              ? { label: 'Vence em breve', icon: ShieldAlert, className: 'border-pending/30 bg-pending-soft text-pending' }
              : { label: 'Válido', icon: ShieldCheck, className: 'border-signed/30 bg-signed-soft text-signed' };

    return (
        <div className={cn('flex items-start gap-3 rounded-lg border px-4 py-3', state.className)}>
            <state.icon className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1 text-sm">
                <p className="font-semibold">
                    {state.label}
                    <span className="font-normal"> até {formatDate(certificate.valid_until)}</span>
                </p>
                <dl className="mt-2 grid gap-x-6 gap-y-1 text-foreground/85 sm:grid-cols-[auto_minmax(0,1fr)]">
                    <dt className="text-muted-foreground">Titular</dt>
                    <dd className="min-w-0 break-words">{certificate.subject}</dd>
                    <dt className="text-muted-foreground">CNPJ</dt>
                    <dd>{formatCnpj(certificate.cnpj)}</dd>
                    <dt className="text-muted-foreground">Emitido por</dt>
                    <dd className="min-w-0 break-words">{certificate.issuer}</dd>
                    <dt className="text-muted-foreground">Impressão digital</dt>
                    <dd className="min-w-0 break-all font-mono text-xs">{certificate.fingerprint}</dd>
                    <dt className="text-muted-foreground">Enviado</dt>
                    <dd className="min-w-0 break-words">
                        {formatDateTime(certificate.uploaded_at)}
                        {certificate.uploaded_by && ` por ${certificate.uploaded_by}`}
                        {certificate.uploaded_ip && ` · ${certificate.uploaded_ip}`}
                    </dd>
                </dl>
            </div>
        </div>
    );
}

function retiredLabel(entry: HistoryEntry): string {
    if (entry.retired_reason === 'replaced') return 'Substituído';
    if (entry.retired_reason === 'removed') return 'Removido';
    return 'Em uso';
}

function CertificateHistory({ entries }: { entries: HistoryEntry[] }) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <History className="size-4 text-muted-foreground" aria-hidden="true" />
                <p className="text-sm font-medium">Histórico</p>
            </div>
            <p className="text-sm text-muted-foreground">
                Cada envio, troca e remoção, com quem fez e de onde. O arquivo e a senha dos certificados que saíram de uso são apagados.
            </p>
            {entries.length === 0 ? (
                <p className="rounded-lg border border-dashed px-4 py-4 text-center text-sm text-muted-foreground">Nenhum envio pelo painel ainda.</p>
            ) : (
                <ol className="divide-y rounded-lg border">
                    {entries.map((entry) => (
                        <li key={entry.id} className="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                            <div className="min-w-0">
                                <p className="font-medium">
                                    {retiredLabel(entry)} · válido até {formatDate(entry.valid_until)}
                                </p>
                                <p className="break-words text-muted-foreground">
                                    {entry.subject} · impressão digital {entry.fingerprint.slice(0, 12)}…
                                </p>
                            </div>
                            <div className="shrink-0 text-muted-foreground sm:text-right">
                                <p>
                                    Enviado em {formatDateTime(entry.uploaded_at)} por {entry.uploaded_by ?? 'Sistema'}
                                    {entry.uploaded_ip && ` · ${entry.uploaded_ip}`}
                                </p>
                                {entry.retired_at && (
                                    <p>
                                        Saiu em {formatDateTime(entry.retired_at)} por {entry.retired_by ?? 'Sistema'}
                                        {entry.retired_ip && ` · ${entry.retired_ip}`}
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

export default function AdminPlatformCertificate({ current, hasServerCertificate, platformCnpj, history }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const upload = useForm<UploadForm>({ certificate: null, password: '', current_password: '' });
    const removal = useForm<{ current_password: string }>({ current_password: '' });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        upload.post(route('admin.platform-certificate.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                upload.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
            onFinish: () => upload.reset('password', 'current_password'),
        });
    };

    const remove = () =>
        removal.delete(route('admin.platform-certificate.destroy'), {
            preserveScroll: true,
            onFinish: () => removal.reset(),
        });

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Certificado da plataforma' }]}>
            <Head title="Certificado da plataforma" />

            <PageHeader
                title="Certificado da plataforma"
                description="O e-CNPJ A1 da Moduhub sela o PDF de todo documento concluído e autentica as consultas ao ADN. O enviado aqui vale sobre o configurado no servidor."
            />

            <FormSection
                title="Em uso"
                description="O arquivo e a senha ficam cifrados e nunca saem do sistema: esta tela não permite baixar nem ver a senha."
            >
                {current ? (
                    <CurrentStatus certificate={current} />
                ) : (
                    <div className="flex items-start gap-3 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        {hasServerCertificate ? (
                            <>
                                <ServerCog className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                                <p>Nenhum certificado enviado pelo painel. O selo usa o certificado configurado no servidor.</p>
                            </>
                        ) : (
                            <>
                                <FileKey2 className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                                <p>Nenhum certificado configurado. Os documentos concluídos estão saindo sem o selo ICP-Brasil.</p>
                            </>
                        )}
                    </div>
                )}

                {!platformCnpj ? (
                    <p className="rounded-lg border border-pending/30 bg-pending-soft px-4 py-3 text-sm text-pending">
                        Para enviar pelo painel, configure o CNPJ da Moduhub no servidor (<code>SIGNATURES_PLATFORM_CNPJ</code>). Só entra certificado
                        desse CNPJ.
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-4 rounded-lg border bg-background/60 p-4">
                        <p className="text-sm font-medium">{current ? 'Substituir certificado' : 'Enviar certificado'}</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="certificate">Arquivo .pfx</Label>
                                <Input
                                    ref={fileInput}
                                    id="certificate"
                                    type="file"
                                    accept=".pfx,.p12,application/x-pkcs12"
                                    onChange={(e) => upload.setData('certificate', e.target.files?.[0] ?? null)}
                                    aria-invalid={Boolean(upload.errors.certificate)}
                                    required
                                />
                                <InputError message={upload.errors.certificate} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Senha do certificado</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="off"
                                    value={upload.data.password}
                                    onChange={(e) => upload.setData('password', e.target.value)}
                                    aria-invalid={Boolean(upload.errors.password)}
                                    required
                                />
                                <InputError message={upload.errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">Senha da sua conta</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={upload.data.current_password}
                                    onChange={(e) => upload.setData('current_password', e.target.value)}
                                    aria-invalid={Boolean(upload.errors.current_password)}
                                    required
                                />
                                <InputError message={upload.errors.current_password} />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Só aceitamos e-CNPJ da ICP-Brasil do CNPJ {formatCnpj(platformCnpj)} (matriz ou filial). Toda troca avisa por e-mail quem recebe os alertas do
                            certificado da plataforma.
                        </p>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={upload.processing}>
                                {upload.processing ? <Spinner /> : <Upload aria-hidden="true" />}
                                {current ? 'Substituir' : 'Enviar certificado'}
                            </Button>
                        </div>
                    </form>
                )}

                {current && (
                    <div className="flex flex-col gap-3 rounded-lg border border-destructive/30 p-4">
                        <p className="text-sm font-medium">Remover do painel</p>
                        <p className="text-sm text-muted-foreground">
                            {hasServerCertificate
                                ? 'O selo volta a usar o certificado configurado no servidor.'
                                : 'Não há certificado no servidor: os próximos documentos concluídos saem sem o selo ICP-Brasil.'}
                        </p>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="remove_current_password">Senha da sua conta</Label>
                                <Input
                                    id="remove_current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={removal.data.current_password}
                                    onChange={(e) => removal.setData('current_password', e.target.value)}
                                    aria-invalid={Boolean(removal.errors.current_password)}
                                />
                                <InputError message={removal.errors.current_password} />
                            </div>
                            <ConfirmAction
                                trigger={
                                    <Button type="button" variant="outline" className="text-destructive hover:text-destructive sm:mt-6" disabled={removal.processing}>
                                        Remover certificado
                                    </Button>
                                }
                                title="Remover o certificado da plataforma?"
                                description={
                                    hasServerCertificate
                                        ? 'O selo volta a usar o certificado do servidor.'
                                        : 'Os documentos concluídos passam a sair sem o selo ICP-Brasil até um novo certificado ser enviado.'
                                }
                                confirmLabel="Remover"
                                destructive
                                onConfirm={remove}
                            />
                        </div>
                    </div>
                )}
            </FormSection>

            <Separator />
            <CertificateHistory entries={history} />
        </AppLayout>
    );
}
