import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    status?: string | null;
    canResetPassword: boolean;
    [key: string]: unknown;
}

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function Login({ status, canResetPassword }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Entrar no Moduhub" description="Use o e-mail e a senha da sua conta.">
            <Head title="Entrar" />

            {status && (
                <p className="mb-5 rounded-lg bg-signed-soft px-3 py-2.5 text-sm font-medium text-signed">
                    {status}
                </p>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor="password">Senha</Label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="rounded-sm text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                Esqueceu a senha?
                            </Link>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-2.5">
                    <Checkbox
                        id="remember"
                        name="remember"
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    <Label htmlFor="remember" className="font-normal">
                        Manter conectado neste dispositivo
                    </Label>
                </div>

                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                    {processing && <Spinner />}
                    Entrar
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
                Ainda não tem conta?{' '}
                <Link
                    href={route('register')}
                    className="rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Criar conta
                </Link>
            </p>
        </AuthLayout>
    );
}
