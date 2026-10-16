import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { SharedData, TenantRole } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';

export const ROLE_LABEL: Record<TenantRole, string> = {
    owner: 'Proprietário',
    admin: 'Administrador',
    member: 'Membro',
};

function WorkspaceMark({ name, color }: { name: string; color?: string | null }) {
    return (
        <span
            className="flex size-8 shrink-0 items-center justify-center rounded-md text-sm font-semibold text-white"
            style={{ backgroundColor: color ?? 'var(--primary)' }}
            aria-hidden="true"
        >
            {name.trim().charAt(0).toUpperCase()}
        </span>
    );
}

export function WorkspaceSwitcher() {
    const { currentTenant, workspaces } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (!currentTenant) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton asChild size="lg">
                        <Link href={route('workspaces.index')}>
                            <span className="flex size-8 items-center justify-center rounded-md border border-dashed">
                                <Plus className="size-4" />
                            </span>
                            <span className="font-medium">Criar workspace</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                        >
                            <WorkspaceMark
                                name={currentTenant.name}
                                color={currentTenant.branding?.primary_color}
                            />
                            <span className="grid flex-1 text-left leading-tight">
                                <span className="truncate font-medium text-foreground">
                                    {currentTenant.name}
                                </span>
                                {currentTenant.role && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {ROLE_LABEL[currentTenant.role]}
                                    </span>
                                )}
                            </span>
                            <ChevronsUpDown className="ml-auto size-4 text-muted-foreground" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-60"
                        align="start"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'}
                    >
                        <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                            Trocar de workspace
                        </DropdownMenuLabel>
                        {workspaces.map((w) => (
                            <DropdownMenuItem
                                key={w.id}
                                onSelect={() =>
                                    w.id !== currentTenant.id &&
                                    router.post(route('workspaces.switch', w.id), {}, { preserveScroll: true })
                                }
                                className="gap-2"
                            >
                                <span className="truncate">{w.name}</span>
                                {w.id === currentTenant.id && (
                                    <Check className="ml-auto size-4 text-primary" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={route('workspaces.index')} className="gap-2">
                                <Plus className="size-4" />
                                Criar ou gerenciar workspaces
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
