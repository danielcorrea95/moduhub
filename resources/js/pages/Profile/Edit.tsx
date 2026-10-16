import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

interface Props {
    mustVerifyEmail: boolean;
    status?: string | null;
    [key: string]: unknown;
}

export default function Edit({ mustVerifyEmail, status }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Meu perfil' }]} width="narrow">
            <Head title="Meu perfil" />

            <PageHeader
                title="Meu perfil"
                description="Seus dados de acesso valem para todos os workspaces em que você participa."
            />

            <div className="divide-y rounded-xl border bg-card shadow-xs">
                <UpdateProfileInformationForm
                    mustVerifyEmail={mustVerifyEmail}
                    status={status}
                    className="p-5 md:p-6"
                />
                <UpdatePasswordForm className="p-5 md:p-6" />
            </div>

            <DeleteUserForm />
        </AppLayout>
    );
}
