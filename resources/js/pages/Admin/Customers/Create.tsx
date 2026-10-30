import { ComplimentaryPlanPicker } from '@/components/admin/complimentary-plan-picker';
import type { ComplimentaryPlan } from '@/components/admin/customer-types';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    plans: ComplimentaryPlan[];
    [key: string]: unknown;
}

export default function AdminCustomerCreate({ plans }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        workspace_name: '',
        tax_id: '',
        owner_name: '',
        owner_email: '',
        plan_ids: [] as number[],
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('admin.customers.store'));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin' }, { title: 'Clientes', href: route('admin.customers.index') }, { title: 'Novo' }]} width="narrow">
            <Head title="Novo cliente" />

            <PageHeader
                title="Novo cliente"
                description="O workspace já nasce em cortesia: sem teste grátis, sem mensalidade e sem cobrança de excedente."
            />

            <form onSubmit={submit} className="flex flex-col gap-8 rounded-xl border bg-card p-5 md:p-8">
                <FormSection title="Workspace" description="Como o cliente aparece no painel e nos e-mails.">
                    <div className="grid gap-2">
                        <Label htmlFor="workspace_name">Nome</Label>
                        <Input id="workspace_name" value={data.workspace_name} onChange={(e) => setData('workspace_name', e.target.value)} placeholder="Auren" required />
                        <InputError message={errors.workspace_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tax_id">
                            CNPJ ou CPF <span className="font-normal text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input id="tax_id" value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} inputMode="numeric" />
                        <InputError message={errors.tax_id} />
                    </div>
                </FormSection>

                <Separator />

                <FormSection
                    title="Proprietário"
                    description="Se o e-mail já tem conta, o workspace aparece na troca de workspace dela. Se não tem, a conta é criada e a pessoa recebe um link para criar a senha."
                >
                    <div className="grid gap-2">
                        <Label htmlFor="owner_name">Nome</Label>
                        <Input id="owner_name" value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)} autoComplete="off" required />
                        <InputError message={errors.owner_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="owner_email">E-mail</Label>
                        <Input id="owner_email" type="email" value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)} autoComplete="off" required />
                        <InputError message={errors.owner_email} />
                    </div>
                </FormSection>

                <Separator />

                <FormSection
                    title="Produtos"
                    description="Um plano por produto. Na cortesia o plano só define as cotas. Para uso sem limite, crie em Planos um plano oculto do site com a cota em branco."
                >
                    <ComplimentaryPlanPicker plans={plans} selected={data.plan_ids} onChange={(ids) => setData('plan_ids', ids)} />
                    <InputError message={errors.plan_ids} />
                </FormSection>

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={route('admin.customers.index')}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing || data.plan_ids.length === 0}>
                        {processing && <Spinner />} Cadastrar cliente
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
