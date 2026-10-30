import { ComplimentaryPlanPicker } from '@/components/admin/complimentary-plan-picker';
import type { AdminCustomer, ComplimentaryPlan } from '@/components/admin/customer-types';
import { StatusBadge } from '@/components/admin/status-badge';
import { SUBSCRIPTION_STATUS } from '@/components/billing/types';
import { ConfirmAction } from '@/components/confirm-action';
import { formatCents } from '@/components/dashboard/types';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    customer: AdminCustomer;
    plans: ComplimentaryPlan[];
    endingTrialDays: number;
    [key: string]: unknown;
}

export default function AdminCustomerEdit({ customer, plans, endingTrialDays }: Props) {
    const s = customer.subscription;
    const complimentary = s?.status === 'complimentary';
    // Assinatura viva no Asaas seguiria cobrando: o cliente precisa cancelar antes.
    const paying = Boolean(s?.has_gateway_subscription) && s?.status !== 'cancelled';
    const { errors: pageErrors } = usePage().props as { errors: Record<string, string> };

    const { data, setData, put, processing, errors, isDirty } = useForm({
        plan_ids: complimentary ? s!.plans.map((p) => p.id) : ([] as number[]),
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(route('admin.customers.complimentary.grant', customer.id), { preserveScroll: true });
    };

    const end = () => router.delete(route('admin.customers.complimentary.end', customer.id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Clientes', href: route('admin.customers.index') }, { title: customer.name }]} width="narrow">
            <Head title={customer.name} />

            <PageHeader title={customer.name} description={`Workspace criado em ${formatDate(customer.created_at)}.`} />

            <section className="rounded-xl border bg-card p-5">
                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">CNPJ ou CPF</dt>
                        <dd className="tabular-nums">{customer.tax_id || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Proprietário</dt>
                        <dd>
                            {customer.owners.length === 0
                                ? '—'
                                : customer.owners.map((o) => (
                                      <span key={o.email} className="block">
                                          {o.name} <span className="text-muted-foreground">({o.email})</span>
                                      </span>
                                  ))}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Situação</dt>
                        <dd className="mt-0.5 flex flex-wrap items-center gap-2">
                            {s ? <StatusBadge {...SUBSCRIPTION_STATUS[s.status]} /> : 'Sem assinatura'}
                            {s?.status === 'trial' && <span className="text-muted-foreground">até {formatDate(s.trial_ends_at)}</span>}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Planos</dt>
                        <dd>
                            {s?.plans.length ? s.plans.map((p) => p.name).join(', ') : '—'}
                            {s && !complimentary && s.price_cents > 0 && (
                                <span className="block text-muted-foreground tabular-nums">{formatCents(s.price_cents)}/mês</span>
                            )}
                        </dd>
                    </div>
                </dl>
            </section>

            <section className="flex flex-col gap-5 rounded-xl border bg-card p-5 md:p-8">
                <header>
                    <h2 className="text-base font-medium">Cortesia</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {complimentary
                            ? 'Este workspace usa sem pagar. Trocar os planos muda os produtos e as cotas na hora.'
                            : 'Sem teste grátis, sem mensalidade e sem cobrança de excedente. O cliente não vê o formulário de assinar.'}
                    </p>
                </header>

                {paying ? (
                    <p className="rounded-lg bg-pending-soft px-4 py-3 text-sm text-pending">
                        Este workspace tem assinatura paga no Asaas. Para dar cortesia, o cliente cancela a assinatura antes, na tela Assinatura.
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <ComplimentaryPlanPicker plans={plans} selected={data.plan_ids} onChange={(ids) => setData('plan_ids', ids)} />
                        <InputError message={errors.plan_ids} />
                        <Button type="submit" className="self-start" disabled={processing || data.plan_ids.length === 0 || (complimentary && !isDirty)}>
                            {processing && <Spinner />}
                            {complimentary ? 'Salvar planos' : 'Dar cortesia'}
                        </Button>
                    </form>
                )}
            </section>

            {complimentary && (
                <section className="flex flex-col gap-3 rounded-xl border border-destructive/30 p-5">
                    <h2 className="text-base font-medium">Encerrar cortesia</h2>
                    <p className="text-sm text-muted-foreground">
                        Os produtos continuam ligados num teste grátis de {endingTrialDays} dias, com o aviso no topo do painel do cliente. Sem assinar até lá, o workspace fica
                        só para consulta.
                    </p>
                    <InputError message={pageErrors.subscription} />
                    <ConfirmAction
                        trigger={
                            <Button variant="outline" className="self-start text-destructive">
                                Encerrar cortesia
                            </Button>
                        }
                        title={`Encerrar a cortesia de ${customer.name}?`}
                        description={`O cliente passa a ter ${endingTrialDays} dias de teste grátis para assinar um plano.`}
                        confirmLabel="Encerrar cortesia"
                        destructive
                        onConfirm={end}
                    />
                </section>
            )}
        </AppLayout>
    );
}
