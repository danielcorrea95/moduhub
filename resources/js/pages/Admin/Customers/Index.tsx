import type { AdminCustomer } from '@/components/admin/customer-types';
import { SUBSCRIPTION_STATUS } from '@/components/billing/types';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatusBadge } from '@/components/admin/status-badge';

interface Props {
    customers: Paginated<AdminCustomer>;
    search: string | null;
    [key: string]: unknown;
}

export default function AdminCustomersIndex({ customers, search: initialSearch }: Props) {
    const [search, setSearch] = useState(initialSearch ?? '');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(
            () => router.get(route('admin.customers.index'), search.trim() ? { busca: search.trim() } : {}, { preserveState: true, replace: true }),
            300,
        );
        return () => clearTimeout(t);
    }, [search]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Clientes' }]}>
            <Head title="Clientes" />

            <PageHeader
                title="Clientes"
                description="Todos os workspaces da plataforma. Cadastre aqui quem usa em cortesia, sem passar pela página de vendas nem pelo Asaas."
                actions={
                    <Button asChild>
                        <Link href={route('admin.customers.create')}>
                            <Plus /> Novo cliente
                        </Link>
                    </Button>
                }
            />

            <div className="relative sm:w-80">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Nome, CNPJ ou e-mail do dono"
                    aria-label="Buscar cliente"
                    className="h-10 bg-card pl-9"
                />
            </div>

            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {customers.data.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title={search ? 'Nenhum cliente encontrado' : 'Nenhum workspace ainda'}
                        description={search ? 'Tente outro nome, CNPJ ou e-mail.' : undefined}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Workspace</TableHead>
                                    <TableHead>Proprietário</TableHead>
                                    <TableHead>Planos</TableHead>
                                    <TableHead>Situação</TableHead>
                                    <TableHead>Criado em</TableHead>
                                    <TableHead>
                                        <span className="sr-only">Ações</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.data.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell>
                                            <span className="block font-medium">{c.name}</span>
                                            {c.tax_id && <span className="block text-xs text-muted-foreground tabular-nums">{c.tax_id}</span>}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {c.owners.length === 0 ? (
                                                <span className="text-muted-foreground">—</span>
                                            ) : (
                                                c.owners.map((o) => (
                                                    <span key={o.email} className="block">
                                                        {o.name}
                                                        <span className="block text-xs text-muted-foreground">{o.email}</span>
                                                    </span>
                                                ))
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {c.subscription?.plans.length ? c.subscription.plans.map((p) => p.name).join(', ') : <span className="text-muted-foreground">—</span>}
                                        </TableCell>
                                        <TableCell>
                                            {c.subscription ? <StatusBadge {...SUBSCRIPTION_STATUS[c.subscription.status]} /> : <span className="text-sm text-muted-foreground">—</span>}
                                        </TableCell>
                                        <TableCell className="text-sm tabular-nums">{formatDate(c.created_at)}</TableCell>
                                        <TableCell className="text-right">
                                            <Button asChild size="sm" variant="ghost">
                                                <Link href={route('admin.customers.edit', c.id)}>Abrir</Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>

            <Pagination page={customers} />
        </AppLayout>
    );
}
