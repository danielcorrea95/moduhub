import { CopyButton } from '@/components/copy-button';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { ConfirmAction } from '@/components/confirm-action';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { ROLE_LABEL } from '@/components/workspace-switcher';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import type { SharedData, TenantRole } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CircleAlert, Send } from 'lucide-react';
import type { FormEvent } from 'react';

type AssignableRole = Exclude<TenantRole, 'owner'>;

interface Member {
    id: number;
    name: string;
    email: string;
    role: TenantRole;
    joined_at: string | null;
}

interface Invitation {
    id: string;
    email: string;
    role: AssignableRole;
    expires_at: string;
    invite_url: string;
}

interface Props {
    members: Member[];
    invitations: Invitation[];
    can: { manage: boolean };
    roles: AssignableRole[];
    [key: string]: unknown;
}

interface InviteForm {
    email: string;
    role: AssignableRole;
}

const ROLE_HINT: Record<TenantRole, string> = {
    owner: 'Controle total, inclusive do workspace.',
    admin: 'Gerencia membros, configurações e integrações.',
    member: 'Envia e acompanha documentos.',
};

export default function Members({ members, invitations, can, roles }: Props) {
    const { auth, errors: pageErrors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const getInitials = useInitials();

    const { data, setData, post, processing, errors, reset } = useForm<InviteForm>({
        email: '',
        role: 'member',
    });

    const invite = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('workspace.invitations.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const revoke = (id: Invitation['id']) =>
        router.delete(route('workspace.invitations.destroy', id), { preserveScroll: true });

    const remove = (member: Member) =>
        router.delete(route('workspace.members.destroy', member.id), { preserveScroll: true });

    const changeRole = (member: Member, role: AssignableRole) =>
        router.patch(route('workspace.members.update', member.id), { role }, { preserveScroll: true });

    // Erros de remover/alterar membro voltam pelo redirect, fora do useForm do convite.
    const memberError = pageErrors.user ?? (pageErrors.role && !errors.role ? pageErrors.role : undefined);

    return (
        <AppLayout breadcrumbs={[{ title: 'Workspace' }, { title: 'Membros' }]}>
            <Head title="Membros" />

            <PageHeader
                title="Membros"
                description="Quem tem acesso a este workspace e o que cada pessoa pode fazer."
            />

            {can.manage && (
                <section className="rounded-xl border bg-card p-5 md:p-6">
                    <h2 className="text-base font-medium">Convidar pessoa</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Enviamos um convite por e-mail. O link também fica disponível aqui para você copiar e mandar por outro canal.
                    </p>

                    <form
                        onSubmit={invite}
                        className="mt-5 grid gap-4 sm:grid-cols-[1fr_12rem_auto] sm:items-start"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="email">E-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="nome@empresa.com.br"
                                aria-invalid={Boolean(errors.email)}
                                required
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Função</Label>
                            <Select
                                value={data.role}
                                onValueChange={(v) => setData('role', v as AssignableRole)}
                            >
                                <SelectTrigger id="role" aria-invalid={Boolean(errors.role)}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (
                                        <SelectItem key={r} value={r}>
                                            {ROLE_LABEL[r]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.role} />
                        </div>

                        <Button type="submit" disabled={processing} className="sm:mt-[1.375rem]">
                            {processing ? <Spinner /> : <Send aria-hidden="true" />}
                            Enviar convite
                        </Button>
                    </form>
                    <p className="mt-3 text-sm text-muted-foreground">
                        {ROLE_LABEL[data.role]}: {ROLE_HINT[data.role].toLowerCase()}
                    </p>
                </section>
            )}

            {invitations.length > 0 && (
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <header className="border-b px-5 py-3.5 md:px-6">
                        <h2 className="text-base font-medium">Convites pendentes</h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Ainda não aceitos. Cada link funciona uma vez, até a data de validade.
                        </p>
                    </header>
                    <ul className="divide-y">
                        {invitations.map((inv) => (
                            <li
                                key={inv.id}
                                className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{inv.email}</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {ROLE_LABEL[inv.role]}, válido até {formatDate(inv.expires_at)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <CopyButton
                                        value={inv.invite_url}
                                        label="Copiar link"
                                        copiedLabel="Link copiado"
                                    />
                                    {can.manage && (
                                        <ConfirmAction
                                            trigger={
                                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                    Cancelar convite
                                                </Button>
                                            }
                                            title="Cancelar este convite?"
                                            description={
                                                <>
                                                    O link do convite para <strong className="text-foreground">{inv.email}</strong>{' '}
                                                    deixa de funcionar na hora. Para dar acesso depois, gere um novo convite.
                                                </>
                                            }
                                            confirmLabel="Cancelar convite"
                                            destructive
                                            onConfirm={() => revoke(inv.id)}
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <header className="border-b px-5 py-3.5 md:px-6">
                    <h2 className="text-base font-medium">
                        Membros ativos <span className="font-normal tabular-nums text-muted-foreground">{members.length}</span>
                    </h2>
                </header>

                {memberError && (
                    <div className="border-b px-5 py-3 md:px-6">
                        <Alert variant="destructive" className="border-destructive/30 text-destructive">
                            <CircleAlert aria-hidden="true" />
                            <AlertDescription className="text-destructive">{memberError}</AlertDescription>
                        </Alert>
                    </div>
                )}

                <ul className="divide-y">
                    {members.map((m) => {
                        const isSelf = auth.user?.id === m.id;
                        const editable = can.manage && m.role !== 'owner' && !isSelf;

                        return (
                            <li
                                key={m.id}
                                className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <Avatar className="size-9 shrink-0">
                                        <AvatarFallback className="bg-accent text-sm font-semibold text-primary">
                                            {getInitials(m.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {m.name}
                                            {isSelf && <span className="font-normal text-muted-foreground"> (você)</span>}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">{m.email}</p>
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-2 pl-12 sm:pl-0">
                                    {editable ? (
                                        <>
                                            <Select
                                                value={m.role}
                                                onValueChange={(v) => changeRole(m, v as AssignableRole)}
                                            >
                                                <SelectTrigger
                                                    className="h-8 w-40"
                                                    aria-label={`Função de ${m.name}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((r) => (
                                                        <SelectItem key={r} value={r}>
                                                            {ROLE_LABEL[r]}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        Remover
                                                    </Button>
                                                }
                                                title={`Remover ${m.name} do workspace?`}
                                                description="A pessoa perde o acesso a todos os documentos e configurações deste workspace na hora. Os documentos que ela enviou continuam aqui."
                                                confirmLabel="Remover membro"
                                                destructive
                                                onConfirm={() => remove(m)}
                                            />
                                        </>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">{ROLE_LABEL[m.role]}</span>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </section>
        </AppLayout>
    );
}
