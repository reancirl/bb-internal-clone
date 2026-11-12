import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface OpenTimeCard {
    id: number;
    clock_in_at: string;
    notes: string | null;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
    id?: string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: Flash;
    openTimeCard: OpenTimeCard | null;
    [key: string]: unknown;
}

export type UserRole = 'admin' | 'crew';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar?: string;
    email_notifications: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
