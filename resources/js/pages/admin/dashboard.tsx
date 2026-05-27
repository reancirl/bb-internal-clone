import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface AdminDashboardProps {
    stats: {
        users_total: number;
        admins_total: number;
        crew_total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Dashboard', href: '/admin/dashboard' },
];

export default function AdminDashboard({ stats }: AdminDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Admin Dashboard</h1>
                    <p className="text-muted-foreground text-sm">BuffaloBuilt Internal — admin portal</p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <StatCard label="Total users" value={stats.users_total} />
                    <StatCard label="Admins" value={stats.admins_total} />
                    <StatCard label="Crew" value={stats.crew_total} />
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-6">
            <div className="text-muted-foreground text-sm">{label}</div>
            <div className="mt-2 text-3xl font-semibold tabular-nums">{value}</div>
        </div>
    );
}
