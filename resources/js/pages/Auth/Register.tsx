import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { MailCheck, Sparkles } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';

interface Invitation {
    token: string;
    email: string;
    tenant_name: string;
}

interface ChosenPlan {
    key: string;
    name: string;
    price_cents: number;
    trial_days: number;
}

interface Props {
    invitation?: Invitation | null;
    /** Carrinho montado na página de vendas. */
    plans?: ChosenPlan[];
    [key: string]: unknown;
}

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    workspace_name: string;
    invitation_token: string;
    plans: string[];
}

const money = (cents: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

export default function Register({ invitation = null, plans = [] }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: invitation?.email ?? '',
        password: '',
        password_confirmation: '',
        workspace_name: '',
        invitation_token: invitation?.token ?? '',
        plans: plans.map((p) => p.key),
    });

    useEffect(() => {
        if (invitation) {
            setData((prev) => ({
                ...prev,
                email: invitation.email,
                invitation_token: invitation.token,
            }));
        }
        // setData muda de identidade a cada render; só o convite deve disparar a sincronização.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [invitation]);

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Criar conta"
            description={
                invitation
                    ? 'Finalize seu cadastro para aceitar o convite.'
                    : 'Comece a enviar documentos para assinatura em poucos minutos.'
            }
        >
            <Head title="Criar conta" />

            {plans.length > 0 && (
                <div className="mb-5 flex gap-3 rounded-lg border border-primary/20 bg-accent px-3 py-2.5 text-sm text-accent-foreground">
                    <Sparkles className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                        <p>
                            {plans.map((p) => p.name).join(', ')}:{' '}
                            <strong>{money(plans.reduce((sum, p) => sum + p.price_cents, 0))} por mês</strong>.
                        </p>
                        <p className="mt-0.5 opacity-80">
                            Os primeiros {Math.max(...plans.map((p) => p.trial_days))} dias são grátis e não pedimos cartão agora.
                        </p>
                    </div>
                </div>
            )}

            {invitation && (
                <div className="mb-5 flex gap-3 rounded-lg border border-primary/20 bg-accent px-3 py-2.5 text-sm text-accent-foreground">
                    <MailCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        Você foi convidado para entrar em <strong>{invitation.tenant_name}</strong>.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.name)}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        readOnly={Boolean(invitation)}
                        required
                        aria-invalid={Boolean(errors.email)}
                        className={invitation ? 'bg-muted text-muted-foreground' : undefined}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {invitation && (
                        <p className="text-xs text-muted-foreground">
                            O convite foi enviado para este e-mail.
                        </p>
                    )}
                    <InputError message={errors.email} />
                </div>

                {!invitation && (
                    <div className="grid gap-2">
                        <Label htmlFor="workspace_name">Nome do workspace</Label>
                        <Input
                            id="workspace_name"
                            name="workspace_name"
                            value={data.workspace_name}
                            placeholder="Minha Empresa"
                            aria-describedby="workspace_name_hint"
                            aria-invalid={Boolean(errors.workspace_name)}
                            onChange={(e) => setData('workspace_name', e.target.value)}
                        />
                        <p id="workspace_name_hint" className="text-xs text-muted-foreground">
                            Deixe em branco para usar seu nome como padrão.
                        </p>
                        <InputError message={errors.workspace_name} />
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="password">Senha</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirmar senha</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        aria-invalid={Boolean(errors.password_confirmation)}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <InputError message={errors.invitation_token} />

                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                    {processing && <Spinner />}
                    {invitation ? 'Criar conta e aceitar convite' : 'Criar conta'}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
                Já tem conta?{' '}
                <Link
                    href={route('login')}
                    className="rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Entrar
                </Link>
            </p>
        </AuthLayout>
    );
}
