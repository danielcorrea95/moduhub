import { ConfirmAction } from '@/components/confirm-action';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { BookOpen, Check, FileKey2, ShieldAlert, ShieldCheck, ShieldX, Upload } from 'lucide-react';
import { useRef, type FormEvent } from 'react';

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
    phone: string | null;
    email: string | null;
    environment: Environment;
    dps_series: string;
    next_dps_number: number;
    ready_to_issue: boolean;
    certificate: IssuerCertificate | null;
}

type Options = Record<string, string> | string[];

interface Props {
    issuer: IssuerData | null;
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

export default function InvoicesIssuer({ issuer, canManage, options }: Props) {
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

                        <div className="grid gap-4 sm:grid-cols-[9rem_1fr_5rem]">
                            <div className="grid gap-2">
                                <Label htmlFor="city_code">Código IBGE</Label>
                                <Input
                                    id="city_code"
                                    value={form.data.city_code}
                                    onChange={(e) => form.setData('city_code', e.target.value.replace(/\D/g, '').slice(0, 7))}
                                    placeholder="4313409"
                                    inputMode="numeric"
                                    aria-invalid={Boolean(form.errors.city_code)}
                                    required
                                />
                                <InputError message={form.errors.city_code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city_name">Município</Label>
                                <Input
                                    id="city_name"
                                    value={form.data.city_name}
                                    onChange={(e) => form.setData('city_name', e.target.value)}
                                    placeholder="Novo Hamburgo"
                                    aria-invalid={Boolean(form.errors.city_name)}
                                />
                                <InputError message={form.errors.city_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="state">UF</Label>
                                <Input
                                    id="state"
                                    value={form.data.state}
                                    onChange={(e) => form.setData('state', e.target.value.toUpperCase().slice(0, 2))}
                                    placeholder="RS"
                                    maxLength={2}
                                    aria-invalid={Boolean(form.errors.state)}
                                />
                                <InputError message={form.errors.state} />
                            </div>
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
                        </>
                    )}
                </FormSection>
            </section>
        </AppLayout>
    );
}
