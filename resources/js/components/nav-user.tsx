import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { useInitials } from '@/hooks/use-initials';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, UserRound } from 'lucide-react';

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();
    const user = auth.user;

    if (!user) return null;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                        >
                            <Avatar className="size-8 rounded-md">
                                <AvatarFallback className="rounded-md bg-secondary text-xs font-semibold text-foreground">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium text-foreground">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {user.email}
                                </span>
                            </span>
                            <ChevronsUpDown className="ml-auto size-4 text-muted-foreground" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="end"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'top'}
                    >
                        <DropdownMenuLabel className="font-normal">
                            <span className="block truncate font-medium">{user.name}</span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {user.email}
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={route('profile.edit')} className="gap-2">
                                <UserRound className="size-4" />
                                Meu perfil
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => router.post(route('logout'))}
                            className="gap-2"
                        >
                            <LogOut className="size-4" />
                            Sair
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
