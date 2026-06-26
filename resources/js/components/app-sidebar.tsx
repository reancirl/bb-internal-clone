import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Book, Briefcase, Calendar, Clock, DollarSign, FolderKanban, LayoutGrid, ShieldCheck, Store, UserPlus, Users } from 'lucide-react';
import AppLogo from './app-logo';

const operationsNav: NavItem[] = [
    { title: 'Time Card', url: '/time-card', icon: Clock },
    { title: 'Leads', url: '/leads', icon: UserPlus },
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Projects', url: '/projects', icon: FolderKanban },
    { title: 'Calendar', url: '/calendar', icon: Calendar },
    { title: 'Jobs', url: '/jobs', icon: Briefcase },
    { title: 'Price Book', url: '/price-book', icon: Book },
    { title: 'Trade Partners', url: '/trade-partners', icon: Users },
    { title: 'Vendors', url: '/vendors', icon: Store },
];

const adminNav: NavItem[] = [
    { title: 'Admin Dashboard', url: '/admin/dashboard', icon: ShieldCheck },
    { title: 'Rates', url: '/admin/rates', icon: DollarSign },
    { title: 'Employees', url: '/admin/employees', icon: Users },
    { title: 'Reports', url: '/admin/reports', icon: BarChart3 },
];

function NavSection({ label, items }: { label: string; items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton asChild isActive={page.url.startsWith(item.url)}>
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth?.user?.role === 'admin';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavSection label="Operations" items={operationsNav} />
                {isAdmin && <NavSection label="Admin" items={adminNav} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
