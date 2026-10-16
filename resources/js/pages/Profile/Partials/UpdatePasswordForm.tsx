import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useRef, type FormEvent } from 'react';

interface PasswordForm {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export default function UpdatePasswordForm({ className }: { className?: string }) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm<PasswordForm>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={cn('grid gap-6 md:grid-cols-[14rem_1fr] md:gap-10', className)}>
            <header>
                <h2 className="text-base font-medium">Senha</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Use uma senha longa e que você não usa em outros serviços.
                </p>
            </header>

            <form onSubmit={updatePassword} className="flex min-w-0 flex-col gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="current_password">Senha atual</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.current_password)}
                        onChange={(e) => setData('current_password', e.target.value)}
                    />
                    <InputError message={errors.current_password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Nova senha</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirmar nova senha</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password_confirmation)}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Atualizar senha
                    </Button>
                    {recentlySuccessful && (
                        <p className="flex items-center gap-1.5 text-sm text-signed" role="status">
                            <Check className="size-4" aria-hidden="true" />
                            Senha atualizada
                        </p>
                    )}
                </div>
            </form>
        </section>
    );
}
