import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { SharedData, User } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, MailWarning } from 'lucide-react';
import type { FormEvent } from 'react';

interface Props {
    mustVerifyEmail: boolean;
    status?: string | null;
    className?: string;
}

interface ProfileForm {
    name: string;
    email: string;
}

export default function UpdateProfileInformationForm({ mustVerifyEmail, status, className }: Props) {
    // A rota de perfil exige autenticação, então o usuário sempre está presente aqui.
    const user = usePage<SharedData>().props.auth.user as User;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<ProfileForm>({
        name: user.name,
        email: user.email,
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={cn('grid gap-6 md:grid-cols-[14rem_1fr] md:gap-10', className)}>
            <header>
                <h2 className="text-base font-medium">Dados pessoais</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Nome e e-mail que aparecem nos documentos que você envia.
                </p>
            </header>

            <form onSubmit={submit} className="flex min-w-0 flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input
                        id="name"
                        value={data.name}
                        required
                        autoComplete="name"
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
                        value={data.email}
                        required
                        autoComplete="username"
                        aria-invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="flex gap-3 rounded-lg bg-pending-soft px-3 py-2.5 text-sm text-pending">
                        <MailWarning className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <div>
                            <p>
                                Seu e-mail ainda não foi confirmado.{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="rounded-sm font-medium underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    Reenviar e-mail de confirmação
                                </Link>
                            </p>
                            {status === 'verification-link-sent' && (
                                <p className="mt-1.5 font-medium text-signed">
                                    Enviamos um novo link de confirmação para o seu e-mail.
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Salvar alterações
                    </Button>
                    {recentlySuccessful && (
                        <p className="flex items-center gap-1.5 text-sm text-signed" role="status">
                            <Check className="size-4" aria-hidden="true" />
                            Alterações salvas
                        </p>
                    )}
                </div>
            </form>
        </section>
    );
}
