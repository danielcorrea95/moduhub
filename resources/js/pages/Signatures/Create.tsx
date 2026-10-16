import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileUp, X } from 'lucide-react';
import { useRef, type SubmitEvent } from 'react';

type SignOrder = 'parallel' | 'sequential';

interface CreateDocumentForm {
    name: string;
    file: File | null;
    message: string;
    sign_order: SignOrder;
    expires_at: string;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function SignaturesCreate() {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, progress } = useForm<CreateDocumentForm>({
        name: '',
        file: null,
        message: '',
        sign_order: 'parallel',
        expires_at: '',
    });

    const submit = (e: SubmitEvent) => {
        e.preventDefault();
        post(route('signatures.store'), { forceFormData: true });
    };

    const clearFile = () => {
        setData('file', null);
        if (fileInput.current) fileInput.current.value = '';
    };

    return (
        <AppLayout
            width="narrow"
            breadcrumbs={[
                { title: 'Documentos', href: route('signatures.index') },
                { title: 'Novo documento' },
            ]}
        >
            <Head title="Novo documento" />

            <PageHeader
                title="Novo documento"
                description="Suba o PDF que precisa ser assinado. Na próxima tela você adiciona os signatários e envia."
            />

            <form onSubmit={submit} className="space-y-6 rounded-xl border bg-card p-5 md:p-6">
                <div className="grid gap-2">
                    <span className="text-sm font-medium leading-none">Arquivo PDF</span>
                    <label
                        htmlFor="file"
                        className={cn(
                            'flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center transition-colors hover:border-primary/50 hover:bg-accent/40',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            errors.file && 'border-destructive',
                            data.file && 'border-solid border-primary/40 bg-accent/30',
                        )}
                    >
                        <input
                            ref={fileInput}
                            id="file"
                            type="file"
                            accept="application/pdf"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="sr-only"
                            aria-invalid={Boolean(errors.file)}
                            aria-describedby="file-hint"
                            required
                        />
                        <FileUp className="size-6 text-primary" aria-hidden="true" />
                        {data.file ? (
                            <>
                                <span className="max-w-full break-all text-sm font-medium text-foreground">
                                    {data.file.name}
                                </span>
                                <span id="file-hint" className="text-xs text-muted-foreground">
                                    {formatBytes(data.file.size)}. Clique para trocar o arquivo.
                                </span>
                            </>
                        ) : (
                            <>
                                <span className="text-sm font-medium text-foreground">
                                    Clique para escolher o PDF
                                </span>
                                <span id="file-hint" className="text-xs text-muted-foreground">
                                    Somente PDF, até 25 MB.
                                </span>
                            </>
                        )}
                    </label>
                    {data.file && !processing && (
                        <div>
                            <Button type="button" variant="ghost" size="sm" onClick={clearFile}>
                                <X /> Remover arquivo
                            </Button>
                        </div>
                    )}
                    {progress && (
                        <div
                            className="h-1 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-label="Envio do arquivo"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={progress.percentage ?? 0}
                        >
                            <div className="h-full bg-primary" style={{ width: `${progress.percentage ?? 0}%` }} />
                        </div>
                    )}
                    <InputError message={errors.file} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">Nome do documento (opcional)</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Se ficar vazio, usamos o nome do arquivo"
                        aria-invalid={Boolean(errors.name)}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="message">Mensagem aos signatários (opcional)</Label>
                    <Textarea
                        id="message"
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        rows={3}
                        placeholder="Olá, por favor assine o documento anexo."
                        aria-invalid={Boolean(errors.message)}
                    />
                    <InputError message={errors.message} />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="sign_order">Ordem de assinatura</Label>
                        <Select
                            value={data.sign_order}
                            onValueChange={(value) => setData('sign_order', value as SignOrder)}
                        >
                            <SelectTrigger id="sign_order" aria-invalid={Boolean(errors.sign_order)}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="parallel">Paralela, todos ao mesmo tempo</SelectItem>
                                <SelectItem value="sequential">Sequencial, um de cada vez</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.sign_order} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="expires_at">Prazo para assinar (opcional)</Label>
                        <Input
                            id="expires_at"
                            type="datetime-local"
                            value={data.expires_at}
                            onChange={(e) => setData('expires_at', e.target.value)}
                            aria-invalid={Boolean(errors.expires_at)}
                        />
                        <InputError message={errors.expires_at} />
                    </div>
                </div>

                <div className="flex flex-col-reverse gap-2 border-t pt-5 sm:flex-row sm:justify-end">
                    <Button variant="outline" asChild>
                        <Link href={route('signatures.index')}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Criar documento
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
