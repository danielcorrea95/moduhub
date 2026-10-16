import { CopyButton } from '@/components/copy-button';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { ConfirmAction } from '@/components/confirm-action';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookOpen, KeyRound, Plus, ShieldAlert, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

type Environment = 'live' | 'test';

interface ApiKeyRow {
    id: string;
    name: string;
    prefix: string;
    last_four: string;
    environment: Environment;
    scopes: string[] | null;
    last_used_at: string | null;
    expires_at: string | null;
    revoked_at: string | null;
    created_at: string;
    active: boolean;
}

interface Reveal {
    id: string;
    name: string;
    plaintext: string;
}

interface Props {
    apiKeys: ApiKeyRow[];
    scopes: Record<string, string>;
    reveal: Reveal | null;
    [key: string]: unknown;
}

interface CreateKeyForm {
    name: string;
    environment: Environment;
    scopes: string[];
    expires_at: string;
}

const ENVIRONMENT_LABEL: Record<Environment, string> = {
    live: 'Produção',
    test: 'Teste',
};

function keyState(k: ApiKeyRow): { label: string; className: string } {
    if (k.active) return { label: 'Ativa', className: 'bg-signed-soft text-signed' };
    if (k.revoked_at) return { label: 'Revogada', className: 'bg-rejected-soft text-rejected' };
    return { label: 'Expirada', className: 'bg-idle-soft text-idle' };
}

export default function ApiKeysIndex({ apiKeys, scopes, reveal }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<CreateKeyForm>({
        name: '',
        environment: 'live',
        scopes: [],
        expires_at: '',
    });

    const [revealOpen, setRevealOpen] = useState(Boolean(reveal));

    useEffect(() => {
        setRevealOpen(Boolean(reveal));
    }, [reveal]);

    const toggleScope = (scope: string, checked: boolean) =>
        setData('scopes', checked ? [...data.scopes, scope] : data.scopes.filter((s) => s !== scope));

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('api-keys.store'), { onSuccess: () => reset() });
    };

    const revoke = (id: string) => router.delete(route('api-keys.destroy', id), { preserveScroll: true });

    const scopesError = errors.scopes ?? Object.entries(errors).find(([k]) => k.startsWith('scopes.'))?.[1];

    return (
        <AppLayout breadcrumbs={[{ title: 'Integrações' }, { title: 'Chaves de API' }]}>
            <Head title="Chaves de API" />

            <PageHeader
                title="Chaves de API"
                description="Permitem que seus sistemas usem a API do Moduhub em nome deste workspace, por exemplo para enviar documentos para assinatura."
                actions={
                    <Button asChild variant="outline">
                        <Link href={route('docs.api')}>
                            <BookOpen /> Documentação da API
                        </Link>
                    </Button>
                }
            />

            {revealOpen && reveal && (
                <section
                    aria-labelledby="reveal-title"
                    className="rounded-xl border border-pending/30 bg-pending-soft p-5 md:p-6"
                >
                    <div className="flex items-start gap-3">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-pending" aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <h2 id="reveal-title" className="font-semibold text-foreground">
                                Copie a chave “{reveal.name}” agora
                            </h2>
                            <p className="mt-1 text-sm text-foreground/80">
                                Esta é a única vez que a chave completa aparece. Guarde-a em um cofre de senhas ou nas
                                variáveis de ambiente do seu sistema.
                            </p>
                            <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <code className="min-w-0 flex-1 rounded-md border bg-card px-3 py-2 font-mono text-sm break-all text-foreground select-all">
                                    {reveal.plaintext}
                                </code>
                                <CopyButton value={reveal.plaintext} variant="default" size="default" label="Copiar chave" copiedLabel="Chave copiada" />
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="-mt-1 -mr-2 shrink-0"
                            onClick={() => setRevealOpen(false)}
                            aria-label="Fechar aviso da chave"
                        >
                            <X />
                        </Button>
                    </div>
                </section>
            )}

            <section className="rounded-xl border bg-card p-5 md:p-6">
                <h2 className="text-base font-medium">Criar chave</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Dê só as permissões de que a integração precisa. Você pode revogar a chave a qualquer momento.
                </p>

                <form onSubmit={submit} className="mt-5 flex flex-col gap-5">
                    <div className="grid gap-4 sm:grid-cols-[1fr_12rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nome</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Ex.: ERP de produção"
                                aria-invalid={Boolean(errors.name)}
                                required
                            />
                            <p className="text-sm text-muted-foreground">Para você reconhecer onde a chave é usada.</p>
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor="environment">Ambiente</Label>
                            <Select
                                value={data.environment}
                                onValueChange={(v) => setData('environment', v as Environment)}
                            >
                                <SelectTrigger id="environment" aria-invalid={Boolean(errors.environment)}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="live">{ENVIRONMENT_LABEL.live}</SelectItem>
                                    <SelectItem value="test">{ENVIRONMENT_LABEL.test}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.environment} />
                        </div>
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Permissões</legend>
                        <div className="mt-2 divide-y rounded-lg border">
                            {Object.entries(scopes).map(([scope, label]) => {
                                const id = `scope-${scope}`;
                                return (
                                    <label
                                        key={scope}
                                        htmlFor={id}
                                        className="flex cursor-pointer items-start gap-3 px-3.5 py-3 hover:bg-muted/40"
                                    >
                                        <Checkbox
                                            id={id}
                                            className="mt-0.5"
                                            checked={data.scopes.includes(scope)}
                                            onCheckedChange={(c) => toggleScope(scope, c === true)}
                                        />
                                        <span className="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                            <span className="text-sm">{label}</span>
                                            <code className="font-mono text-xs text-muted-foreground">{scope}</code>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <InputError message={scopesError} />
                    </fieldset>

                    <div className="grid gap-2 sm:max-w-xs">
                        <Label htmlFor="expires_at">
                            Validade <span className="font-normal text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input
                            id="expires_at"
                            type="datetime-local"
                            value={data.expires_at}
                            onChange={(e) => setData('expires_at', e.target.value)}
                            aria-invalid={Boolean(errors.expires_at)}
                        />
                        <p className="text-sm text-muted-foreground">Sem data, a chave vale até ser revogada.</p>
                        <InputError message={errors.expires_at} />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                            Criar chave
                        </Button>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <h2 className="border-b px-5 py-3.5 text-base font-medium md:px-6">Chaves criadas</h2>
                {apiKeys.length === 0 ? (
                    <EmptyState
                        icon={KeyRound}
                        title="Nenhuma chave ainda"
                        description="Crie uma chave acima para conectar seu sistema ao Moduhub."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-5 md:pl-6">Nome</TableHead>
                                    <TableHead>Chave</TableHead>
                                    <TableHead>Permissões</TableHead>
                                    <TableHead>Último uso</TableHead>
                                    <TableHead>Situação</TableHead>
                                    <TableHead className="pr-5 md:pr-6">
                                        <span className="sr-only">Ações</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {apiKeys.map((k) => {
                                    const state = keyState(k);
                                    return (
                                        <TableRow key={k.id} className={cn(!k.active && 'text-muted-foreground')}>
                                            <TableCell className="pl-5 align-top md:pl-6">
                                                <p className={cn('font-medium', k.active && 'text-foreground')}>{k.name}</p>
                                                <p className="mt-0.5 text-sm text-muted-foreground">
                                                    {ENVIRONMENT_LABEL[k.environment]}
                                                    {k.expires_at && <>, expira em {formatDate(k.expires_at)}</>}
                                                </p>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <code className="font-mono text-sm whitespace-nowrap">
                                                    {k.prefix}…{k.last_four}
                                                </code>
                                            </TableCell>
                                            <TableCell className="max-w-64 align-top">
                                                <ul className="flex flex-col gap-0.5 text-sm">
                                                    {(k.scopes ?? []).map((s) => (
                                                        <li key={s} title={s}>
                                                            {scopes[s] ?? <code className="font-mono text-xs">{s}</code>}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </TableCell>
                                            <TableCell className="align-top text-sm whitespace-nowrap">
                                                {k.last_used_at ? formatDateTime(k.last_used_at) : 'Nunca usada'}
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <span
                                                    className={cn(
                                                        'inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                                                        state.className,
                                                    )}
                                                >
                                                    {state.label}
                                                </span>
                                            </TableCell>
                                            <TableCell className="pr-5 text-right align-top md:pr-6">
                                                {k.active && (
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                            >
                                                                Revogar
                                                            </Button>
                                                        }
                                                        title={`Revogar a chave “${k.name}”?`}
                                                        description="Toda requisição feita com esta chave passa a ser recusada imediatamente. Os sistemas que a usam param de funcionar até receberem uma chave nova. Não dá para desfazer."
                                                        confirmLabel="Revogar chave"
                                                        destructive
                                                        onConfirm={() => revoke(k.id)}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}
