import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm<{ password: string }>({
        password: '',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Confirme sua senha"
            description="Esta é uma área protegida. Digite sua senha para continuar."
        >
            <Head title="Confirmar senha" />

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="password">Senha</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        autoFocus
                        aria-invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                    {processing && <Spinner />}
                    Confirmar senha
                </Button>
            </form>
        </AuthLayout>
    );
}
