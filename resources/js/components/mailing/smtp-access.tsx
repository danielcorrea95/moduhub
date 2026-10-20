import { ConfirmAction } from '@/components/confirm-action';
import { CopyButton } from '@/components/copy-button';
import { CodeTabs } from '@/components/docs/code-block';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { buildSmtpSamples } from '@/lib/smtp-samples';
import { cn, formatDateTime } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { Clock, KeyRound, ShieldAlert, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import type { EmailUsageData, SendingDomainData, SmtpData } from './types';
import { UsageMeter } from './usage-meter';

/** Credenciais e dados para o sistema do cliente enviar pelo relay smtp.moduhub.com.br. */
export function SmtpAccess({ smtp, domain, usage }: { smtp: SmtpData; domain: SendingDomainData; usage: EmailUsageData }) {
    const fromAddress = `${domain.from_local_part}@${domain.domain}`;

    return (
        <div className="flex flex-col gap-8">
            {!smtp.enabled && (
                <div className="flex items-start gap-3 rounded-lg border border-pending/30 bg-pending-soft p-4 text-sm text-pending">
                    <Clock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        O servidor <span className="font-mono">{smtp.host}</span> está em implantação. Você já pode criar as credenciais e
                        deixar seu sistema configurado; avisaremos quando o envio estiver liberado.
                    </p>
                </div>
            )}

            {smtp.reveal && <CredentialReveal reveal={smtp.reveal} />}

            <Block title="Cota de envio" description="Ao atingir um dos limites, o envio para até renovar.">
                <UsageMeter usage={usage} />
            </Block>

            <Block
                title="Credenciais"
                description="Crie uma para cada sistema (site, ERP, loja). Assim você revoga uma sem parar as outras."
            >
                <Credentials smtp={smtp} />
            </Block>

            <Block title="Dados de conexão" description="O que o seu sistema pede na configuração de e-mail.">
                <dl className="divide-y rounded-lg border text-sm">
                    <ConnectionRow label="Servidor" value={smtp.host} copy />
                    <ConnectionRow label="Porta" value={smtp.ports.map((p) => `${p.port}, ${p.label}`).join(' ou ')} />
                    <ConnectionRow label="Usuário" value={smtp.reveal?.username ?? 'O usuário da credencial'} copy={Boolean(smtp.reveal)} />
                    <ConnectionRow
                        label="Senha"
                        value={smtp.reveal?.password ?? 'Mostrada uma única vez, ao criar a credencial'}
                        copy={Boolean(smtp.reveal)}
                    />
                    <ConnectionRow label="Remetente" value={`Qualquer endereço @${domain.domain}`} />
                </dl>
            </Block>

            <Block
                title="Exemplos de configuração"
                description={
                    smtp.reveal
                        ? 'Os exemplos já trazem a senha que você acabou de criar. Copie agora: ela não aparece de novo.'
                        : 'Troque SUA_SENHA pela senha mostrada quando você criou a credencial.'
                }
            >
                <CodeTabs
                    label="Linguagem do exemplo"
                    samples={buildSmtpSamples({
                        host: smtp.host,
                        username: smtp.reveal?.username ?? smtp.credentials[0]?.username ?? 'SEU_USUARIO',
                        password: smtp.reveal?.password ?? 'SUA_SENHA',
                        fromAddress,
                        fromName: domain.from_name,
                    })}
                />
            </Block>
        </div>
    );
}

function Block({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <section>
            <h3 className="text-base font-medium">{title}</h3>
            <p className="mt-0.5 mb-4 text-sm text-muted-foreground">{description}</p>
            {children}
        </section>
    );
}

function ConnectionRow({ label, value, copy = false }: { label: string; value: string; copy?: boolean }) {
    return (
        <div className="grid gap-1 px-4 py-3 sm:grid-cols-[7rem_1fr_auto] sm:items-center sm:gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('min-w-0 break-all', copy && 'font-mono')}>{value}</dd>
            {copy ? <CopyButton value={value} size="sm" variant="ghost" className="justify-self-start" /> : <span aria-hidden="true" />}
        </div>
    );
}

function CredentialReveal({ reveal }: { reveal: NonNullable<SmtpData['reveal']> }) {
    return (
        <div className="rounded-lg border border-pending/30 bg-pending-soft p-5" role="alert">
            <div className="flex items-start gap-3">
                <ShieldAlert className="mt-0.5 size-5 shrink-0 text-pending" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                    <p className="font-medium text-pending">Copie a senha de "{reveal.name}" agora</p>
                    <p className="mt-1 text-sm text-pending">Ela não será mostrada de novo. Se perder, revogue esta credencial e crie outra.</p>
                    <dl className="mt-4 divide-y divide-pending/20 rounded-md border border-pending/20 bg-card text-sm">
                        <ConnectionRow label="Usuário" value={reveal.username} copy />
                        <ConnectionRow label="Senha" value={reveal.password} copy />
                    </dl>
                </div>
            </div>
        </div>
    );
}

function Credentials({ smtp }: { smtp: SmtpData }) {
    const form = useForm({ name: '' });
    const atLimit = smtp.credentials.length >= smtp.max_credentials;

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('smtp-credentials.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <div className="flex flex-col gap-4">
            {smtp.credentials.length > 0 && (
                <ul className="divide-y rounded-lg border">
                    {smtp.credentials.map((c) => (
                        <li key={c.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <p className="font-medium">{c.name}</p>
                                <p className="mt-0.5 font-mono text-sm break-all text-muted-foreground">{c.username}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Senha terminando em {c.last_four}. {c.last_used_at ? `Último uso em ${formatDateTime(c.last_used_at)}.` : 'Ainda não usada.'}
                                    {c.created_by ? ` Criada por ${c.created_by}.` : ''}
                                </p>
                            </div>
                            <ConfirmAction
                                trigger={
                                    <Button type="button" variant="outline" size="sm" className="shrink-0">
                                        <Trash2 aria-hidden="true" />
                                        Revogar
                                    </Button>
                                }
                                title={`Revogar "${c.name}"?`}
                                description="Os sistemas que usam esta credencial deixam de conseguir enviar e-mails imediatamente."
                                confirmLabel="Revogar credencial"
                                destructive
                                onConfirm={() => router.delete(route('smtp-credentials.destroy', c.id), { preserveScroll: true })}
                            />
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="grid gap-2">
                <Label htmlFor="smtp_name">{smtp.credentials.length > 0 ? 'Nova credencial' : 'Nome da primeira credencial'}</Label>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="smtp_name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Ex.: Site, ERP, Loja virtual"
                        maxLength={60}
                        aria-invalid={Boolean(form.errors.name)}
                        className="sm:max-w-xs"
                        disabled={atLimit}
                        required
                    />
                    <Button type="submit" disabled={form.processing || atLimit}>
                        {form.processing ? <Spinner /> : <KeyRound aria-hidden="true" />}
                        Criar credencial
                    </Button>
                </div>
                {atLimit && (
                    <p className="text-sm text-muted-foreground">
                        Limite de {smtp.max_credentials} credenciais. Revogue uma que não usa mais para criar outra.
                    </p>
                )}
                <InputError message={form.errors.name} />
            </form>
        </div>
    );
}
