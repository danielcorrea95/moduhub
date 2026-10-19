import { ConfirmAction } from '@/components/confirm-action';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { MunicipalityInput } from '@/components/municipality-input';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatCnpj } from '@/lib/fiscal';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookOpen, Check, FileKey2, History, ShieldAlert, ShieldCheck, ShieldX, Upload } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';

type Environment = 'restricted' | 'production';

interface IssuerCertificate {
    subject: string | null;
    cnpj: string | null;
    issuer: string | null;
    serial: string | null;
    fingerprint_sha256: string | null;
    valid_from: string | null;
    valid_until: string | null;
    uploaded_at: string | null;
    expired: boolean;
    expires_soon: boolean;
}

interface IssuerData {
    id: string;
    legal_name: string;
    trade_name: string | null;
    cnpj: string;
    municipal_registration: string | null;
    city_code: string;
    city_name: string | null;
    state: string | null;
    simples_nacional: number;
    simples_regime: number | null;
    special_regime: number;
    ibs_cbs: { cst: string | null; class_code: string | null; operation_code: string | null };
    default_service: { national_tax_code: string | null; municipal_tax_code: string | null; nbs_code: string | null; description: string | null } | null;
    default_approximate_taxes: { federal: string | null; state: string | null; municipal: string | null } | null;
    email_taker: boolean;
    danfse_stub: boolean;
    phone: string | null;
    email: string | null;
    environment: Environment;
    dps_series: string;
    next_dps_number: number;
    ready_to_issue: boolean;
    certificate: IssuerCertificate | null;
}

interface CertificateEventData {
    id: string;
    type: 'uploaded' | 'replaced' | 'removed' | 'used' | 'expiry_notice';
    type_label: string;
    purpose: string | null;
    purpose_label: string | null;
    certificate_fingerprint: string | null;
    actor: { type: 'user' | 'api_key' | 'system'; label: string | null };
    ip_address: string | null;
    data: Record<string, unknown> | null;
    created_at: string;
}

type Options = Record<string, string> | string[];

interface Props {
    issuer: IssuerData | null;
    certificateEvents: CertificateEventData[];
    canManage: boolean;
    options: {
        simples_nacional: Options;
        simples_regime: Options;
        special_regime: Options;
        environment: Options;
    };
    [key: string]: unknown;
}

interface IssuerForm {
    legal_name: string;
    trade_name: string;
    cnpj: string;
    municipal_registration: string;
    city_code: string;
    city_name: string;
    state: string;
    simples_nacional: string;
    simples_regime: string;
    special_regime: string;
    ibs_cbs_cst: string;
    ibs_cbs_class_code: string;
    ibs_cbs_operation_code: string;
    default_service: { national_tax_code: string; municipal_tax_code: string; nbs_code: string; description: string };
    default_approximate_taxes: { federal: string; state: string; municipal: string };
    email_taker: boolean;
    danfse_stub: boolean;
    phone: string;
    email: string;
    environment: Environment;
    dps_series: string;
}

interface CertificateForm {
    certificate: File | null;
    password: string;
}

const SIMPLES_ME_EPP = '3';

function entries(options: Options): [string, string][] {
    return Object.entries(options);
}

function OptionSelect({
    id,
    value,
    options,
    onChange,
    error,
    disabled,
}: {
    id: string;
    value: string;
    options: Options;
    onChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
}) {
    return (
        <>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger id={id} aria-invalid={Boolean(error)} className="h-auto min-h-9 py-1.5 text-left whitespace-normal">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {entries(options).map(([key, label]) => (
                        <SelectItem key={key} value={key}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </>
    );
}

function CertificateStatus({ certificate }: { certificate: IssuerCertificate }) {
    const state = certificate.expired
        ? { label: 'Expirado', icon: ShieldX, className: 'border-rejected/30 bg-rejected-soft text-rejected' }
        : certificate.expires_soon
          ? { label: 'Vence em breve', icon: ShieldAlert, className: 'border-pending/30 bg-pending-soft text-pending' }
          : { label: 'Válido', icon: ShieldCheck, className: 'border-signed/30 bg-signed-soft text-signed' };

    return (
        <div className={cn('flex items-start gap-3 rounded-lg border px-4 py-3', state.className)}>
            <state.icon className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1 text-sm">
                <p className="font-semibold">
                    {state.label}
                    <span className="font-normal"> até {formatDate(certificate.valid_until)}</span>
                </p>
                <dl className="mt-2 grid gap-x-6 gap-y-1 text-foreground/85 sm:grid-cols-[auto_minmax(0,1fr)]">
                    <dt className="text-muted-foreground">Titular</dt>
                    <dd className="min-w-0 break-words">{certificate.subject ?? '—'}</dd>
                    <dt className="text-muted-foreground">CNPJ</dt>
                    <dd>{certificate.cnpj ? formatCnpj(certificate.cnpj) : '—'}</dd>
                    <dt className="text-muted-foreground">Emitido por</dt>
                    <dd className="min-w-0 break-words">{certificate.issuer ?? '—'}</dd>
                    <dt className="text-muted-foreground">Enviado em</dt>
                    <dd>{formatDateTime(certificate.uploaded_at)}</dd>
                </dl>
            </div>
        </div>
    );
}

function eventDetail(event: CertificateEventData): string | null {
    const data = event.data ?? {};
    const parts: string[] = [];

    if (typeof data.operation === 'string') parts.push(data.operation);
    if (typeof data.external_id === 'string') parts.push(`pedido ${data.external_id}`);
    if (typeof data.access_key === 'string') parts.push(`chave …${data.access_key.slice(-8)}`);
    if (typeof data.days_left === 'number') parts.push(data.days_left <= 0 ? 'certificado vencido' : `faltavam ${data.days_left} dias`);
    if (event.certificate_fingerprint && event.type !== 'used') parts.push(`impressão digital ${event.certificate_fingerprint.slice(0, 12)}…`);

    return parts.length > 0 ? parts.join(' · ') : null;
}

const HISTORY_PREVIEW = 8;

function CertificateHistory({ events }: { events: CertificateEventData[] }) {
    const [expanded, setExpanded] = useState(false);
    const visible = expanded ? events : events.slice(0, HISTORY_PREVIEW);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <History className="size-4 text-muted-foreground" aria-hidden="true" />
                <p className="text-sm font-medium">Histórico do certificado</p>
            </div>
            <p className="text-sm text-muted-foreground">
                Cada envio, troca e remoção, e cada vez que a chave privada foi usada, com quem fez e para quê.
            </p>
            {events.length === 0 ? (
                <p className="rounded-lg border border-dashed px-4 py-4 text-center text-sm text-muted-foreground">Nenhum registro ainda.</p>
            ) : (
                <ol className="divide-y rounded-lg border">
                    {visible.map((event) => {
                        const detail = eventDetail(event);

                        return (
                            <li key={event.id} className="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                                <div className="min-w-0">
                                    <p className="font-medium">{event.purpose_label ?? event.type_label}</p>
                                    {detail && <p className="break-words text-muted-foreground">{detail}</p>}
                                </div>
                                <div className="shrink-0 text-muted-foreground sm:text-right">
                                    <p>
                                        <time dateTime={event.created_at}>{formatDateTime(event.created_at)}</time>
                                    </p>
                                    <p className="break-words">
                                        {event.actor.label ?? 'Sistema'}
                                        {event.ip_address && <span> · {event.ip_address}</span>}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
            {events.length > HISTORY_PREVIEW && (
                <Button type="button" variant="ghost" size="sm" className="self-start" onClick={() => setExpanded((value) => !value)}>
                    {expanded ? 'Mostrar menos' : `Mostrar os ${events.length} registros mais recentes`}
                </Button>
            )}
        </div>
    );
}

export default function InvoicesIssuer({ issuer, certificateEvents, canManage, options }: Props) {
    const form = useForm<IssuerForm>({
        legal_name: issuer?.legal_name ?? '',
        trade_name: issuer?.trade_name ?? '',
        cnpj: issuer ? formatCnpj(issuer.cnpj) : '',
        municipal_registration: issuer?.municipal_registration ?? '',
        city_code: issuer?.city_code ?? '',
        city_name: issuer?.city_name ?? '',
        state: issuer?.state ?? '',
        simples_nacional: String(issuer?.simples_nacional ?? 3),
        simples_regime: issuer?.simples_regime ? String(issuer.simples_regime) : '1',
        special_regime: String(issuer?.special_regime ?? 0),
        ibs_cbs_cst: issuer?.ibs_cbs.cst ?? '',
        ibs_cbs_class_code: issuer?.ibs_cbs.class_code ?? '',
        ibs_cbs_operation_code: issuer?.ibs_cbs.operation_code ?? '',
        default_service: {
            national_tax_code: issuer?.default_service?.national_tax_code ?? '',
            municipal_tax_code: issuer?.default_service?.municipal_tax_code ?? '',
            nbs_code: issuer?.default_service?.nbs_code ?? '',
            description: issuer?.default_service?.description ?? '',
        },
        default_approximate_taxes: {
            federal: issuer?.default_approximate_taxes?.federal ?? '',
            state: issuer?.default_approximate_taxes?.state ?? '',
            municipal: issuer?.default_approximate_taxes?.municipal ?? '',
        },
        email_taker: issuer?.email_taker ?? true,
        danfse_stub: issuer?.danfse_stub ?? false,
        phone: issuer?.phone ?? '',
        email: issuer?.email ?? '',
        environment: issuer?.environment ?? 'restricted',
        dps_series: issuer?.dps_series ?? '1',
    });

    const fileInput = useRef<HTMLInputElement>(null);
    const certForm = useForm<CertificateForm>({ certificate: null, password: '' });

    const submitIssuer = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(route('invoices.issuer.update'), { preserveScroll: true });
    };

    const submitCertificate = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        certForm.post(route('invoices.issuer.certificate.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                certForm.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const removeCertificate = () =>
        router.delete(route('invoices.issuer.certificate.destroy'), { preserveScroll: true });

    const isMeEpp = form.data.simples_nacional === SIMPLES_ME_EPP;
    const readOnly = !canManage;

    return (
        <AppLayout breadcrumbs={[{ title: 'Notas fiscais' }, { title: 'Emitente' }]} width="narrow">
            <Head title="Emitente de notas fiscais" />

            <PageHeader
                title="Emitente de notas fiscais"
                description="A empresa que emite NFS-e por este workspace e o certificado digital que assina as notas na Sefin Nacional."
                actions={
                    <Button asChild variant="outline">
                        <Link href={`${route('docs.api')}#objeto-emitente`}>
                            <BookOpen /> API do emitente
                        </Link>
                    </Button>
                }
            />

            {readOnly && (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground" role="status">
                    Só quem administra o workspace pode alterar os dados fiscais e o certificado.
                </p>
            )}

            <form onSubmit={submitIssuer} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <fieldset disabled={readOnly} className="contents">
                    <FormSection title="Empresa" description="Como o prestador aparece na nota. Precisa bater com o cadastro na Receita e na prefeitura.">
                        <div className="grid gap-2">
                            <Label htmlFor="legal_name">Razão social</Label>
                            <Input
                                id="legal_name"
                                value={form.data.legal_name}
                                onChange={(e) => form.setData('legal_name', e.target.value)}
                                aria-invalid={Boolean(form.errors.legal_name)}
                                required
                            />
                            <InputError message={form.errors.legal_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="trade_name">
                                Nome fantasia <span className="font-normal text-muted-foreground">(opcional)</span>
                            </Label>
                            <Input
                                id="trade_name"
                                value={form.data.trade_name}
                                onChange={(e) => form.setData('trade_name', e.target.value)}
                                aria-invalid={Boolean(form.errors.trade_name)}
                            />
                            <InputError message={form.errors.trade_name} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="cnpj">CNPJ</Label>
                                <Input
                                    id="cnpj"
                                    value={form.data.cnpj}
                                    onChange={(e) => form.setData('cnpj', e.target.value)}
                                    placeholder="00.000.000/0000-00"
                                    aria-invalid={Boolean(form.errors.cnpj)}
                                    required
                                />
                                <InputError message={form.errors.cnpj} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="municipal_registration">
                                    Inscrição municipal <span className="font-normal text-muted-foreground">(opcional)</span>
                                </Label>
                                <Input
                                    id="municipal_registration"
                                    value={form.data.municipal_registration}
                                    onChange={(e) => form.setData('municipal_registration', e.target.value)}
                                    aria-invalid={Boolean(form.errors.municipal_registration)}
                                />
                                <InputError message={form.errors.municipal_registration} />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:max-w-md">
                            <Label htmlFor="city_code">Município</Label>
                            <MunicipalityInput
                                id="city_code"
                                value={form.data.city_code}
                                onChange={(code, municipality) =>
                                    form.setData((data) => ({ ...data, city_code: code, city_name: municipality?.name ?? '', state: municipality?.uf ?? '' }))
                                }
                                invalid={Boolean(form.errors.city_code)}
                                placeholder="Nome do município ou código IBGE"
                            />
                            <InputError message={form.errors.city_code} />
                        </div>
                        <p className="-mt-3 text-sm text-muted-foreground">
                            O código IBGE do município do estabelecimento emissor define para qual prefeitura a nota é lançada.
                        </p>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    Telefone <span className="font-normal text-muted-foreground">(opcional)</span>
                                </Label>
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    placeholder="(51) 3594-0000"
                                    inputMode="tel"
                                    aria-invalid={Boolean(form.errors.phone)}
                                />
                                <InputError message={form.errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    E-mail fiscal <span className="font-normal text-muted-foreground">(opcional)</span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    aria-invalid={Boolean(form.errors.email)}
                                />
                                <InputError message={form.errors.email} />
                            </div>
                        </div>
                    </FormSection>

                    <Separator />

                    <FormSection title="Enquadramento tributário" description="Determina como a Sefin Nacional calcula o ISSQN e os campos do Simples na nota.">
                        <div className="grid gap-2">
                            <Label htmlFor="simples_nacional">Simples Nacional</Label>
                            <OptionSelect
                                id="simples_nacional"
                                value={form.data.simples_nacional}
                                options={options.simples_nacional}
                                onChange={(v) => form.setData('simples_nacional', v)}
                                error={form.errors.simples_nacional}
                                disabled={readOnly}
                            />
                        </div>

                        {isMeEpp && (
                            <div className="grid gap-2">
                                <Label htmlFor="simples_regime">Regime de apuração</Label>
                                <OptionSelect
                                    id="simples_regime"
                                    value={form.data.simples_regime}
                                    options={options.simples_regime}
                                    onChange={(v) => form.setData('simples_regime', v)}
                                    error={form.errors.simples_regime}
                                    disabled={readOnly}
                                />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="special_regime">Regime especial</Label>
                            <OptionSelect
                                id="special_regime"
                                value={form.data.special_regime}
                                options={options.special_regime}
                                onChange={(v) => form.setData('special_regime', v)}
                                error={form.errors.special_regime}
                                disabled={readOnly}
                            />
                        </div>
                    </FormSection>

                    <Separator />

                    <FormSection
                        title="Padrões de emissão"
                        description="O que vale quando a nota não informa. Útil para quem emite sempre o mesmo serviço, como mensalidades: o sistema integrado manda só tomador e valor."
                    >
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['national_tax_code', 'Tributação nacional', 'Ex.: 08.01.01', 6],
                                    ['municipal_tax_code', 'Trib. municipal', '3 dígitos, se houver', 3],
                                    ['nbs_code', 'Código NBS', '9 dígitos, se houver', 9],
                                ] as const
                            ).map(([key, label, hint, size]) => (
                                <div key={key} className="grid content-start gap-2">
                                    <Label htmlFor={`default_${key}`}>{label}</Label>
                                    <Input
                                        id={`default_${key}`}
                                        value={form.data.default_service[key]}
                                        onChange={(e) =>
                                            form.setData('default_service', { ...form.data.default_service, [key]: e.target.value.replace(/\D/g, '').slice(0, size) })
                                        }
                                        inputMode="numeric"
                                        disabled={readOnly}
                                    />
                                    <p className="text-xs text-muted-foreground">{hint}</p>
                                    <InputError message={(form.errors as Record<string, string | undefined>)[`default_service.${key}`]} />
                                </div>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="default_description">Descrição padrão do serviço</Label>
                            <Textarea
                                id="default_description"
                                value={form.data.default_service.description}
                                onChange={(e) => form.setData('default_service', { ...form.data.default_service, description: e.target.value })}
                                rows={2}
                                maxLength={2000}
                                placeholder="Ex.: Prestação de serviços educacionais"
                                disabled={readOnly}
                            />
                            <p className="text-xs text-muted-foreground">Usada quando a nota vem sem descrição.</p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['federal', 'Tributos federais (%)'],
                                    ['state', 'Tributos estaduais (%)'],
                                    ['municipal', 'Tributos municipais (%)'],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key} className="grid content-start gap-2">
                                    <Label htmlFor={`default_tax_${key}`}>{label}</Label>
                                    <Input
                                        id={`default_tax_${key}`}
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={form.data.default_approximate_taxes[key]}
                                        onChange={(e) => form.setData('default_approximate_taxes', { ...form.data.default_approximate_taxes, [key]: e.target.value })}
                                        disabled={readOnly}
                                    />
                                </div>
                            ))}
                        </div>
                        <p className="-mt-2 text-xs text-muted-foreground">
                            Percentuais aproximados da Lei 12.741 (tabela do IBPT para o código do serviço), impressos na nota.
                        </p>
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="email_taker"
                                checked={form.data.email_taker}
                                onCheckedChange={(checked) => form.setData('email_taker', checked === true)}
                                disabled={readOnly}
                            />
                            <Label htmlFor="email_taker" className="font-normal">
                                Enviar a nota por e-mail ao tomador assim que for emitida (DANFSe e XML anexados)
                            </Label>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="danfse_stub"
                                checked={form.data.danfse_stub}
                                onCheckedChange={(checked) => form.setData('danfse_stub', checked === true)}
                                disabled={readOnly}
                            />
                            <Label htmlFor="danfse_stub" className="font-normal">
                                Imprimir canhoto no DANFSe (data, assinatura de quem recebeu e número da nota)
                            </Label>
                        </div>
                    </FormSection>

                    <Separator />

                    <FormSection
                        title="IBS e CBS"
                        description="Classificação da reforma tributária que vai em todas as notas, salvo quando a nota informar outra. Peça os códigos ao contador."
                    >
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['ibs_cbs_cst', 'CST', 3, 'Ex.: 000'],
                                    ['ibs_cbs_class_code', 'Classificação (cClassTrib)', 6, 'Começa pelo CST'],
                                    ['ibs_cbs_operation_code', 'Indicador de operação (cIndOp)', 6, '6 dígitos'],
                                ] as const
                            ).map(([key, label, size, hint]) => (
                                <div key={key} className="grid content-start gap-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        value={form.data[key]}
                                        onChange={(e) => form.setData(key, e.target.value.replace(/\D/g, '').slice(0, size))}
                                        inputMode="numeric"
                                        aria-invalid={Boolean(form.errors[key])}
                                        disabled={readOnly}
                                    />
                                    <p className="text-xs text-muted-foreground">{hint}</p>
                                    <InputError message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                        <p className="-mt-2 text-sm text-muted-foreground">
                            Sem estes códigos a DPS sai sem o grupo IBS/CBS. Para empresas do regime regular ele já é exigido desde 01/10/2026,
                            sem rejeição até 31/12/2026; para optantes do Simples Nacional, a partir de 01/01/2027.
                        </p>
                    </FormSection>

                    <Separator />

                    <FormSection title="Emissão" description="Ambiente da Sefin Nacional e numeração das declarações (DPS) que geram as notas.">
                        <div className="grid gap-2">
                            <Label htmlFor="environment">Ambiente</Label>
                            <OptionSelect
                                id="environment"
                                value={form.data.environment}
                                options={options.environment}
                                onChange={(v) => form.setData('environment', v as Environment)}
                                error={form.errors.environment}
                                disabled={readOnly}
                            />
                            <p className="text-sm text-muted-foreground">
                                Produção restrita é o ambiente de testes do governo: as notas emitidas lá não têm validade fiscal. Troque para
                                Produção só quando a integração estiver validada.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="dps_series">Série da DPS</Label>
                                <Input
                                    id="dps_series"
                                    value={form.data.dps_series}
                                    onChange={(e) => form.setData('dps_series', e.target.value.replace(/\D/g, '').slice(0, 5))}
                                    inputMode="numeric"
                                    aria-invalid={Boolean(form.errors.dps_series)}
                                    required
                                />
                                <InputError message={form.errors.dps_series} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="next_dps_number">Próximo número</Label>
                                <Input id="next_dps_number" value={issuer?.next_dps_number ?? 1} readOnly disabled className="tabular-nums" />
                                <p className="text-sm text-muted-foreground">Avança sozinho a cada emissão.</p>
                            </div>
                        </div>
                    </FormSection>
                </fieldset>

                {canManage && (
                    <>
                        <Separator />
                        <div className="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
                            {form.recentlySuccessful && (
                                <p className="flex items-center gap-1.5 text-sm text-signed" role="status">
                                    <Check className="size-4" aria-hidden="true" />
                                    Dados salvos
                                </p>
                            )}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {issuer ? 'Salvar alterações' : 'Salvar emitente'}
                            </Button>
                        </div>
                    </>
                )}
            </form>

            <section className="flex flex-col gap-6 rounded-xl border bg-card p-5 md:p-8">
                <FormSection
                    title="Certificado digital"
                    description="e-CNPJ A1 da empresa emitente. Assina cada DPS e autentica a conexão com a Sefin Nacional. Fica guardado cifrado."
                >
                    {!issuer ? (
                        <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                            Salve os dados do emitente acima para liberar o envio do certificado.
                        </p>
                    ) : (
                        <>
                            {issuer.certificate ? (
                                <CertificateStatus certificate={issuer.certificate} />
                            ) : (
                                <div className="flex items-start gap-3 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                                    <FileKey2 className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                                    <p>Nenhum certificado enviado. Sem ele não é possível emitir notas.</p>
                                </div>
                            )}

                            {canManage && (
                                <form onSubmit={submitCertificate} className="flex flex-col gap-4 rounded-lg border bg-background/60 p-4">
                                    <p className="text-sm font-medium">{issuer.certificate ? 'Substituir certificado' : 'Enviar certificado'}</p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="certificate">Arquivo .pfx</Label>
                                            <Input
                                                ref={fileInput}
                                                id="certificate"
                                                type="file"
                                                accept=".pfx,.p12,application/x-pkcs12"
                                                onChange={(e) => certForm.setData('certificate', e.target.files?.[0] ?? null)}
                                                aria-invalid={Boolean(certForm.errors.certificate)}
                                                required
                                            />
                                            <InputError message={certForm.errors.certificate} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">Senha do certificado</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                autoComplete="off"
                                                value={certForm.data.password}
                                                onChange={(e) => certForm.setData('password', e.target.value)}
                                                aria-invalid={Boolean(certForm.errors.password)}
                                                required
                                            />
                                            <InputError message={certForm.errors.password} />
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Aceitamos só certificados da mesma empresa do emitente, matriz ou filial. A senha é necessária a cada
                                        emissão e fica cifrada junto do arquivo.
                                    </p>
                                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        {issuer.certificate ? (
                                            <ConfirmAction
                                                trigger={
                                                    <Button type="button" variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                        Remover certificado
                                                    </Button>
                                                }
                                                title="Remover o certificado?"
                                                description="O workspace deixa de conseguir emitir notas até que um novo certificado seja enviado."
                                                confirmLabel="Remover"
                                                destructive
                                                onConfirm={removeCertificate}
                                            />
                                        ) : (
                                            <span />
                                        )}
                                        <Button type="submit" disabled={certForm.processing}>
                                            {certForm.processing ? <Spinner /> : <Upload aria-hidden="true" />}
                                            {issuer.certificate ? 'Substituir' : 'Enviar certificado'}
                                        </Button>
                                    </div>
                                </form>
                            )}

                            <Separator />
                            <CertificateHistory events={certificateEvents} />
                        </>
                    )}
                </FormSection>
            </section>
        </AppLayout>
    );
}
