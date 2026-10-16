import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    status?: string | null;
    [key: string]: unknown;
}

export default function VerifyEmail({ status }: Props) {
    const { post, processing } = useForm({});

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthLayout
            title="Confirme seu e-mail"
            description="Enviamos um link de confirmação para o e-mail do cadastro. Abra a mensagem e clique no link para começar. Se não encontrar, confira a caixa de spam ou peça um novo envio."
        >
            <Head title="Confirmar e-mail" />

            {status === 'verification-link-sent' && (
                <p className="mb-5 rounded-lg bg-signed-soft px-3 py-2.5 text-sm font-medium text-signed">
                    Enviamos um novo link de confirmação para o e-mail informado no cadastro.
                </p>
            )}

            <form onSubmit={submit} className="flex flex-col gap-3">
                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                    {processing && <Spinner />}
                    Reenviar e-mail de confirmação
                </Button>
                <Button asChild variant="ghost" className="w-full text-muted-foreground">
                    <Link href={route('logout')} method="post" as="button">
                        Sair
                    </Link>
                </Button>
            </form>
        </AuthLayout>
    );
}
