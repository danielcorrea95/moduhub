import { PaymentForm } from '@/components/billing/payment-form';
import { PixDialog } from '@/components/billing/pix-dialog';
import {
    PAYMENT_STATUS,
    SUBSCRIPTION_STATUS,
    type BillingAccountData,
    PRODUCT_LABEL,
    PRODUCT_UNIT,
    quotaLabel,
    type BillingPaymentRow,
    type BillingSubscription,
    type CatalogProduct,
} from '@/components/billing/types';
import { usePlanCart } from '@/components/billing/use-plan-cart';
import { ConfirmAction } from '@/components/confirm-action';
import { formatCents } from '@/components/dashboard/types';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn, formatDate } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { Check, CreditCard, ExternalLink, QrCode } from 'lucide-react';
import { useState } from 'react';

interface Props {
    subscription: BillingSubscription | null;
    products: CatalogProduct[];
    account: BillingAccountData;
    payments: BillingPaymentRow[];
    grace_days: number;
    /** Carrinho montado na página de vendas. */
    preselected_plan_ids: number[];
    [key: string]: unknown;
}

const TONE_CLASSES = {
    signed: 'bg-signed-soft text-signed',
    pending: 'bg-pending-soft text-pending',
    rejected: 'bg-rejected-soft text-rejected',
    sent: 'bg-sent-soft text-sent',
    idle: 'bg-idle-soft text-idle',
};

function Badge({ label, tone }: { label: string; tone: keyof typeof TONE_CLASSES }) {
    return (
        <span className={cn('inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium', TONE_CLASSES[tone])}>
            {label}
        </span>
    );
}

export default function BillingShow({ subscription, products, account, payments, grace_days, preselected_plan_ids }: Props) {
    // Assinatura viva no Asaas: troca de produtos e de forma de pagamento. Senão, o formulário de assinar.
    const subscribed = Boolean(subscription?.has_gateway_subscription) && subscription?.status !== 'cancelled';
    const currentIds = (subscription?.plans ?? []).map((p) => p.id);
    const cart = usePlanCart(products, preselected_plan_ids.length > 0 ? preselected_plan_ids : currentIds);
    const [changingMethod, setChangingMethod] = useState(false);

    // Quem já assina continua pagando o preço contratado nos planos que mantém.
    const priceFor = (planId: number) => (subscribed ? subscription?.plans.find((p) => p.id === planId)?.price_cents : undefined);
    const cartTotal = cart.plans.reduce((sum, p) => sum + (priceFor(p.id) ?? p.price_cents), 0);
    const cartChanged = [...cart.ids].sort().join() !== [...currentIds].sort().join();

    const savePlans = () => router.patch(route('billing.plan'), { plan_ids: cart.ids }, { preserveScroll: true });
    const cancel = () => router.delete(route('billing.cancel'), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={[{ title: 'Workspace' }, { title: 'Assinatura' }]}>
            <Head title="Assinatura" />

            <PageHeader
                title="Assinatura"
                description="Seu plano na Moduhub, a forma de pagamento e as cobranças. Pagamentos processados pelo Asaas."
            />

            {subscription && <StatusPanel subscription={subscription} graceDays={grace_days} />}

            <section aria-labelledby="plans-title" className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                <div className="flex flex-col gap-8">
                    <header>
                        <h2 id="plans-title" className="text-base font-medium">
                            {subscribed ? 'Seus produtos' : 'Monte sua assinatura'}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Escolha um plano de cada produto que quiser usar. Tudo vem numa mensalidade só.
                        </p>
                    </header>
                    {products.map((product) => (
                        <ProductPicker
                            key={product.key}
                            product={product}
                            selectedId={cart.selected[product.key] ?? null}
                            onSelect={(id) => cart.toggle(product.key, id)}
                        />
                    ))}
                </div>

                <aside className="flex flex-col gap-4 rounded-xl border bg-card p-5 lg:sticky lg:top-6">
                    <h3 className="text-sm font-medium">Resumo</h3>
                    {cart.plans.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Nenhum plano escolhido.</p>
                    ) : (
                        <dl className="flex flex-col gap-2 text-sm">
                            {cart.plans.map((plan) => (
                                <div key={plan.id} className="flex items-baseline justify-between gap-3">
                                    <dt>
                                        <span className="block">{plan.name}</span>
                                        <span className="block text-xs text-muted-foreground">{PRODUCT_LABEL[plan.product]}</span>
                                    </dt>
                                    <dd className="tabular-nums">{formatCents(priceFor(plan.id) ?? plan.price_cents)}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    <p className="flex items-baseline justify-between border-t pt-3">
                        <span className="text-sm font-medium">Total por mês</span>
                        <span className="text-xl font-semibold tabular-nums">{formatCents(cartTotal)}</span>
                    </p>
                    {subscribed && (
                        <ConfirmAction
                            trigger={
                                <Button disabled={!cartChanged || cart.plans.length === 0}>Salvar alterações</Button>
                            }
                            title="Alterar os produtos da assinatura?"
                            description={`Os produtos mudam agora. A mensalidade passa a ser ${formatCents(cartTotal)} a partir da próxima cobrança em aberto, sem valor proporcional.`}
                            confirmLabel="Alterar assinatura"
                            onConfirm={savePlans}
                        />
                    )}
                    {subscribed && cartChanged && cart.plans.length === 0 && (
                        <p className="text-xs text-muted-foreground">Para sair de todos os produtos, cancele a assinatura no fim da página.</p>
                    )}
                </aside>
            </section>

            {!subscribed && cart.plans.length > 0 && (
                <section className="rounded-xl border bg-card p-5 md:p-8">
                    <h2 className="mb-6 text-base font-medium">Pagamento</h2>
                    <PaymentForm
                        mode="subscribe"
                        planIds={cart.ids}
                        account={account}
                        defaultType="CREDIT_CARD"
                        submitLabel={`Assinar por ${formatCents(cart.totalCents)}/mês`}
                        footnote={
                            subscription?.status === 'trial' && subscription.trial_ends_at
                                ? `Nada é cobrado agora: a primeira mensalidade vence em ${formatDate(subscription.trial_ends_at)}, no fim do teste grátis.`
                                : 'A primeira mensalidade vence hoje. O acesso é liberado quando o pagamento for confirmado.'
                        }
                    />
                </section>
            )}

            {subscribed && subscription && (
                <section className="flex flex-col gap-4 rounded-xl border bg-card p-5 md:p-8">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-base font-medium">Forma de pagamento</h2>
                            <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                {subscription.billing_type === 'CREDIT_CARD' ? (
                                    <>
                                        <CreditCard className="size-4" />
                                        {subscription.card_brand ?? 'Cartão'} final {subscription.card_last4 ?? '••••'}, cobrado automaticamente todo mês
                                    </>
                                ) : (
                                    <>
                                        <QrCode className="size-4" /> Pix, com a cobrança enviada por e-mail todo mês
                                    </>
                                )}
                            </p>
                        </div>
                        {!changingMethod && (
                            <Button variant="outline" onClick={() => setChangingMethod(true)}>
                                {subscription.billing_type === 'CREDIT_CARD' ? 'Trocar cartão ou forma' : 'Mudar forma de pagamento'}
                            </Button>
                        )}
                    </div>
                    {changingMethod && (
                        <div className="border-t pt-6">
                            <PaymentForm
                                mode="change"
                                account={account}
                                defaultType={subscription.billing_type ?? 'CREDIT_CARD'}
                                submitLabel="Salvar forma de pagamento"
                                footnote="Vale também para as cobranças em aberto. Trocar o cartão não gera cobrança na hora."
                                onDone={() => setChangingMethod(false)}
                            />
                            <Button variant="ghost" className="mt-2" onClick={() => setChangingMethod(false)}>
                                Cancelar
                            </Button>
                        </div>
                    )}
                </section>
            )}

            <section aria-labelledby="payments-title" className="flex flex-col gap-4">
                <h2 id="payments-title" className="text-base font-medium">
                    Cobranças
                </h2>
                {payments.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Nenhuma cobrança ainda. Elas aparecem aqui assim que o Asaas gerar a primeira.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Descrição</TableHead>
                                    <TableHead>Vencimento</TableHead>
                                    <TableHead className="text-right">Valor</TableHead>
                                    <TableHead>Situação</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Ações</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell>
                                            <span className="block">{p.description ?? (p.kind === 'overage' ? 'Excedente' : 'Mensalidade')}</span>
                                            {p.lines && p.lines.length > 0 && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {p.lines.map((l) => l.label).join(' · ')}
                                                </span>
                                            )}
                                            {p.failure_reason && p.status !== 'paid' && (
                                                <span className="block text-xs text-rejected">{p.failure_reason}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{formatDate(p.due_date)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatCents(p.value_cents)}</TableCell>
                                        <TableCell>
                                            <Badge {...PAYMENT_STATUS[p.status]} />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {p.has_pix && <PixDialog paymentId={p.id} amount={formatCents(p.value_cents)} />}
                                                {p.invoice_url && (
                                                    <Button asChild size="sm" variant="ghost">
                                                        <a href={p.invoice_url} target="_blank" rel="noreferrer">
                                                            {p.status === 'pending' || p.status === 'overdue' ? 'Pagar fatura' : 'Ver fatura'}
                                                            <ExternalLink />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>

            {subscribed && (
                <section className="flex flex-col gap-3 rounded-xl border border-destructive/30 p-5">
                    <h2 className="text-base font-medium">Cancelar assinatura</h2>
                    <p className="text-sm text-muted-foreground">
                        As mensalidades futuras deixam de ser cobradas. O workspace segue liberado até o fim do período pago e
                        depois fica só para consulta. O excedente do período ainda é cobrado no fechamento.
                    </p>
                    <ConfirmAction
                        trigger={
                            <Button variant="outline" className="self-start text-destructive">
                                Cancelar assinatura
                            </Button>
                        }
                        title="Cancelar a assinatura?"
                        description="As cobranças em aberto da mensalidade são canceladas no Asaas. Para voltar depois, é só assinar de novo."
                        confirmLabel="Cancelar assinatura"
                        cancelLabel="Manter assinatura"
                        destructive
                        onConfirm={cancel}
                    />
                </section>
            )}
        </AppLayout>
    );
}

function StatusPanel({ subscription: s, graceDays }: { subscription: BillingSubscription; graceDays: number }) {
    const status = SUBSCRIPTION_STATUS[s.status];

    const detail = (() => {
        switch (s.status) {
            case 'trial':
                return s.has_gateway_subscription
                    ? `Teste grátis até ${formatDate(s.trial_ends_at)}. A primeira mensalidade vence nesse dia.`
                    : `Teste grátis até ${formatDate(s.trial_ends_at)}. Assine antes disso para não perder o acesso.`;
            case 'incomplete':
                return 'Aguardando a primeira mensalidade. O workspace está só para consulta até o pagamento ser confirmado.';
            case 'active':
                return `Ciclo atual de ${formatDate(s.current_period_start)} a ${formatDate(s.current_period_end)}.`;
            case 'past_due':
                return `Há cobrança vencida desde ${formatDate(s.past_due_since)}. Sem pagamento até ${formatDate(s.grace_ends_at)} (${graceDays} dias de carência), o workspace fica só para consulta.`;
            case 'suspended':
                return s.has_gateway_subscription
                    ? 'Bloqueado por falta de pagamento: dá para consultar, mas não criar nem alterar. Pague a cobrança vencida abaixo para liberar na hora.'
                    : 'O teste grátis acabou. Escolha um plano para voltar a criar e editar.';
            case 'cancelled':
                return s.blocked
                    ? 'Assinatura cancelada. O workspace está só para consulta. Assine de novo para voltar a usar.'
                    : `Assinatura cancelada. O workspace segue liberado até ${formatDate(s.current_period_end)}.`;
        }
    })();

    return (
        <section
            className={cn(
                'flex flex-col gap-2 rounded-xl border p-5',
                (s.status === 'past_due' || s.blocked) && 'border-destructive/40 bg-rejected-soft/40',
            )}
        >
            <div className="flex flex-wrap items-center gap-3">
                <h2 className="text-base font-medium">{s.plans.length > 0 ? s.plans.map((p) => p.name).join(', ') : 'Sem plano'}</h2>
                <Badge {...status} />
                {s.plans.length > 0 && <span className="text-sm text-muted-foreground tabular-nums">{formatCents(s.price_cents)}/mês</span>}
            </div>
            <p className="text-sm text-muted-foreground">{detail}</p>
        </section>
    );
}

function ProductPicker({
    product,
    selectedId,
    onSelect,
}: {
    product: CatalogProduct;
    selectedId: number | null;
    onSelect: (id: number | null) => void;
}) {
    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-3">
                <span className="block font-medium">{PRODUCT_LABEL[product.key]}</span>
                {product.description && <span className="block text-sm text-muted-foreground">{product.description}</span>}
            </legend>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {product.plans.map((plan) => {
                    const on = selectedId === plan.id;
                    return (
                        <button
                            key={plan.id}
                            type="button"
                            role="checkbox"
                            aria-checked={on}
                            onClick={() => onSelect(plan.id)}
                            className={cn(
                                'flex flex-col gap-1 rounded-lg border bg-card p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                on ? 'border-primary ring-1 ring-primary' : 'hover:bg-muted/40',
                            )}
                        >
                            <span className="flex items-center justify-between gap-2">
                                <span className="text-sm font-medium">{plan.name}</span>
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'flex size-4 items-center justify-center rounded-sm border',
                                        on ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                                    )}
                                >
                                    {on && <Check className="size-3" />}
                                </span>
                            </span>
                            <span className="text-lg font-semibold tabular-nums">
                                {formatCents(plan.price_cents)}
                                <span className="text-xs font-normal text-muted-foreground">/mês</span>
                            </span>
                            <span className="text-xs text-muted-foreground">{quotaLabel(plan)}</span>
                            {plan.overage_cents !== null && (
                                <span className="text-xs text-muted-foreground">
                                    {formatCents(plan.overage_cents)} por {PRODUCT_UNIT[plan.product][0]} além disso
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}
