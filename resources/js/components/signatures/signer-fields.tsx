import InputError from '@/components/input-error';
import type { SignerStatus } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type AuthMethod = 'email' | 'sms' | 'email_and_sms';

export interface Signer {
    id: string;
    order: number;
    name: string;
    role: string | null;
    email: string;
    cpf: string | null;
    cnpj: string | null;
    phone: string | null;
    auth_method: AuthMethod;
    status: SignerStatus;
    viewed_at: string | null;
    signed_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    /** Presente só enquanto o signatário pode assinar (documento enviado e vez dele). */
    sign_url: string | null;
}

export interface SignerFormData {
    name: string;
    role: string;
    email: string;
    cpf: string;
    cnpj: string;
    phone: string;
    auth_method: AuthMethod;
    [key: string]: string;
}

export const AUTH_METHOD_LABEL: Record<AuthMethod, string> = {
    email: 'Código por e-mail',
    sms: 'Código por SMS',
    email_and_sms: 'Código por e-mail e SMS',
};

const AUTH_METHODS = Object.keys(AUTH_METHOD_LABEL) as AuthMethod[];

/** Sugestões do campo "papel"; a pessoa pode digitar qualquer outro. */
const ROLE_SUGGESTIONS = [
    'Contratante',
    'Contratado',
    'Diretor',
    'Responsável financeiro',
    'Representante legal',
    'Sócio',
    'Fiador',
    'Testemunha',
];

/** Campos compartilhados entre "adicionar" e "editar" signatário. */
export function SignerFields({
    idPrefix,
    data,
    setData,
    errors,
}: {
    idPrefix: string;
    data: SignerFormData;
    setData: <K extends keyof SignerFormData>(key: K, value: SignerFormData[K]) => void;
    errors: Partial<Record<keyof SignerFormData, string>>;
}) {
    const id = (field: string) => `${idPrefix}-${field}`;

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={id('name')}>Nome</Label>
                <Input
                    id={id('name')}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    autoComplete="off"
                    aria-invalid={Boolean(errors.name)}
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('email')}>E-mail</Label>
                <Input
                    id={id('email')}
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    autoComplete="off"
                    aria-invalid={Boolean(errors.email)}
                    required
                />
                <InputError message={errors.email} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('role')}>Assina como (opcional)</Label>
                <Input
                    id={id('role')}
                    value={data.role}
                    onChange={(e) => setData('role', e.target.value)}
                    list={id('role-suggestions')}
                    maxLength={60}
                    placeholder="Ex.: Diretor, Testemunha"
                    autoComplete="off"
                    aria-describedby={id('role-help')}
                    aria-invalid={Boolean(errors.role)}
                />
                <datalist id={id('role-suggestions')}>
                    {ROLE_SUGGESTIONS.map((role) => (
                        <option key={role} value={role} />
                    ))}
                </datalist>
                <p id={id('role-help')} className="text-xs text-muted-foreground">
                    Aparece no relatório de assinaturas.
                </p>
                <InputError message={errors.role} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('cpf')}>CPF (opcional)</Label>
                <Input
                    id={id('cpf')}
                    value={data.cpf}
                    onChange={(e) => setData('cpf', e.target.value)}
                    placeholder="000.000.000-00"
                    inputMode="numeric"
                    aria-invalid={Boolean(errors.cpf)}
                />
                <InputError message={errors.cpf} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('cnpj')}>CNPJ (opcional)</Label>
                <Input
                    id={id('cnpj')}
                    value={data.cnpj}
                    onChange={(e) => setData('cnpj', e.target.value)}
                    placeholder="00.000.000/0000-00"
                    inputMode="numeric"
                    aria-invalid={Boolean(errors.cnpj)}
                />
                <InputError message={errors.cnpj} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('phone')}>Telefone (opcional)</Label>
                <Input
                    id={id('phone')}
                    type="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    placeholder="+55 11 90000-0000"
                    aria-invalid={Boolean(errors.phone)}
                />
                <InputError message={errors.phone} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('auth-method')}>Como o signatário confirma a identidade</Label>
                <Select
                    value={data.auth_method}
                    onValueChange={(value) => setData('auth_method', value as AuthMethod)}
                >
                    <SelectTrigger id={id('auth-method')} aria-invalid={Boolean(errors.auth_method)}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {AUTH_METHODS.map((m) => (
                            <SelectItem key={m} value={m}>
                                {AUTH_METHOD_LABEL[m]}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.auth_method} />
            </div>
        </div>
    );
}
