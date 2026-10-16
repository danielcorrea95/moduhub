import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface TenantBrandingSettings {
    primary_color?: string | null;
    logo_url?: string | null;
    support_email?: string | null;
}

interface Props {
    tenant: {
        id: string;
        name: string;
        slug: string;
        tax_id: string | null;
        branding: TenantBrandingSettings | null;
    };
    [key: string]: unknown;
}

interface WorkspaceSettingsForm {
    name: string;
    tax_id: string;
    branding: {
        primary_color: string;
        logo_url: string;
        support_email: string;
    };
}

// Mesmo azul-tinta do tema: um workspace sem cor própria continua com a cara padrão do Moduhub.
const DEFAULT_PRIMARY_COLOR = '#2338b8';
const HEX_COLOR = /^#[0-9a-f]{6}$/i;

function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-6 md:grid-cols-[14rem_1fr] md:gap-10">
            <header>
                <h2 className="text-base font-medium">{title}</h2>
                <p className="mt-1 text-sm text-muted-foreground">{description}</p>
            </header>
            <div className="flex min-w-0 flex-col gap-5">{children}</div>
        </section>
    );
}

export default function WorkspaceSettings({ tenant }: Props) {
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<WorkspaceSettingsForm>({
        name: tenant.name ?? '',
        tax_id: tenant.tax_id ?? '',
        branding: {
            primary_color: tenant.branding?.primary_color ?? DEFAULT_PRIMARY_COLOR,
            logo_url: tenant.branding?.logo_url ?? '',
            support_email: tenant.branding?.support_email ?? '',
        },
    });
    const [logoFailed, setLogoFailed] = useState(false);

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(route('workspace.update'), { preserveScroll: true });
    };

    const colorIsValid = HEX_COLOR.test(data.branding.primary_color);
    const logoUrl = data.branding.logo_url.trim();

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Workspace' }, { title: 'Configurações' }]}
            width="narrow"
        >
            <Head title="Configurações do workspace" />

            <PageHeader
                title="Configurações do workspace"
                description="Dados da empresa e a identidade visual que seus signatários veem nos e-mails e na página de assinatura."
            />

            <form onSubmit={submit} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <FormSection
                    title="Dados da empresa"
                    description="Como sua empresa é identificada no Moduhub."
                >
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nome</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tax_id">
                            CNPJ <span className="font-normal text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input
                            id="tax_id"
                            value={data.tax_id}
                            onChange={(e) => setData('tax_id', e.target.value)}
                            placeholder="00.000.000/0000-00"
                            inputMode="numeric"
                            aria-invalid={Boolean(errors.tax_id)}
                        />
                        <InputError message={errors.tax_id} />
                    </div>
                </FormSection>

                <Separator />

                <FormSection
                    title="Identidade visual"
                    description="Personalize a página de assinatura e os e-mails com a marca do seu workspace."
                >
                    <div className="grid gap-2">
                        <Label htmlFor="primary_color">Cor principal</Label>
                        <div className="flex items-center gap-3">
                            <input
                                type="color"
                                aria-label="Escolher cor principal"
                                value={colorIsValid ? data.branding.primary_color : DEFAULT_PRIMARY_COLOR}
                                onChange={(e) => setData('branding.primary_color', e.target.value)}
                                className="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-background p-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            />
                            <Input
                                id="primary_color"
                                value={data.branding.primary_color}
                                onChange={(e) => setData('branding.primary_color', e.target.value)}
                                placeholder={DEFAULT_PRIMARY_COLOR}
                                maxLength={9}
                                spellCheck={false}
                                aria-invalid={Boolean(errors['branding.primary_color'])}
                                className="w-32 font-mono"
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Usada em botões e destaques. Informe em hexadecimal, como #2338b8.
                        </p>
                        <InputError message={errors['branding.primary_color']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="logo_url">Endereço do logo</Label>
                        <Input
                            id="logo_url"
                            type="url"
                            value={data.branding.logo_url}
                            onChange={(e) => {
                                setLogoFailed(false);
                                setData('branding.logo_url', e.target.value);
                            }}
                            placeholder="https://suaempresa.com.br/logo.png"
                            spellCheck={false}
                            aria-invalid={Boolean(errors['branding.logo_url'])}
                            className="font-mono"
                        />
                        <InputError message={errors['branding.logo_url']} />
                        {logoUrl !== '' && (
                            <div className="mt-1 flex min-h-16 items-center rounded-lg border bg-background px-4 py-3">
                                {logoFailed ? (
                                    <p className="text-sm text-muted-foreground">
                                        Não conseguimos carregar a imagem deste endereço.
                                    </p>
                                ) : (
                                    <img
                                        src={logoUrl}
                                        alt="Prévia do logo"
                                        className="max-h-10 w-auto max-w-full object-contain"
                                        onError={() => setLogoFailed(true)}
                                    />
                                )}
                            </div>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="support_email">E-mail de suporte</Label>
                        <Input
                            id="support_email"
                            type="email"
                            value={data.branding.support_email}
                            onChange={(e) => setData('branding.support_email', e.target.value)}
                            placeholder="suporte@suaempresa.com.br"
                            aria-invalid={Boolean(errors['branding.support_email'])}
                        />
                        <p className="text-sm text-muted-foreground">
                            Para onde os signatários escrevem quando têm dúvidas.
                        </p>
                        <InputError message={errors['branding.support_email']} />
                    </div>
                </FormSection>

                <Separator />

                <div className="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
                    {recentlySuccessful && (
                        <p className="flex items-center gap-1.5 text-sm text-signed" role="status">
                            <Check className="size-4" aria-hidden="true" />
                            Alterações salvas
                        </p>
                    )}
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Salvar alterações
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
