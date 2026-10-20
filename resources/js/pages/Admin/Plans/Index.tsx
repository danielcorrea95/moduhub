import { PRODUCT_LABEL, quotaLabel, type CatalogPlan, type ProductKey } from '@/components/billing/types';
import { formatCents } from '@/components/dashboard/types';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface AdminPlan extends CatalogPlan {
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    combo: boolean;
    subscribers: number;
}

interface Props {
    plans: AdminPlan[];
    products: { key: ProductKey; name: string }[];
    [key: string]: unknown;
}

function situation(plan: AdminPlan): { label: string; className: string } {
    if (!plan.is_active) return { label: 'Desativado', className: 'bg-idle-soft text-idle' };
    if (!plan.is_public) return { label: 'Oculto do site', className: 'bg-pending-soft text-pending' };
    return { label: 'À venda', className: 'bg-signed-soft text-signed' };
}

export default function AdminPlansIndex({ plans, products }: Props) {
    const combos = plans.filter((p) => p.combo);

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Planos' }]}>
            <Head title="Planos" />

            <PageHeader
                title="Planos"
                description="O que aparece na página de vendas e no carrinho da assinatura. Preço novo vale para assinaturas novas; cota e excedente mudam na hora para todos."
                actions={
                    <Button asChild>
                        <Link href={route('admin.plans.create')}>
                            <Plus /> Novo plano
                        </Link>
                    </Button>
                }
            />

            {products.map((product) => {
                const rows = plans.filter((p) => !p.combo && p.product === product.key);

                return (
                    <section key={product.key} className="flex flex-col gap-3">
                        <h2 className="text-base font-medium">{PRODUCT_LABEL[product.key]}</h2>
                        {rows.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                                Nenhum plano. Sem plano à venda, o produto não aparece na página de vendas.
                            </p>
                        ) : (
                            <PlansTable rows={rows} />
                        )}
                    </section>
                );
            })}

            {combos.length > 0 && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-medium">Combos antigos</h2>
                    <p className="text-sm text-muted-foreground">
                        Planos com mais de um produto, do formato anterior ao carrinho. Ficam fora da venda e valem para quem já assina.
                    </p>
                    <PlansTable rows={combos} />
                </section>
            )}
        </AppLayout>
    );
}

function PlansTable({ rows }: { rows: AdminPlan[] }) {
    return (
        <div className="overflow-x-auto rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Plano</TableHead>
                        <TableHead className="text-right">Preço</TableHead>
                        <TableHead>Cota</TableHead>
                        <TableHead className="text-right">Excedente</TableHead>
                        <TableHead className="text-right">Assinantes</TableHead>
                        <TableHead>Situação</TableHead>
                        <TableHead>
                            <span className="sr-only">Ações</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((plan) => {
                        const s = situation(plan);
                        return (
                            <TableRow key={plan.id}>
                                <TableCell>
                                    <span className="block font-medium">{plan.name}</span>
                                    <span className="block font-mono text-xs text-muted-foreground">{plan.key}</span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{formatCents(plan.price_cents)}</TableCell>
                                <TableCell className="text-sm">
                                    {quotaLabel(plan)}
                                    {plan.daily_quota !== null && (
                                        <span className="block text-xs text-muted-foreground">
                                            até {plan.daily_quota.toLocaleString('pt-BR')} por dia
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {plan.overage_cents !== null ? formatCents(plan.overage_cents) : <span className="text-muted-foreground">bloqueia</span>}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{plan.subscribers}</TableCell>
                                <TableCell>
                                    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${s.className}`}>{s.label}</span>
                                </TableCell>
                                <TableCell className="text-right">
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={route('admin.plans.edit', plan.id)}>Editar</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
