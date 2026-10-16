import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    invitation: { token: string; email: string; role: string; tenant_name: string; expires_at: string | null };
    current_email: string;
    [key: string]: unknown;
}

export default function InvitationMismatch({ invitation, current_email }: Props) {
    const [leaving, setLeaving] = useState(false);

    const logout = () => {
        router.post(route('logout'), undefined, {
            onStart: () => setLeaving(true),
            onFinish: () => setLeaving(false),
        });
    };

    return (
        <AuthLayout title="Este convite é para outro e-mail">
            <Head title="Convite para outro e-mail" />
            <p className="text-sm text-muted-foreground">
                O convite para <span className="font-medium text-foreground">{invitation.tenant_name}</span> foi
                enviado para <span className="font-medium break-all text-foreground">{invitation.email}</span>, mas
                você entrou como <span className="font-medium break-all text-foreground">{current_email}</span>.
            </p>
            <p className="mt-3 text-sm text-muted-foreground">
                Saia desta conta e abra o link do convite de novo usando o e-mail convidado.
            </p>
            <div className="mt-6 space-y-2">
                <Button
                    type="button"
                    size="lg"
                    className="h-auto min-h-10 w-full whitespace-normal py-2"
                    onClick={logout}
                    disabled={leaving}
                >
                    {leaving && <Spinner />}
                    Sair e aceitar com {invitation.email}
                </Button>
                <Button asChild variant="ghost" size="lg" className="w-full">
                    <Link href={route('dashboard')}>Voltar ao painel</Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
