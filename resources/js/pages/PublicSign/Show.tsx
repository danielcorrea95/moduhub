import InputError from '@/components/input-error';
import SignaturePad from '@/components/signature-pad';
import TypedSignature from '@/components/typed-signature';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout, { type PublicTenant } from '@/layouts/public-layout';
import { cn, formatDateTime } from '@/lib/utils';
import type { Flash } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Clock,
    ExternalLink,
    FileText,
    Mail,
    PenLine,
    ShieldCheck,
    TriangleAlert,
    Type,
} from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface PublicSigner {
    id: number | string;
    name: string;
    email: string;
    cpf: string | null;
    order: number;
    status: string;
    auth_method: 'email' | 'sms' | 'email_and_sms' | 'icp' | string;
    signed_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
}

interface PublicDocument {
    id: string;
    name: string;
    status: string;
    sign_order: 'parallel' | 'sequential';
    message: string | null;
    page_count: number | null;
    file_hash: string | null;
    expires_at: string | null;
    sent_at: string | null;
    completed_at: string | null;
}

interface Props {
    signer: PublicSigner;
    document: PublicDocument;
    tenant: PublicTenant | null;
    waiting_predecessors: number;
    pdf_url: string;
    code_sent: boolean;
    [key: string]: unknown;
}

type SignatureMode = 'typed' | 'drawn';

interface SignFormData {
    code: string;
    accepted: boolean;
    signature_image: string | null;
}

interface IcpFormData {
    certificate: File | null;
    password: string;
    accepted: boolean;
}

// O token não vem nas props; a URL pública é /sign/{token}.
function signerToken(): string {
    return window.location.pathname.split('/')[2];
}

export default function PublicSignShow({
    signer,
    document,
    tenant,
    waiting_predecessors,
    pdf_url,
    code_sent,
}: Props) {
    const { flash, errors } = usePage<{ flash?: Flash; errors: Record<string, string | undefined> }>().props;
    const [showReject, setShowReject] = useState(false);
    const [signatureMode, setSignatureMode] = useState<SignatureMode>('typed');

    const codeForm = useForm({});
    const signForm = useForm<SignFormData>({ code: '', accepted: false, signature_image: null });
    const icpForm = useForm<IcpFormData>({ certificate: null, password: '', accepted: false });
    const rejectForm = useForm<{ reason: string }>({ reason: '' });

    const isIcpSigner = signer.auth_method === 'icp';
    const sequentialBlocked = document.sign_order === 'sequential' && waiting_predecessors > 0;
    const issuer = tenant?.name ?? 'O emissor';
    // Erros do serviço que não pertencem a um campo (prazo, documento indisponível, fora da vez).
    const generalError = errors.document ?? errors.signer;

    const setSignatureImage = (dataUrl: string | null) => signForm.setData('signature_image', dataUrl);

    const switchMode = (mode: SignatureMode) => {
        if (mode === signatureMode) return;
        setSignatureMode(mode);
        signForm.setData('signature_image', null);
    };

    const sendCode = () => {
        codeForm.post(route('public-sign.send-code', { token: signerToken() }), {
            preserveScroll: true,
        });
    };

    const submitSign = (e: FormEvent) => {
        e.preventDefault();
        signForm.post(route('public-sign.sign', { token: signerToken() }));
    };

    const submitIcp = (e: FormEvent) => {
        e.preventDefault();
        icpForm.post(route('public-sign.sign-icp', { token: signerToken() }), {
            forceFormData: true,
        });
    };

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(route('public-sign.reject', { token: signerToken() }), {
            preserveScroll: true,
            onSuccess: () => setShowReject(false),
        });
    };

    const signMissing: string[] = [];
    if (signForm.data.code.length !== 6) signMissing.push('digite o código de 6 dígitos');
    if (!signForm.data.accepted) signMissing.push('marque a caixa de concordância');

    const icpMissing: string[] = [];
    if (!icpForm.data.certificate) icpMissing.push('escolha o arquivo do certificado');
    if (!icpForm.data.password) icpMissing.push('digite a senha');
    if (!icpForm.data.accepted) icpMissing.push('marque a caixa de concordância');

    return (
        <PublicLayout
            title={`Assinar ${document.name}`}
            tenant={tenant}
            flash={flash}
            footer="A trilha de auditoria e o carimbo de verificação ficam visíveis no próprio PDF."
        >
            <div className="space-y-6">
                <header className="space-y-3">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance break-words">
                        {document.name}
                    </h1>
                    <p className="text-muted-foreground">
                        {issuer} pediu a sua assinatura neste documento.
                    </p>
                    <ul className="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-muted-foreground">
                        {document.page_count !== null && (
                            <li className="flex items-center gap-1.5">
                                <FileText className="size-4" aria-hidden="true" />
                                {document.page_count} {document.page_count === 1 ? 'página' : 'páginas'}
                            </li>
                        )}
                        <li className="flex items-center gap-1.5">
                            <PenLine className="size-4" aria-hidden="true" />
                            {document.sign_order === 'parallel'
                                ? 'Cada pessoa assina quando puder'
                                : 'Assinaturas em ordem definida'}
                        </li>
                        {document.expires_at && (
                            <li className="flex items-center gap-1.5">
                                <CalendarClock className="size-4" aria-hidden="true" />
                                Assine até {formatDateTime(document.expires_at)}
                            </li>
                        )}
                    </ul>
                </header>

                {document.message && (
                    <section className="rounded-xl border bg-card p-4 sm:p-5">
                        <h2 className="text-sm font-medium">Mensagem de {issuer}</h2>
                        <p className="mt-1.5 whitespace-pre-line text-sm text-foreground/80">{document.message}</p>
                    </section>
                )}

                <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">
                    <section className="rounded-xl border bg-card lg:col-span-3" aria-labelledby="step-read">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                            <StepTitle id="step-read" number={1}>
                                Leia o documento
                            </StepTitle>
                            <Button asChild variant="outline" size="sm">
                                <a href={pdf_url} target="_blank" rel="noopener noreferrer">
                                    <ExternalLink /> Abrir em tela cheia
                                </a>
                            </Button>
                        </div>
                        <iframe
                            src={pdf_url}
                            title="Pré-visualização do documento"
                            className="block h-[60svh] w-full rounded-b-xl lg:h-[75vh]"
                        />
                    </section>

                    <div className="space-y-4 lg:sticky lg:top-6 lg:col-span-2">
                        <section className="rounded-xl border bg-card shadow-xs" aria-label="Assinatura">
                            <div className="border-b px-4 py-4 sm:px-5">
                                <p className="text-sm text-muted-foreground">Assinando como</p>
                                <p className="mt-0.5 font-semibold break-words">{signer.name}</p>
                                <p className="text-sm break-all text-muted-foreground">{signer.email}</p>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {document.sign_order === 'sequential'
                                        ? `Você é a pessoa nº ${signer.order} na ordem de assinatura.`
                                        : `Signatário nº ${signer.order}.`}
                                    {isIcpSigner && ' Assinatura com certificado digital ICP-Brasil.'}
                                </p>
                            </div>

                            <div className="space-y-6 px-4 py-5 sm:px-5">
                                {generalError && (
                                    <div
                                        role="alert"
                                        className="flex gap-2.5 rounded-lg bg-rejected-soft px-3 py-2.5 text-sm text-rejected"
                                    >
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                        <p>{generalError}</p>
                                    </div>
                                )}

                                {sequentialBlocked ? (
                                    <div className="flex gap-3 rounded-lg bg-pending-soft p-4 text-sm text-pending">
                                        <Clock className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                                        <div>
                                            <p className="font-semibold">Ainda não é a sua vez</p>
                                            <p className="mt-1">
                                                {waiting_predecessors === 1
                                                    ? 'Falta 1 pessoa assinar antes de você.'
                                                    : `Faltam ${waiting_predecessors} pessoas assinarem antes de você.`}{' '}
                                                Volte a esta página mais tarde, pelo mesmo link.
                                            </p>
                                        </div>
                                    </div>
                                ) : isIcpSigner ? (
                                    <form onSubmit={submitIcp} className="space-y-6">
                                        <div className="space-y-3">
                                            <StepTitle number={2}>Envie seu certificado</StepTitle>
                                            <p className="text-sm text-muted-foreground">
                                                Use o seu certificado A1 (arquivo .pfx ou .p12) e a senha dele. O
                                                certificado é usado só para esta assinatura e não fica guardado nos
                                                nossos servidores.
                                            </p>
                                            <div className="space-y-2">
                                                <Label htmlFor="certificate">Arquivo do certificado</Label>
                                                <input
                                                    id="certificate"
                                                    type="file"
                                                    accept=".pfx,.p12,application/x-pkcs12"
                                                    onChange={(e) =>
                                                        icpForm.setData('certificate', e.target.files?.[0] ?? null)
                                                    }
                                                    aria-invalid={Boolean(icpForm.errors.certificate)}
                                                    className={cn(
                                                        'block w-full rounded-md border border-input bg-card text-sm text-foreground',
                                                        'file:mr-3 file:h-10 file:cursor-pointer file:border-0 file:border-r file:border-input file:bg-accent file:px-3 file:text-sm file:font-medium file:text-accent-foreground',
                                                        'focus-visible:outline-2 focus-visible:outline-offset-2 aria-invalid:border-destructive',
                                                    )}
                                                />
                                                <InputError message={icpForm.errors.certificate} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="pfxpass">Senha do certificado</Label>
                                                <Input
                                                    id="pfxpass"
                                                    type="password"
                                                    value={icpForm.data.password}
                                                    onChange={(e) => icpForm.setData('password', e.target.value)}
                                                    autoComplete="off"
                                                    aria-invalid={Boolean(icpForm.errors.password)}
                                                    className="h-11 bg-card"
                                                />
                                                <InputError message={icpForm.errors.password} />
                                            </div>
                                        </div>

                                        <div className="space-y-3">
                                            <StepTitle number={3}>Confirme e assine</StepTitle>
                                            <Consent
                                                id="icp-accepted"
                                                checked={icpForm.data.accepted}
                                                onChange={(v) => icpForm.setData('accepted', v)}
                                                error={icpForm.errors.accepted}
                                            >
                                                Li o documento e autorizo a aplicação da minha assinatura digital
                                                ICP-Brasil neste PDF.
                                            </Consent>
                                            <SubmitButton
                                                processing={icpForm.processing}
                                                disabled={icpMissing.length > 0}
                                                processingLabel="Processando certificado..."
                                                icon={<ShieldCheck />}
                                            />
                                            <MissingHint missing={icpForm.processing ? [] : icpMissing} />
                                        </div>
                                    </form>
                                ) : !code_sent ? (
                                    <div className="space-y-3">
                                        <StepTitle number={2}>Confirme que é você</StepTitle>
                                        <p className="text-sm text-muted-foreground">
                                            Vamos enviar um código de 6 dígitos para o seu e-mail{' '}
                                            <span className="font-medium break-all text-foreground">{signer.email}</span>.
                                        </p>
                                        <Button
                                            type="button"
                                            size="lg"
                                            onClick={sendCode}
                                            disabled={codeForm.processing}
                                            className="h-11 w-full"
                                        >
                                            {codeForm.processing ? <Spinner /> : <Mail />}
                                            {codeForm.processing ? 'Enviando...' : 'Enviar código para meu e-mail'}
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            Depois de digitar o código, você escolhe sua assinatura e conclui.
                                        </p>
                                    </div>
                                ) : (
                                    <form onSubmit={submitSign} className="space-y-6">
                                        <div className="space-y-3">
                                            <StepTitle number={2}>Confirme que é você</StepTitle>
                                            <div className="space-y-2">
                                                <Label htmlFor="code">Código recebido por e-mail</Label>
                                                <Input
                                                    id="code"
                                                    value={signForm.data.code}
                                                    onChange={(e) =>
                                                        signForm.setData('code', e.target.value.replace(/\D/g, ''))
                                                    }
                                                    inputMode="numeric"
                                                    autoComplete="one-time-code"
                                                    maxLength={6}
                                                    placeholder="000000"
                                                    required
                                                    aria-invalid={Boolean(signForm.errors.code)}
                                                    aria-describedby="code-help"
                                                    className="h-12 bg-card text-center font-mono text-xl tracking-[0.3em] md:text-xl"
                                                />
                                                <InputError message={signForm.errors.code} />
                                                <div
                                                    id="code-help"
                                                    className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                                                >
                                                    <p className="text-xs text-muted-foreground">
                                                        Não chegou? Olhe também a caixa de spam.
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="link"
                                                        size="sm"
                                                        onClick={sendCode}
                                                        disabled={codeForm.processing}
                                                        className="h-auto px-0"
                                                    >
                                                        {codeForm.processing ? 'Reenviando...' : 'Reenviar código'}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-3">
                                            <StepTitle number={3}>Escolha sua assinatura</StepTitle>
                                            <Tabs
                                                value={signatureMode}
                                                onValueChange={(v) => switchMode(v as SignatureMode)}
                                            >
                                                <TabsList className="grid w-full grid-cols-2">
                                                    <TabsTrigger value="typed" className="gap-1.5">
                                                        <Type className="size-4" aria-hidden="true" /> Digitar
                                                    </TabsTrigger>
                                                    <TabsTrigger value="drawn" className="gap-1.5">
                                                        <PenLine className="size-4" aria-hidden="true" /> Desenhar
                                                    </TabsTrigger>
                                                </TabsList>
                                            </Tabs>
                                            {/* Montagem condicional de propósito: trocar o modo descarta a imagem anterior. */}
                                            {signatureMode === 'typed' ? (
                                                <TypedSignature
                                                    defaultName={signer.name}
                                                    disabled={signForm.processing}
                                                    onChange={setSignatureImage}
                                                />
                                            ) : (
                                                <SignaturePad
                                                    disabled={signForm.processing}
                                                    onChange={setSignatureImage}
                                                />
                                            )}
                                            <InputError message={signForm.errors.signature_image} />
                                        </div>

                                        <div className="space-y-3">
                                            <StepTitle number={4}>Confirme e assine</StepTitle>
                                            <Consent
                                                id="accepted"
                                                checked={signForm.data.accepted}
                                                onChange={(v) => signForm.setData('accepted', v)}
                                                error={signForm.errors.accepted}
                                            >
                                                Li o documento, concordo com seus termos e autorizo meu nome,
                                                e-mail e IP a serem registrados como evidência da minha
                                                assinatura eletrônica.
                                            </Consent>
                                            <SubmitButton
                                                processing={signForm.processing}
                                                disabled={signMissing.length > 0}
                                                processingLabel="Assinando..."
                                                icon={<PenLine />}
                                            />
                                            <MissingHint missing={signForm.processing ? [] : signMissing} />
                                        </div>
                                    </form>
                                )}
                            </div>
                        </section>

                        {!sequentialBlocked && (
                            <RejectDialog
                                open={showReject}
                                onOpenChange={setShowReject}
                                reason={rejectForm.data.reason}
                                onReasonChange={(v) => rejectForm.setData('reason', v)}
                                error={rejectForm.errors.reason}
                                processing={rejectForm.processing}
                                onSubmit={submitReject}
                            />
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function StepTitle({ number, id, children }: { number: number; id?: string; children: ReactNode }) {
    return (
        <h2 id={id} className="flex items-center gap-2.5 font-semibold">
            <span
                className="flex size-6 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground tabular-nums"
                aria-hidden="true"
            >
                {number}
            </span>
            <span>
                <span className="sr-only">Passo {number}: </span>
                {children}
            </span>
        </h2>
    );
}

function Consent({
    id,
    checked,
    onChange,
    error,
    children,
}: {
    id: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <div
                className={cn(
                    'flex items-start gap-3 rounded-lg border p-3 transition-colors',
                    checked ? 'border-primary/40 bg-accent/60' : 'bg-card',
                )}
            >
                <Checkbox
                    id={id}
                    checked={checked}
                    onCheckedChange={(v) => onChange(v === true)}
                    aria-invalid={Boolean(error)}
                    className="mt-0.5 size-5"
                />
                <Label htmlFor={id} className="text-sm leading-relaxed font-normal">
                    {children}
                </Label>
            </div>
            <InputError message={error} />
        </div>
    );
}

function SubmitButton({
    processing,
    disabled,
    processingLabel,
    icon,
}: {
    processing: boolean;
    disabled: boolean;
    processingLabel: string;
    icon: ReactNode;
}) {
    return (
        <Button type="submit" size="lg" disabled={processing || disabled} className="h-12 w-full text-base">
            {processing ? <Spinner /> : icon}
            {processing ? processingLabel : 'Assinar documento'}
        </Button>
    );
}

function MissingHint({ missing }: { missing: string[] }) {
    if (missing.length === 0) return null;
    const text =
        missing.length === 1 ? missing[0] : `${missing.slice(0, -1).join(', ')} e ${missing[missing.length - 1]}`;
    return (
        <p className="text-center text-xs text-muted-foreground" aria-live="polite">
            Para liberar o botão, {text}.
        </p>
    );
}

function RejectDialog({
    open,
    onOpenChange,
    reason,
    onReasonChange,
    error,
    processing,
    onSubmit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reason: string;
    onReasonChange: (value: string) => void;
    error?: string;
    processing: boolean;
    onSubmit: (e: FormEvent) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <div className="flex flex-col items-center gap-1 px-2 text-center">
                <p className="text-sm text-muted-foreground">Não concorda com o documento?</p>
                <DialogTrigger asChild>
                    <Button variant="ghost" className="text-destructive hover:bg-rejected-soft hover:text-destructive">
                        Recusar documento
                    </Button>
                </DialogTrigger>
            </div>
            <DialogContent>
                <form onSubmit={onSubmit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>Recusar este documento?</DialogTitle>
                        <DialogDescription>
                            Ao recusar, o documento fica marcado como recusado para todos os envolvidos e ninguém mais
                            poderá assiná-lo. Isso não pode ser desfeito.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="reason">Por que você está recusando?</Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => onReasonChange(e.target.value)}
                            rows={4}
                            maxLength={1000}
                            required
                            aria-invalid={Boolean(error)}
                            className="bg-card text-base md:text-sm"
                            placeholder="Ex.: o valor do contrato está diferente do combinado."
                        />
                        <InputError message={error} />
                    </div>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Voltar
                            </Button>
                        </DialogClose>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing && <Spinner />}
                            Recusar documento
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
