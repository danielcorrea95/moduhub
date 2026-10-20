import type { AxiosStatic } from 'axios';
import type { LucideIcon } from 'lucide-react';

declare global {
    // Ziggy — injetado por @routes no Blade.
    function route(
        name?: string,
        params?: unknown,
        absolute?: boolean,
    ): string & { current(name?: string, params?: unknown): boolean };

    interface Window {
        axios: AxiosStatic;
    }
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export type TenantRole = 'owner' | 'admin' | 'member';

export type ModuleKey = 'signatures' | 'invoices' | 'mailing';

export interface TenantBranding {
    primary_color?: string | null;
    logo_url?: string | null;
}

export interface CurrentTenant {
    id: string;
    name: string;
    slug: string;
    branding: TenantBranding | null;
    role: TenantRole | null;
    modules: ModuleKey[];
}

export interface WorkspaceSummary {
    id: string;
    name: string;
    slug: string;
    role: TenantRole;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
}

export interface SharedData {
    auth: { user: User | null };
    currentTenant: CurrentTenant | null;
    workspaces: WorkspaceSummary[];
    flash: Flash;
    sidebarOpen: boolean;
    planUsage: PlanUsageMeter[];
    [key: string]: unknown;
}

/** Uso de um módulo no ciclo do plano (barra lateral). `limit` nulo = sem limite. */
export interface PlanUsageMeter {
    key: string;
    label: string;
    used: number;
    limit: number | null;
    resets_at: string;
    detail: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    T & SharedData;

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
}
