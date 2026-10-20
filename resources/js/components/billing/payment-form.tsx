import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { CreditCard, Lock, QrCode } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import type { BillingAccountData, BillingType } from './types';

interface PaymentFormData extends BillingAccountData {
    plan_ids: number[];
    billing_type: BillingType;
    card_holder_name: string;
    card_number: string;
    card_expiry_month: string;
    card_expiry_year: string;
    card_cvv: string;
    [key: string]: string | number | number[] | null;
}

type FieldName = keyof BillingAccountData | keyof typeof EMPTY_CARD;

const EMPTY_CARD = {
    card_holder_name: '',
    card_number: '',
    card_expiry_month: '',
    card_expiry_year: '',
    card_cvv: '',
};

/** "4111111111111111" → "4111 1111 1111 1111", enquanto digita. */
const groupCardNumber = (value: string) =>
    value
        .replace(/\D/g, '')
        .slice(0, 19)
        .replace(/(\d{4})(?=\d)/g, '$1 ');

/**
 * Forma de pagamento + dados de cobrança, para assinar (com plano) ou trocar a forma de pagamento.
 * O cartão vai direto para o backend, que repassa ao Asaas; o formulário limpa os campos do cartão
 * depois de cada envio, dando certo ou não.
 */
export function PaymentForm({
    mode,
    planIds,
    account,
    defaultType,
    submitLabel,
    footnote,
    onDone,
}: {
    mode: 'subscribe' | 'change';
    /** Carrinho a assinar (modo subscribe). */
    planIds?: number[];
    account: BillingAccountData;
    defaultType: BillingType;
    submitLabel: string;
    footnote?: ReactNode;
    onDone?: () => void;
}) {
    const form = useForm<PaymentFormData>({
        plan_ids: planIds ?? [],
        billing_type: defaultType,
        ...account,
        ...EMPTY_CARD,
    });
    const { data, setData, errors, processing } = form;
    const byCard = data.billing_type === 'CREDIT_CARD';

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onFinish: () => form.setData((current) => ({ ...current, ...EMPTY_CARD })),
            onSuccess: () => onDone?.(),
        };
        form.transform((current) => ({ ...current, plan_ids: planIds ?? current.plan_ids }));
        if (mode === 'subscribe') {
            form.post(route('billing.subscribe'), options);
        } else {
            form.patch(route('billing.payment-method'), options);
        }
    };

    const field = (name: FieldName, label: string, props: Record<string, unknown> = {}) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                value={String(data[name] ?? '')}
                onChange={(e) => setData(name, e.target.value)}
                aria-invalid={Boolean(errors[name])}
                {...props}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <fieldset className="grid gap-3">
                <legend className="mb-2 text-sm font-medium">Forma de pagamento</legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    <MethodOption
                        selected={!byCard}
                        onSelect={() => setData('billing_type', 'PIX')}
                        icon={<QrCode className="size-5" />}
                        title="Pix"
                        description="Todo mês chega a cobrança por e-mail e você paga pelo app do banco."
                    />
                    <MethodOption
                        selected={byCard}
                        onSelect={() => setData('billing_type', 'CREDIT_CARD')}
                        icon={<CreditCard className="size-5" />}
                        title="Cartão de crédito"
                        description="Cobrado automaticamente todo mês, sem você precisar fazer nada."
                    />
                </div>
                <InputError message={errors.billing_type} />
            </fieldset>

            {byCard && (
                <fieldset className="grid gap-4 rounded-lg border bg-muted/30 p-4">
                    <legend className="px-1 text-sm font-medium">Cartão</legend>
                    {field('card_number', 'Número do cartão', {
                        inputMode: 'numeric',
                        autoComplete: 'cc-number',
                        placeholder: '0000 0000 0000 0000',
                        value: groupCardNumber(data.card_number),
                        onChange: (e: React.ChangeEvent<HTMLInputElement>) => setData('card_number', e.target.value.replace(/\D/g, '')),
                        className: 'font-mono',
                    })}
                    {field('card_holder_name', 'Nome impresso no cartão', { autoComplete: 'cc-name' })}
                    <div className="grid grid-cols-3 gap-3">
                        {field('card_expiry_month', 'Mês', { inputMode: 'numeric', autoComplete: 'cc-exp-month', placeholder: 'MM', maxLength: 2 })}
                        {field('card_expiry_year', 'Ano', { inputMode: 'numeric', autoComplete: 'cc-exp-year', placeholder: 'AAAA', maxLength: 4 })}
                        {field('card_cvv', 'CVV', { inputMode: 'numeric', autoComplete: 'cc-csc', placeholder: '123', maxLength: 4, type: 'password' })}
                    </div>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Lock className="size-3.5" /> O cartão vai direto para o Asaas, nosso processador de pagamentos. A Moduhub guarda só a bandeira e o final.
                    </p>
                </fieldset>
            )}

            <fieldset className="grid gap-4">
                <legend className="mb-2 text-sm font-medium">
                    Dados de cobrança{byCard && <span className="font-normal text-muted-foreground"> (do titular do cartão)</span>}
                </legend>
                {field('name', 'Nome ou razão social', { autoComplete: 'name' })}
                <div className="grid gap-4 sm:grid-cols-2">
                    {field('tax_id', 'CPF ou CNPJ', { inputMode: 'numeric' })}
                    {field('email', 'E-mail para as cobranças', { type: 'email', autoComplete: 'email' })}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    {field('phone', 'Celular', { inputMode: 'tel', autoComplete: 'tel', placeholder: '(51) 99999-0000' })}
                    {field('postal_code', 'CEP', { inputMode: 'numeric', autoComplete: 'postal-code', placeholder: '00000-000' })}
                    {field('address_number', 'Número', {})}
                </div>
                {field('address_complement', 'Complemento (opcional)', {})}
            </fieldset>

            {errors.gateway && (
                <p role="alert" className="rounded-md bg-rejected-soft px-3 py-2 text-sm text-rejected">
                    {errors.gateway}
                </p>
            )}
            <InputError message={errors.plan_ids} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {footnote && <p className="text-sm text-muted-foreground">{footnote}</p>}
                <Button type="submit" disabled={processing} className="sm:ml-auto">
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function MethodOption({
    selected,
    onSelect,
    icon,
    title,
    description,
}: {
    selected: boolean;
    onSelect: () => void;
    icon: ReactNode;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn(
                'flex items-start gap-3 rounded-lg border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                selected ? 'border-primary bg-primary/5' : 'hover:bg-muted/50',
            )}
        >
            <span className={cn('mt-0.5', selected ? 'text-primary' : 'text-muted-foreground')}>{icon}</span>
            <span>
                <span className="block text-sm font-medium">{title}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{description}</span>
            </span>
        </button>
    );
}
