import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link } from '@inertiajs/react';

export default function InvitationInvalid() {
    return (
        <AuthLayout
            title="Este convite não está mais ativo"
            description="O link pode ter expirado, já ter sido usado ou cancelado pelo administrador."
        >
            <Head title="Convite inválido" />
            <p className="text-sm text-muted-foreground">
                Peça a quem administra o workspace para enviar um novo convite.
            </p>
            <Button asChild variant="outline" size="lg" className="mt-6 w-full">
                <Link href={route('login')}>Ir para o login</Link>
            </Button>
        </AuthLayout>
    );
}
