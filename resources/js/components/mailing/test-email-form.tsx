import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Send } from 'lucide-react';
import type { FormEvent } from 'react';

export function TestEmailForm() {
    const { auth } = usePage<SharedData>().props;
    const form = useForm({ to: auth.user?.email ?? '' });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('sending-domain.test'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <Label htmlFor="test_to">Enviar um e-mail de teste</Label>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    id="test_to"
                    type="email"
                    value={form.data.to}
                    onChange={(e) => form.setData('to', e.target.value)}
                    placeholder="destinatario@empresa.com.br"
                    aria-invalid={Boolean(form.errors.to)}
                    className="sm:max-w-xs"
                    required
                />
                <Button type="submit" variant="outline" disabled={form.processing}>
                    {form.processing ? <Spinner /> : <Send aria-hidden="true" />}
                    Enviar teste
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">Sai com o remetente acima. Até 5 testes a cada 10 minutos.</p>
            <InputError message={form.errors.to} />
        </form>
    );
}
