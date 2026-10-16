import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { formatDate } from '@/lib/utils';
import type { TenantRole } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface InvitationPayload {
    token: string;
    email: string;
    role: TenantRole | string;
    tenant_name: string;
    expires_at: string | null;
}

type Props =
    | { invitation: InvitationPayload; needs_account: true; register_url: string; login_url: string; [key: string]: unknown }
    | { invitation: InvitationPayload; needs_account: false; [key: string]: unknown };

const ROLE_LABELS: Record<string, string> = {
    owner: 'Proprietário',
    admin: 'Administrador',
    member: 'Membro',
};

export default function InvitationShow(props: Props) {
    const { invitation } = props;
    const [accepting, setAccepting] = useState(false);

    const accept = () => {
        router.post(route('invitations.accept', invitation.token), undefined, {
            onStart: () => setAccepting(true),
            onFinish: () => setAccepting(false),
        });
    };

    return (
        <AuthLayout
            title={`Convite para ${invitation.tenant_name}`}
            description="Você foi convidado para participar deste workspace no Moduhub."
        >
            <Head title="Convite" />

            <dl className="space-y-3 rounded-lg bg-muted px-4 py-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">Convite enviado para</dt>
                    <dd className="font-medium break-all">{invitation.email}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Sua função</dt>
                    <dd className="font-medium">{ROLE_LABELS[invitation.role] ?? invitation.role}</dd>
                </div>
                {invitation.expires_at && (
                    <div>
                        <dt className="text-muted-foreground">Válido até</dt>
                        <dd className="font-medium">{formatDate(invitation.expires_at)}</dd>
                    </div>
                )}
            </dl>

            <div className="mt-6 space-y-2">
                {props.needs_account ? (
                    <>
                        <Button asChild size="lg" className="w-full">
                            <Link href={props.register_url}>Criar conta e aceitar</Link>
                        </Button>
                        <Button asChild size="lg" variant="outline" className="w-full">
                            <Link href={props.login_url}>Já tenho conta, quero entrar</Link>
                        </Button>
                    </>
                ) : (
                    <Button type="button" size="lg" className="w-full" onClick={accept} disabled={accepting}>
                        {accepting && <Spinner />}
                        Aceitar e entrar no workspace
                    </Button>
                )}
            </div>
        </AuthLayout>
    );
}
