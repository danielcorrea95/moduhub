import { Field } from '@/components/invoices/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

/** Endereço de obra, evento ou imóvel: só CEP, sem município (TCEnderObraEvento). */
export interface SimpleAddressForm {
    postal_code: string;
    street: string;
    number: string;
    complement: string;
    district: string;
}

export interface ServiceExtrasForm {
    notes: string;
    reference: string;
    order: string;
    order_items: string;
    technical_document: string;
    construction_mode: 'none' | 'work_code' | 'cib' | 'address';
    construction_registration: string;
    construction_code: string;
    construction_address: SimpleAddressForm;
    has_event: boolean;
    event_name: string;
    event_starts_on: string;
    event_ends_on: string;
    event_mode: 'event_id' | 'address';
    event_id: string;
    event_address: SimpleAddressForm;
}

type Loose = Record<string, unknown> | null | undefined;

const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));

export function emptyAddress(): SimpleAddressForm {
    return { postal_code: '', street: '', number: '', complement: '', district: '' };
}

export function addressFromDraft(address: Loose): SimpleAddressForm {
    return {
        postal_code: text(address?.postal_code),
        street: text(address?.street),
        number: text(address?.number),
        complement: text(address?.complement),
        district: text(address?.district),
    };
}

export function serviceExtrasFromDraft(service: Loose): ServiceExtrasForm {
    const info = (service?.additional_info ?? null) as Loose;
    const work = (service?.construction ?? null) as Loose;
    const event = (service?.event ?? null) as Loose;

    return {
        notes: text(info?.notes),
        reference: text(info?.reference),
        order: text(info?.order),
        order_items: Array.isArray(info?.order_items) ? (info.order_items as string[]).join(', ') : '',
        technical_document: text(info?.technical_document),
        construction_mode: work?.work_code ? 'work_code' : work?.cib ? 'cib' : work?.address ? 'address' : 'none',
        construction_registration: text(work?.registration),
        construction_code: text(work?.work_code ?? work?.cib),
        construction_address: addressFromDraft(work?.address as Loose),
        has_event: Boolean(event),
        event_name: text(event?.name),
        event_starts_on: text(event?.starts_on),
        event_ends_on: text(event?.ends_on),
        event_mode: event?.address ? 'address' : 'event_id',
        event_id: text(event?.event_id),
        event_address: addressFromDraft(event?.address as Loose),
    };
}

export function serviceExtrasPayload(form: ServiceExtrasForm) {
    return {
        additional_info: {
            notes: form.notes,
            reference: form.reference,
            order: form.order,
            order_items: form.order_items
                .split(',')
                .map((item) => item.trim())
                .filter(Boolean),
            technical_document: form.technical_document,
        },
        construction:
            form.construction_mode === 'none'
                ? null
                : {
                      registration: form.construction_registration,
                      work_code: form.construction_mode === 'work_code' ? form.construction_code : '',
                      cib: form.construction_mode === 'cib' ? form.construction_code : '',
                      address: form.construction_mode === 'address' ? form.construction_address : null,
                  },
        event: form.has_event
            ? {
                  name: form.event_name,
                  starts_on: form.event_starts_on,
                  ends_on: form.event_ends_on,
                  event_id: form.event_mode === 'event_id' ? form.event_id : '',
                  address: form.event_mode === 'address' ? form.event_address : null,
              }
            : null,
    };
}

export function SimpleAddressFields({
    idPrefix,
    errorPrefix,
    value,
    onChange,
    errors,
}: {
    idPrefix: string;
    errorPrefix: string;
    value: SimpleAddressForm;
    onChange: (value: SimpleAddressForm) => void;
    errors: Record<string, string | undefined>;
}) {
    const err = (name: string) => errors[`${errorPrefix}.${name}`];
    const set = (patch: Partial<SimpleAddressForm>) => onChange({ ...value, ...patch });

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-[9rem_1fr_6rem]">
                <Field id={`${idPrefix}_postal_code`} label="CEP" error={err('postal_code')}>
                    <Input id={`${idPrefix}_postal_code`} value={value.postal_code} onChange={(e) => set({ postal_code: e.target.value })} inputMode="numeric" />
                </Field>
                <Field id={`${idPrefix}_street`} label="Logradouro" error={err('street')}>
                    <Input id={`${idPrefix}_street`} value={value.street} onChange={(e) => set({ street: e.target.value })} />
                </Field>
                <Field id={`${idPrefix}_number`} label="Número" error={err('number')}>
                    <Input id={`${idPrefix}_number`} value={value.number} onChange={(e) => set({ number: e.target.value })} />
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id={`${idPrefix}_complement`} label="Complemento" optional error={err('complement')}>
                    <Input id={`${idPrefix}_complement`} value={value.complement} onChange={(e) => set({ complement: e.target.value })} />
                </Field>
                <Field id={`${idPrefix}_district`} label="Bairro" error={err('district')}>
                    <Input id={`${idPrefix}_district`} value={value.district} onChange={(e) => set({ district: e.target.value })} />
                </Field>
            </div>
        </div>
    );
}

export function ServiceExtrasFields({
    value,
    onChange,
    errors,
}: {
    value: ServiceExtrasForm;
    onChange: (patch: Partial<ServiceExtrasForm>) => void;
    errors: Record<string, string | undefined>;
}) {
    const err = (key: string) => errors[`service.${key}`];

    return (
        <div className="grid gap-6">
            <div className="grid gap-4">
                <Field
                    id="additional_notes"
                    label="Informações complementares"
                    hint="Saem no DANFSe. Ex.: nome do aluno, turma, período de referência."
                    error={err('additional_info.notes')}
                >
                    <Textarea id="additional_notes" value={value.notes} onChange={(e) => onChange({ notes: e.target.value })} rows={2} maxLength={2000} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="additional_reference" label="Documento de referência" optional hint="Ex.: número do contrato" error={err('additional_info.reference')}>
                        <Input id="additional_reference" value={value.reference} onChange={(e) => onChange({ reference: e.target.value })} maxLength={255} />
                    </Field>
                    <Field
                        id="additional_technical_document"
                        label="Documento de responsabilidade técnica"
                        optional
                        hint="ART, RRT ou TRT"
                        error={err('additional_info.technical_document')}
                    >
                        <Input
                            id="additional_technical_document"
                            value={value.technical_document}
                            onChange={(e) => onChange({ technical_document: e.target.value })}
                            maxLength={40}
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="additional_order" label="Pedido ou ordem de serviço" optional error={err('additional_info.order')}>
                        <Input id="additional_order" value={value.order} onChange={(e) => onChange({ order: e.target.value })} maxLength={60} />
                    </Field>
                    <Field id="additional_order_items" label="Itens do pedido" optional hint="Separados por vírgula" error={err('additional_info.order_items')}>
                        <Input id="additional_order_items" value={value.order_items} onChange={(e) => onChange({ order_items: e.target.value })} />
                    </Field>
                </div>
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Obra de construção civil</p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="construction_mode" label="Identificação da obra">
                        <Select value={value.construction_mode} onValueChange={(v) => onChange({ construction_mode: v as ServiceExtrasForm['construction_mode'] })}>
                            <SelectTrigger id="construction_mode">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Não é obra</SelectItem>
                                <SelectItem value="work_code">Código da obra (CNO/CEI)</SelectItem>
                                <SelectItem value="cib">CIB do imóvel</SelectItem>
                                <SelectItem value="address">Endereço da obra</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {value.construction_mode !== 'none' && (
                        <Field id="construction_registration" label="Inscrição imobiliária fiscal" optional error={err('construction.registration')}>
                            <Input
                                id="construction_registration"
                                value={value.construction_registration}
                                onChange={(e) => onChange({ construction_registration: e.target.value })}
                                maxLength={30}
                            />
                        </Field>
                    )}
                </div>
                {(value.construction_mode === 'work_code' || value.construction_mode === 'cib') && (
                    <Field
                        id="construction_code"
                        label={value.construction_mode === 'cib' ? 'CIB (8 caracteres)' : 'Código da obra'}
                        error={err('construction.work_code') ?? err('construction.cib')}
                        className="sm:max-w-sm"
                    >
                        <Input
                            id="construction_code"
                            value={value.construction_code}
                            onChange={(e) => onChange({ construction_code: e.target.value })}
                            maxLength={value.construction_mode === 'cib' ? 8 : 30}
                        />
                    </Field>
                )}
                {value.construction_mode === 'address' && (
                    <SimpleAddressFields
                        idPrefix="construction_address"
                        errorPrefix="service.construction.address"
                        value={value.construction_address}
                        onChange={(address) => onChange({ construction_address: address })}
                        errors={errors}
                    />
                )}
            </div>

            <div className="grid gap-4">
                <p className="text-sm font-medium">Evento</p>
                <Field id="has_event" label="Serviço ligado a um evento">
                    <Select value={value.has_event ? 'yes' : 'no'} onValueChange={(v) => onChange({ has_event: v === 'yes' })}>
                        <SelectTrigger id="has_event" className="sm:max-w-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="no">Não</SelectItem>
                            <SelectItem value="yes">Sim</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                {value.has_event && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-[1fr_10rem_10rem]">
                            <Field id="event_name" label="Nome do evento" error={err('event.name')}>
                                <Input id="event_name" value={value.event_name} onChange={(e) => onChange({ event_name: e.target.value })} maxLength={255} />
                            </Field>
                            <Field id="event_starts_on" label="Início" error={err('event.starts_on')}>
                                <Input id="event_starts_on" type="date" value={value.event_starts_on} onChange={(e) => onChange({ event_starts_on: e.target.value })} />
                            </Field>
                            <Field id="event_ends_on" label="Fim" error={err('event.ends_on')}>
                                <Input id="event_ends_on" type="date" value={value.event_ends_on} onChange={(e) => onChange({ event_ends_on: e.target.value })} />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="event_mode" label="Local">
                                <Select value={value.event_mode} onValueChange={(v) => onChange({ event_mode: v as ServiceExtrasForm['event_mode'] })}>
                                    <SelectTrigger id="event_mode">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="event_id">Código do evento na prefeitura</SelectItem>
                                        <SelectItem value="address">Endereço</SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            {value.event_mode === 'event_id' && (
                                <Field id="event_id" label="Código do evento" error={err('event.event_id')}>
                                    <Input id="event_id" value={value.event_id} onChange={(e) => onChange({ event_id: e.target.value })} maxLength={30} />
                                </Field>
                            )}
                        </div>
                        {value.event_mode === 'address' && (
                            <SimpleAddressFields
                                idPrefix="event_address"
                                errorPrefix="service.event.address"
                                value={value.event_address}
                                onChange={(address) => onChange({ event_address: address })}
                                errors={errors}
                            />
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
