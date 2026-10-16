import { SignerFields, type SignerFormData } from '@/components/signatures/signer-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import type { SubmitEvent } from 'react';

export function AddSignerForm({ documentId }: { documentId: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<SignerFormData>({
        name: '',
        email: '',
        cpf: '',
        cnpj: '',
        phone: '',
        auth_method: 'email',
    });

    const submit = (e: SubmitEvent) => {
        e.preventDefault();
        post(route('signatures.signers.store', documentId), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4 border-t bg-muted/30 px-5 py-5 md:px-6">
            <h3 className="text-sm font-semibold text-foreground">Adicionar signatário</h3>
            <SignerFields idPrefix="new-signer" data={data} setData={setData} errors={errors} />
            <div>
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner /> : <UserPlus />}
                    Adicionar signatário
                </Button>
            </div>
        </form>
    );
}
