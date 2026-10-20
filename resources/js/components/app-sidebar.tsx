import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { PlanUsage } from '@/components/plan-usage';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import type { NavGroup, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AtSign,
    BookOpen,
    Building2, Inbox, MapPinned,
    FileSignature,
    House,
    KeyRound,
    ReceiptText,
    Send,
    Settings,
    UsersRound,
    Webhook,
} from 'lucide-react';

function useNavGroups(): NavGroup[] {
    const { currentTenant } = usePage<SharedData>().props;
    const current = (name: string) => route().current(name);

    const groups: NavGroup[] = [
        {
            title: '',
            items: [
                {
                    title: 'Início',
                    href: route('dashboard'),
                    icon: House,
                    isActive: current('dashboard'),
                },
            ],
        },
    ];

    if (!currentTenant) return groups;

    if (currentTenant.modules.includes('signatures')) {
        groups.push({
            title: 'Assinaturas',
            items: [
                {
                    title: 'Documentos',
                    href: route('signatures.index'),
                    icon: FileSignature,
                    isActive: current('signatures.*'),
                },
            ],
        });
    }

    if (currentTenant.modules.includes('invoices')) {
        groups.push({
            title: 'Notas fiscais',
            items: [
                {
                    title: 'Notas',
                    href: route('invoices.index'),
                    icon: ReceiptText,
                    isActive: current('invoices.index') || current('invoices.create') || current('invoices.show'),
                },
                {
                    title: 'Emitente',
                    href: route('invoices.issuer.edit'),
                    icon: Building2,
                    isActive: current('invoices.issuer.*'),
                },
                {
                    title: 'Recebidas',
                    href: route('invoices.received.index'),
                    icon: Inbox,
                    isActive: current('invoices.received.*'),
                },
                {
                    title: 'Municípios',
                    href: route('invoices.agreements'),
                    icon: MapPinned,
                    isActive: current('invoices.agreements'),
                },
            ],
        });
    }

    if (currentTenant.role === 'owner' || currentTenant.role === 'admin') {
        groups.push(
            {
                title: 'Workspace',
                items: [
                    {
                        title: 'Membros',
                        href: route('workspace.members'),
                        icon: UsersRound,
                        isActive: current('workspace.members'),
                    },
                    {
                        title: 'Configurações',
                        href: route('workspace.settings'),
                        icon: Settings,
                        isActive: current('workspace.settings'),
                    },
                ],
            },
            {
                title: 'E-mail',
                items: [
                    {
                        title: 'Enviados',
                        href: route('emails.overview'),
                        icon: Send,
                        isActive: current('emails.*'),
                    },
                    {
                        title: 'Domínio de envio',
                        href: route('sending-domain.show'),
                        icon: AtSign,
                        isActive: current('sending-domain.*'),
                    },
                ],
            },
            {
                title: 'Integrações',
                items: [
                    {
                        title: 'Chaves de API',
                        href: route('api-keys.index'),
                        icon: KeyRound,
                        isActive: current('api-keys.*'),
                    },
                    {
                        title: 'Webhooks',
                        href: route('webhooks.index'),
                        icon: Webhook,
                        isActive: current('webhooks.*'),
                    },
                    {
                        title: 'Documentação da API',
                        href: route('docs.api'),
                        icon: BookOpen,
                        isActive: false,
                    },
                ],
            },
        );
    }

    return groups;
}

export function AppSidebar() {
    const groups = useNavGroups();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-3 px-3 pt-4">
                <Link
                    href={route('dashboard')}
                    className="flex h-8 items-center px-1 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
                >
                    <AppLogo className="group-data-[collapsible=icon]:[&>span:last-child]:hidden" />
                </Link>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent className="px-1">
                {groups.map((group) => (
                    <SidebarGroup key={group.title || 'root'}>
                        {group.title && (
                            <SidebarGroupLabel className="text-xs font-normal text-muted-foreground">
                                {group.title}
                            </SidebarGroupLabel>
                        )}
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={item.isActive}
                                            tooltip={item.title}
                                            className="h-9"
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter className="gap-3 px-3 pb-4">
                <PlanUsage />
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
