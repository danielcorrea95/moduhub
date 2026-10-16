import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    status?: string | null;
    [key: string]: unknown;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm<{ email: string }>({
        email: '',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Redefinir senha"
            description="Informe o e-mail da sua conta e enviaremos um link para você escolher uma nova senha."
        >
            <Head title="Esqueci a senha" />

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

                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                    {processing && <Spinner />}
                    Enviar link de redefinição
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
                Lembrou a senha?{' '}
                <Link
                    href={route('login')}
                    className="rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Voltar para o login
                </Link>
            </p>
        </AuthLayout>
    );
}
