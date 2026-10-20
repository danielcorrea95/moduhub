import type { ProductKey } from '@/components/billing/types';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface EditablePlan {
    id: number;
    key: string;
    name: string;
    description: string | null;
    price_cents: number;
    trial_days: number;
    product: ProductKey;
    included_quota: number | null;
    daily_quota: number | null;
    overage_cents: number | null;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    subscribers: number;
}

interface Props {
    plan: EditablePlan | null;
    products: { key: ProductKey; name: string }[];
    [key: string]: unknown;
}

/** 2490 → "24,90" para o campo de preço. */
const reais = (cents: number | null) => (cents === null ? '' : (cents / 100).toFixed(2).replace('.', ','));

export default function AdminPlanForm({ plan, products }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        product: plan?.product ?? products[0].key,
        name: plan?.name ?? '',
        key: plan?.key ?? '',
        description: plan?.description ?? '',
        price: reais(plan?.price_cents ?? null),
        trial_days: String(plan?.trial_days ?? 14),
        included_quota: plan?.included_quota?.toString() ?? '',
        daily_quota: plan?.daily_quota?.toString() ?? '',
        overage: reais(plan?.overage_cents ?? null),
        sort_order: String(plan?.sort_order ?? 0),
        is_active: plan?.is_active ?? true,
        is_public: plan?.is_public ?? true,
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (plan) put(route('admin.plans.update', plan.id));
        else post(route('admin.plans.store'));
    };

    const locked = (plan?.subscribers ?? 0) > 0;
    // O backend valida em centavos (price_cents/overage_cents); os campos são em reais.
    const serverErrors = errors as Record<string, string | undefined>;

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Planos', href: route('admin.plans.index') }, { title: plan ? plan.name : 'Novo' }]} width="narrow">
            <Head title={plan ? `Plano ${plan.name}` : 'Novo plano'} />

            <PageHeader
                title={plan ? `Plano ${plan.name}` : 'Novo plano'}
                description={
                    locked
                        ? `${plan!.subscribers} assinatura(s) usam este plano. Um preço novo vale só para quem assinar daqui em diante; cota e excedente mudam na hora para todos.`
                        : 'Cada plano é de um produto. O cliente monta a assinatura escolhendo um plano de cada produto.'
                }
            />

            <form onSubmit={submit} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <FormSection title="Produto e nome" description="Como o plano aparece na página de vendas e no carrinho.">
                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">Produto</legend>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {products.map((p) => (
                                <button
                                    key={p.key}
                                    type="button"
                                    role="radio"
                                    aria-checked={data.product === p.key}
                                    disabled={locked && data.product !== p.key}
                                    onClick={() => setData('product', p.key)}
                                    className={cn(
                                        'rounded-lg border px-3 py-2.5 text-left text-sm transition-colors disabled:opacity-40',
                                        data.product === p.key ? 'border-primary bg-primary/5 font-medium' : 'hover:bg-muted/50',
                                    )}
                                >
                                    {p.name}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.product} />
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Nome</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="NFS-e 100" required />
                        <InputError message={errors.name} />
                    </div>

                    {!plan && (
                        <div className="grid gap-2">
                            <Label htmlFor="key">
                                Chave <span className="font-normal text-muted-foreground">(opcional)</span>
                            </Label>
                            <Input id="key" value={data.key} onChange={(e) => setData('key', e.target.value)} placeholder="nfse-100" className="font-mono" />
                            <p className="text-xs text-muted-foreground">Vai no link do site (?plans=nfse-100). Em branco, é gerada pelo nome. Não muda depois.</p>
                            <InputError message={errors.key} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="description">Descrição</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} />
                        <InputError message={errors.description} />
                    </div>
                </FormSection>

                <Separator />

                <FormSection title="Preço e cota" description="Cota em branco é sem limite. Excedente em branco faz a cota virar limite.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="price">Mensalidade (R$)</Label>
                            <Input id="price" value={data.price} onChange={(e) => setData('price', e.target.value)} inputMode="decimal" placeholder="79,00" required />
                            <InputError message={serverErrors.price_cents} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="trial_days">Dias de teste grátis</Label>
                            <Input id="trial_days" value={data.trial_days} onChange={(e) => setData('trial_days', e.target.value)} inputMode="numeric" />
                            <InputError message={errors.trial_days} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="included_quota">Cota por mês</Label>
                            <Input id="included_quota" value={data.included_quota} onChange={(e) => setData('included_quota', e.target.value)} inputMode="numeric" placeholder="sem limite" />
                            <InputError message={errors.included_quota} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="overage">Excedente por unidade (R$)</Label>
                            <Input id="overage" value={data.overage} onChange={(e) => setData('overage', e.target.value)} inputMode="decimal" placeholder="bloqueia ao passar" />
                            <InputError message={serverErrors.overage_cents} />
                        </div>
                        {data.product === 'mailing' && (
                            <div className="grid gap-2">
                                <Label htmlFor="daily_quota">Cota por dia</Label>
                                <Input id="daily_quota" value={data.daily_quota} onChange={(e) => setData('daily_quota', e.target.value)} inputMode="numeric" placeholder="sem limite" />
                                <InputError message={errors.daily_quota} />
                            </div>
                        )}
                    </div>
                </FormSection>

                <Separator />

                <FormSection title="Venda" description="Oculto do site continua valendo para quem já assina. Desativado não pode ser contratado.">
                    <label className="flex items-center justify-between gap-4">
                        <span className="text-sm">À venda na página e no carrinho</span>
                        <Switch checked={data.is_public} onCheckedChange={(v) => setData('is_public', v)} />
                    </label>
                    <label className="flex items-center justify-between gap-4">
                        <span className="text-sm">Ativo</span>
                        <Switch checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="sort_order">Ordem na lista</Label>
                        <Input id="sort_order" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} inputMode="numeric" className="w-28" />
                        <InputError message={errors.sort_order} />
                    </div>
                </FormSection>

                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={route('admin.plans.index')}>Voltar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {plan ? 'Salvar plano' : 'Criar plano'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
