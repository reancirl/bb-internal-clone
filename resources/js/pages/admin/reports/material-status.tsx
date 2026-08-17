import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Download, ExternalLink, Package } from 'lucide-react';

interface CategoryRow {
    category: string;
    total: number;
    ordered: number;
    on_site: number;
    outstanding: number;
}

interface ProjectRow {
    id: number;
    name: string;
    client_name: string | null;
    status: string;
    categories: CategoryRow[];
    totals: { total: number; ordered: number; on_site: number; outstanding: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
    { title: 'Material Status', href: '/admin/reports/material-status' },
];

export default function MaterialStatus({ projects }: { projects: ProjectRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Material Status" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Material Status</h1>
                        <p className="text-muted-foreground text-sm">Ordered, on-site, and outstanding takeoff lines by project and category.</p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href="/admin/reports/material-status/export">
                            <Download className="h-4 w-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-sm">
                                No projects with takeoff lines yet. Create a project to see its material status here.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    projects.map((p) => (
                        <Card key={p.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Package className="h-4 w-4" />
                                            {p.name}
                                            <Badge variant="secondary" className="capitalize">
                                                {p.status}
                                            </Badge>
                                        </CardTitle>
                                        {p.client_name && <CardDescription>{p.client_name}</CardDescription>}
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={cn(
                                                'text-sm font-semibold tabular-nums',
                                                p.totals.outstanding > 0
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-green-700 dark:text-green-400',
                                            )}
                                        >
                                            {p.totals.outstanding > 0 ? `${p.totals.outstanding} outstanding` : 'All ordered'}
                                        </span>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" title="Open project" asChild>
                                            <Link href={`/projects/${p.id}`}>
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[36rem] text-sm">
                                        <thead>
                                            <tr className="text-muted-foreground border-b text-xs">
                                                <th className="px-4 py-2 text-left font-medium">Category</th>
                                                <th className="px-4 py-2 text-right font-medium">Lines</th>
                                                <th className="px-4 py-2 text-right font-medium">Ordered</th>
                                                <th className="px-4 py-2 text-right font-medium">On Site</th>
                                                <th className="px-4 py-2 text-right font-medium">Outstanding</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {p.categories.map((c) => (
                                                <tr key={c.category} className="border-b last:border-0">
                                                    <td className="px-4 py-2.5 font-medium">{c.category}</td>
                                                    <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">{c.total}</td>
                                                    <td className="px-4 py-2.5 text-right tabular-nums">{c.ordered}</td>
                                                    <td className="px-4 py-2.5 text-right tabular-nums">{c.on_site}</td>
                                                    <td
                                                        className={cn(
                                                            'px-4 py-2.5 text-right font-semibold tabular-nums',
                                                            c.outstanding > 0
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-green-700 dark:text-green-400',
                                                        )}
                                                    >
                                                        {c.outstanding}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-muted/40 border-t-2">
                                                <td className="px-4 py-2.5 text-right font-medium">Total</td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{p.totals.total}</td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{p.totals.ordered}</td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{p.totals.on_site}</td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{p.totals.outstanding}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
