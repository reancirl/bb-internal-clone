import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Clock, Download } from 'lucide-react';
import { useState } from 'react';

interface TimeSummaryRow {
    user_id: number;
    name: string;
    role: string;
    cards_count: number;
    open_cards_count: number;
    total_minutes: number;
}

interface Props {
    rows: TimeSummaryRow[];
    filters: { from: string; to: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
    { title: 'Time Summary', href: '/admin/reports/time-summary' },
];

function formatHours(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h === 0 ? `${m}m` : `${h}h ${m}m`;
}

export default function TimeSummary({ rows, filters }: Props) {
    const [form, setForm] = useState({ from: filters.from, to: filters.to });

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/reports/time-summary', form, { preserveState: true, replace: true });
    };

    const totalMinutes = rows.reduce((sum, r) => sum + r.total_minutes, 0);
    const exportUrl = `/admin/reports/time-summary/export?from=${filters.from}&to=${filters.to}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Time Summary" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Time Summary</h1>
                        <p className="text-muted-foreground text-sm">Hours per employee for payroll, by date range.</p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href={exportUrl}>
                            <Download className="h-4 w-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3">
                            <div className="flex flex-col gap-1">
                                <label htmlFor="from" className="text-xs font-medium">
                                    From
                                </label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={form.from}
                                    onChange={(e) => setForm({ ...form, from: e.target.value })}
                                    className="w-40"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <label htmlFor="to" className="text-xs font-medium">
                                    To
                                </label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={form.to}
                                    onChange={(e) => setForm({ ...form, to: e.target.value })}
                                    className="w-40"
                                />
                            </div>
                            <Button type="submit" size="sm">
                                Apply
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-4 w-4" />
                            Hours by Employee
                        </CardTitle>
                        <CardDescription>
                            {filters.from} to {filters.to}. Open cards (still clocked in) are excluded from totals.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {rows.length === 0 ? (
                            <p className="text-muted-foreground px-6 pb-6 text-sm">No time cards in this range.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b text-xs">
                                            <th className="px-4 py-2 text-left font-medium">Employee</th>
                                            <th className="px-4 py-2 text-left font-medium">Role</th>
                                            <th className="px-4 py-2 text-right font-medium">Cards</th>
                                            <th className="px-4 py-2 text-right font-medium">Open</th>
                                            <th className="px-4 py-2 text-right font-medium">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((r) => (
                                            <tr key={r.user_id} className="border-b last:border-0">
                                                <td className="px-4 py-2.5 font-medium">{r.name}</td>
                                                <td className="px-4 py-2.5">
                                                    <Badge variant="secondary" className="capitalize">
                                                        {r.role}
                                                    </Badge>
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">{r.cards_count}</td>
                                                <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">
                                                    {r.open_cards_count > 0 ? r.open_cards_count : '—'}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{formatHours(r.total_minutes)}</td>
                                            </tr>
                                        ))}
                                        <tr className="bg-muted/40 border-t-2">
                                            <td colSpan={4} className="px-4 py-2.5 text-right font-medium">
                                                Total
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{formatHours(totalMinutes)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
