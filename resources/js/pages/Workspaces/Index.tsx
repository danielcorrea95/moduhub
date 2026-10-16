import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ROLE_LABEL } from '@/components/workspace-switcher';
import AppLayout from '@/layouts/app-layout';
import type { SharedData, WorkspaceSummary } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Plus } from 'lucide-react';
import type { FormEvent } from 'react';

interface Props {
    workspaces: WorkspaceSummary[];
    [key: string]: unknown;
}

interface CreateWorkspaceForm {
    name: string;
}

export default function WorkspacesIndex({ workspaces }: Props) {
    const { currentTenant } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm<CreateWorkspaceForm>({
        name: '',
    });

    const create = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('workspaces.store'), { onSuccess: () => reset() });
    };

    const switchTo = (id: string) => router.post(route('workspaces.switch', id));

    return (
        <AppLayout breadcrumbs={[{ title: 'Workspaces' }]} width="narrow">
            <Head title="Workspaces" />

            <PageHeader
                title="Workspaces"
                description="Cada workspace tem seus próprios documentos, membros e integrações. Troque entre eles quando precisar."
            />

            {workspaces.length > 0 && (
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <h2 className="border-b px-5 py-3.5 text-base font-medium md:px-6">
                        Seus workspaces
                    </h2>
                    <ul className="divide-y">
                        {workspaces.map((w) => {
                            const isCurrent = currentTenant?.id === w.id;
                            return (
                                <li
                                    key={w.id}
                                    className="flex items-center justify-between gap-4 px-5 py-4 md:px-6"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{w.name}</p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {ROLE_LABEL[w.role]}
                                        </p>
                                    </div>
                                    {isCurrent ? (
                                        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-signed-soft px-2 py-0.5 text-xs font-semibold text-signed">
                                            <Check className="size-3.5" aria-hidden="true" />
                                            Em uso
                                        </span>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => switchTo(w.id)}
                                            aria-label={`Entrar no workspace ${w.name}`}
                                        >
                                            Entrar
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            <section className="rounded-xl border bg-card p-5 md:p-6">
                <h2 className="text-base font-medium">
                    {workspaces.length === 0 ? 'Crie seu primeiro workspace' : 'Criar novo workspace'}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Use o nome da empresa ou da equipe. Você poderá convidar pessoas depois.
                </p>

                <form onSubmit={create} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="name">Nome do workspace</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Ex.: Silva & Associados"
                            aria-invalid={Boolean(errors.name)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <Button type="submit" disabled={processing} className="sm:mt-[1.375rem]">
                        {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                        Criar workspace
                    </Button>
                </form>
            </section>
        </AppLayout>
    );
}
