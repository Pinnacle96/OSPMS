import type { PageProps } from '@inertiajs/core';

export type User = { public_id: string; name: string; email: string | null; username: string | null; phone?: string | null; status: string; must_change_password?: boolean; last_login_at?: string | null; created_at?: string; roles?: { id: number; name: string }[] };
export type Scope = { id: number; name: string; access_level: 'view' | 'manage' };
export type AccessScopes = { statewide: boolean; lgas: Scope[]; parks: Scope[]; operators: Scope[] };
export interface SharedProps extends PageProps {
    auth: { user: User | null; roles: string[]; permissions: string[]; scopes: AccessScopes | null };
    system: { name: string; currency: string; timezone: string; demo_mode: boolean; branding: { primary: string; primary_dark: string; secondary: string; government_logo: string | null; powered_by: string } };
    flash: { success?: string; error?: string; status?: string };
    navigation: { label: string; href: string; group: string; icon: string }[];
    unread_notifications_count: number;
}
export type Paginated<T> = { data: T[]; from: number | null; to: number | null; total: number; links: { url: string | null; label: string; active: boolean }[] };
export type Role = { id: number; name: string; permissions: { id: number; name: string }[]; users_count?: number };
