import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Fragment } from 'react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { currentTenant } = usePage<SharedData>().props;
    const canCreateDocument =
        currentTenant?.modules.includes('signatures') &&
        !route().current('signatures.create');

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-4 md:px-6">
            <SidebarTrigger className="-ml-1" />
            {breadcrumbs.length > 0 && (
                <>
                    <Breadcrumb className="min-w-0">
                        <BreadcrumbList className="flex-nowrap">
                            {breadcrumbs.map((item, index) => {
                                const isLast = index === breadcrumbs.length - 1;
                                return (
                                    <Fragment key={`${item.title}-${index}`}>
                                        <BreadcrumbItem className="min-w-0">
                                            {isLast || !item.href ? (
                                                <BreadcrumbPage className="truncate">
                                                    {item.title}
                                                </BreadcrumbPage>
                                            ) : (
                                                <BreadcrumbLink asChild>
                                                    <Link href={item.href} className="truncate">
                                                        {item.title}
                                                    </Link>
                                                </BreadcrumbLink>
                                            )}
                                        </BreadcrumbItem>
                                        {!isLast && <BreadcrumbSeparator />}
                                    </Fragment>
                                );
                            })}
                        </BreadcrumbList>
                    </Breadcrumb>
                </>
            )}

            {canCreateDocument && (
                <Button asChild className="ml-auto">
                    <Link href={route('signatures.create')}>
                        <Plus />
                        <span className="hidden sm:inline">Novo documento</span>
                        <span className="sm:hidden">Novo</span>
                    </Link>
                </Button>
            )}
        </header>
    );
}
