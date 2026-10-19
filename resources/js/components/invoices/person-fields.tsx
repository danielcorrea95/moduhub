import { countryName, Field } from '@/components/invoices/field';
import { MunicipalityInput } from '@/components/municipality-input';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export interface AddressForm {
    country: string;
    postal_code: string;
    street: string;
    number: string;
    complement: string;
    district: string;
    city_code: string;
    city: string;
    state: string;
}

export interface PersonForm {
    /** Só do formulário: decide entre CPF/CNPJ + endereço nacional e NIF + endereço no exterior. */
    foreign: boolean;
    document: string;
    nif: string;
    nif_absence_reason: string;
    name: string;
    email: string;
    phone: string;
    municipal_registration: string;
    address: AddressForm;
}

export type PersonDraft = Partial<Omit<PersonForm, 'address' | 'foreign'>> & { address?: Partial<AddressForm> | null };

const text = (value: string | number | null | undefined) => (value === null || value === undefined ? '' : String(value));

export function personFromDraft(draft: PersonDraft | null | undefined): PersonForm {
    const address = draft?.address ?? {};
    const country = text(address.country).toUpperCase();

    return {
        foreign: Boolean(draft?.nif || draft?.nif_absence_reason || (country !== '' && country !== 'BR')),
        document: text(draft?.document),
        nif: text(draft?.nif),
        nif_absence_reason: text(draft?.nif_absence_reason),
        name: text(draft?.name),
        email: text(draft?.email),
        phone: text(draft?.phone),
        municipal_registration: text(draft?.municipal_registration),
        address: {
            country,
            postal_code: text(address.postal_code),
            street: text(address.street),
            number: text(address.number),
            complement: text(address.complement),
            district: text(address.district),
            city_code: text(address.city_code),
            city: text(address.city),
            state: text(address.state),
        },
    };
}

/** Formato da API: só os campos do tipo escolhido (nacional ou estrangeiro) seguem preenchidos. */
export function personPayload(person: PersonForm) {
    const { foreign, address, ...rest } = person;

    return {
        ...rest,
        document: foreign ? '' : rest.document,
        nif: foreign && rest.nif_absence_reason === '' ? rest.nif : '',
        nif_absence_reason: foreign ? rest.nif_absence_reason : '',
        address: foreign ? { ...address, city_code: '' } : { ...address, country: '', city: '', state: '' },
    };
}

/**
 * Campos do tomador ou do intermediário (TCInfoPessoa). `errorKey` é o prefixo dos erros do backend ("taker", "intermediary").
 */
export function PersonFields({
    idPrefix,
    errorKey,
    value,
    onChange,
    errors,
    nifAbsence,
    requireDocument = true,
}: {
    idPrefix: string;
    errorKey: string;
    value: PersonForm;
    onChange: (patch: Partial<PersonForm>) => void;
    errors: Record<string, string | undefined>;
    nifAbsence: Record<string, string>;
    requireDocument?: boolean;
}) {
    const id = (name: string) => `${idPrefix}_${name}`;
    const err = (name: string) => errors[`${errorKey}.${name}`];
    const setAddress = (patch: Partial<AddressForm>) => onChange({ address: { ...value.address, ...patch } });
    const country = value.address.country.trim().toUpperCase();
    const countryLabel = country.length === 2 ? countryName(country) : null;

    return (
        <div className="grid gap-4">
            <div className="flex items-center gap-2.5">
                <Checkbox
                    id={id('foreign')}
                    checked={value.foreign}
                    onCheckedChange={(checked) => onChange({ foreign: checked === true })}
                />
                <Label htmlFor={id('foreign')} className="font-normal">
                    Estrangeiro, sem CPF ou CNPJ
                </Label>
            </div>

            {value.foreign ? (
                <div className="grid gap-4 sm:grid-cols-[14rem_1fr]">
                    <Field id={id('nif_absence_reason')} label="Identificação fiscal" error={err('nif_absence_reason')}>
                        <Select
                            value={value.nif_absence_reason || 'nif'}
                            onValueChange={(v) => onChange({ nif_absence_reason: v === 'nif' ? '' : v })}
                        >
                            <SelectTrigger id={id('nif_absence_reason')}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="nif">Tem NIF</SelectItem>
                                {Object.entries(nifAbsence).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    {value.nif_absence_reason === '' && (
                        <Field
                            id={id('nif')}
                            label="NIF"
                            hint="Número de identificação fiscal no país de origem."
                            error={err('nif') ?? err('document')}
                        >
                            <Input
                                id={id('nif')}
                                value={value.nif}
                                onChange={(e) => onChange({ nif: e.target.value })}
                                maxLength={40}
                                aria-invalid={Boolean(err('nif') ?? err('document'))}
                                required={requireDocument}
                            />
                        </Field>
                    )}
                </div>
            ) : (
                <Field id={id('document')} label="CPF ou CNPJ" error={err('document')} className="sm:max-w-[14rem]">
                    <Input
                        id={id('document')}
                        value={value.document}
                        onChange={(e) => onChange({ document: e.target.value })}
                        aria-invalid={Boolean(err('document'))}
                        required={requireDocument}
                    />
                </Field>
            )}

            <Field id={id('name')} label="Nome ou razão social" error={err('name')}>
                <Input
                    id={id('name')}
                    value={value.name}
                    onChange={(e) => onChange({ name: e.target.value })}
                    aria-invalid={Boolean(err('name'))}
                    required={requireDocument}
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field id={id('email')} label="E-mail" optional error={err('email')}>
                    <Input id={id('email')} type="email" value={value.email} onChange={(e) => onChange({ email: e.target.value })} />
                </Field>
                <Field id={id('phone')} label="Telefone" optional error={err('phone')}>
                    <Input id={id('phone')} value={value.phone} onChange={(e) => onChange({ phone: e.target.value })} inputMode="tel" />
                </Field>
            </div>

            <details className="rounded-lg border px-4 py-3 [&[open]]:pb-4">
                <summary className="cursor-pointer text-sm font-medium">
                    Endereço <span className="font-normal text-muted-foreground">(opcional)</span>
                </summary>
                <div className="mt-4 grid gap-4">
                    {value.foreign && (
                        <div className="grid gap-4 sm:grid-cols-[5rem_1fr_1fr]">
                            <Field
                                id={id('country')}
                                label="País"
                                hint={countryLabel ?? 'Código ISO, ex.: US, DE, PT'}
                                error={err('address.country')}
                            >
                                <Input
                                    id={id('country')}
                                    value={value.address.country}
                                    onChange={(e) => setAddress({ country: e.target.value.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() })}
                                    maxLength={2}
                                />
                            </Field>
                            <Field id={id('city')} label="Cidade" error={err('address.city')}>
                                <Input id={id('city')} value={value.address.city} onChange={(e) => setAddress({ city: e.target.value })} maxLength={60} />
                            </Field>
                            <Field id={id('state')} label="Estado ou província" error={err('address.state')}>
                                <Input id={id('state')} value={value.address.state} onChange={(e) => setAddress({ state: e.target.value })} maxLength={60} />
                            </Field>
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                        <Field id={id('postal_code')} label={value.foreign ? 'Código postal' : 'CEP'} error={err('address.postal_code')}>
                            <Input
                                id={id('postal_code')}
                                value={value.address.postal_code}
                                onChange={(e) => setAddress({ postal_code: e.target.value })}
                                inputMode={value.foreign ? 'text' : 'numeric'}
                                maxLength={value.foreign ? 11 : 9}
                            />
                        </Field>
                        <Field id={id('street')} label="Logradouro" error={err('address.street')}>
                            <Input id={id('street')} value={value.address.street} onChange={(e) => setAddress({ street: e.target.value })} />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                        <Field id={id('number')} label="Número" error={err('address.number')}>
                            <Input id={id('number')} value={value.address.number} onChange={(e) => setAddress({ number: e.target.value })} />
                        </Field>
                        <Field id={id('complement')} label="Complemento" optional error={err('address.complement')}>
                            <Input id={id('complement')} value={value.address.complement} onChange={(e) => setAddress({ complement: e.target.value })} />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id={id('district')} label="Bairro" error={err('address.district')}>
                            <Input id={id('district')} value={value.address.district} onChange={(e) => setAddress({ district: e.target.value })} />
                        </Field>
                        {!value.foreign && (
                            <Field id={id('city_code')} label="Município" error={err('address.city_code')}>
                                <MunicipalityInput
                                    id={id('city_code')}
                                    value={value.address.city_code}
                                    onChange={(code) => setAddress({ city_code: code })}
                                    invalid={Boolean(err('address.city_code'))}
                                    placeholder="Nome ou código IBGE"
                                />
                            </Field>
                        )}
                    </div>
                </div>
            </details>
        </div>
    );
}
