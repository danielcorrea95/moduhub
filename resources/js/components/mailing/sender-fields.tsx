import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SenderForm } from './types';

/** Nome, endereço e "responder para". Usado no cadastro do domínio e na aba Remetente. */
export function SenderFields({
    data,
    setData,
    errors,
    domain,
}: {
    data: SenderForm;
    setData: (key: keyof SenderForm, value: string) => void;
    errors: Partial<Record<keyof SenderForm, string>>;
    domain: string;
}) {
    return (
        <div className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="from_name">Nome do remetente</Label>
                <Input
                    id="from_name"
                    value={data.from_name}
                    onChange={(e) => setData('from_name', e.target.value)}
                    aria-invalid={Boolean(errors.from_name)}
                    maxLength={100}
                    required
                />
                <InputError message={errors.from_name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="from_local_part">Endereço</Label>
                <div className="flex items-stretch overflow-hidden rounded-md border border-input focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 focus-within:ring-offset-background">
                    <input
                        id="from_local_part"
                        value={data.from_local_part}
                        onChange={(e) => setData('from_local_part', e.target.value)}
                        aria-invalid={Boolean(errors.from_local_part)}
                        aria-describedby="from_local_part_hint"
                        spellCheck={false}
                        autoCapitalize="none"
                        className="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none"
                        required
                    />
                    <span className="flex max-w-[60%] items-center truncate border-l bg-muted/60 px-3 text-sm text-muted-foreground">
                        @{domain || 'suaempresa.com.br'}
                    </span>
                </div>
                <p id="from_local_part_hint" className="text-sm text-muted-foreground">
                    Use uma caixa que exista e seja lida. Sem "responder para", é nela que chegam as respostas.
                </p>
                <InputError message={errors.from_local_part} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="reply_to">
                    Responder para <span className="font-normal text-muted-foreground">(opcional)</span>
                </Label>
                <Input
                    id="reply_to"
                    type="email"
                    value={data.reply_to}
                    onChange={(e) => setData('reply_to', e.target.value)}
                    placeholder="comercial@suaempresa.com.br"
                    aria-invalid={Boolean(errors.reply_to)}
                />
                <InputError message={errors.reply_to} />
            </div>
        </div>
    );
}
