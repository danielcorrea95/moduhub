import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

export default function DeleteUserForm({ className }: { className?: string }) {
    const [open, setOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm<{ password: string }>({
        password: '',
    });

    const closeModal = () => {
        setOpen(false);
        clearErrors();
        reset();
    };

    const deleteUser = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <section
            className={cn(
                'flex flex-col gap-4 rounded-xl border border-destructive/25 bg-card p-5 sm:flex-row sm:items-center sm:justify-between md:p-6',
                className,
            )}
        >
            <div className="max-w-prose">
                <h2 className="text-base font-medium">Excluir conta</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Remove sua conta e todos os dados ligados a ela, sem possibilidade de recuperação.
                    Baixe antes os documentos e informações que quiser guardar.
                </p>
            </div>

            <Dialog
                open={open}
                onOpenChange={(next) => (next ? setOpen(true) : closeModal())}
            >
                <DialogTrigger asChild>
                    <Button variant="destructive" className="shrink-0">
                        Excluir conta
                    </Button>
                </DialogTrigger>
                <DialogContent
                    onOpenAutoFocus={(e) => {
                        e.preventDefault();
                        passwordInput.current?.focus();
                    }}
                >
                    <form onSubmit={deleteUser} className="flex flex-col gap-5">
                        <DialogHeader>
                            <DialogTitle>Excluir sua conta?</DialogTitle>
                            <DialogDescription>
                                Todos os dados da conta serão apagados de forma permanente. Digite sua
                                senha para confirmar a exclusão.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="delete_password">Senha</Label>
                            <Input
                                id="delete_password"
                                type="password"
                                name="password"
                                ref={passwordInput}
                                value={data.password}
                                autoComplete="current-password"
                                aria-invalid={Boolean(errors.password)}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button type="submit" variant="destructive" disabled={processing}>
                                {processing && <Spinner />}
                                Excluir conta permanentemente
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
