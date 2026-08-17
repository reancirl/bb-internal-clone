import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlarmClock, CalendarRange, ChevronDown, Clock, Download, Users } from 'lucide-react';
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
    filters: { from: string | null; to: string | null };
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

function toDateString(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function mondayOf(d: Date): Date {
    const copy = new Date(d);
    const day = copy.getDay();
    copy.setDate(copy.getDate() - (day === 0 ? 6 : day - 1));
    return copy;
}

function presetRange(key: string): { from: string; to: string } {
    const today = new Date();
    switch (key) {
        case 'last-week': {
            const monday = mondayOf(today);
            monday.setDate(monday.getDate() - 7);
            const sunday = new Date(monday);
            sunday.setDate(sunday.getDate() + 6);
            return { from: toDateString(monday), to: toDateString(sunday) };
        }
        case 'this-month':
            return {
                from: toDateString(new Date(today.getFullYear(), today.getMonth(), 1)),
                to: toDateString(new Date(today.getFullYear(), today.getMonth() + 1, 0)),
            };
        case 'last-month':
            return {
                from: toDateString(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
                to: toDateString(new Date(today.getFullYear(), today.getMonth(), 0)),
            };
        default: {
            const monday = mondayOf(today);
            const sunday = new Date(monday);
            sunday.setDate(sunday.getDate() + 6);
            return { from: toDateString(monday), to: toDateString(sunday) };
        }
    }
}

const PRESETS = [
    { key: 'all', label: 'All time' },
    { key: 'this-week', label: 'This week' },
    { key: 'last-week', label: 'Last week' },
    { key: 'this-month', label: 'This month' },
    { key: 'last-month', label: 'Last month' },
];

function formatRangeLabel(from: string | null, to: string | null): string {
    if (from === null || to === null) {
        return 'All time';
    }
    const fmt = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return `${fmt(from)} – ${fmt(to)}`;
}

export default function TimeSummary({ rows, filters }: Props) {
    const [form, setForm] = useState({ from: filters.from ?? '', to: filters.to ?? '' });
    const [customOpen, setCustomOpen] = useState(false);

    const visit = (params: Record<string, string>) => {
        router.get('/admin/reports/time-summary', params, { preserveState: true, replace: true });
    };

    const applyCustom = (e: React.FormEvent) => {
        e.preventDefault();
        if (form.from && form.to) {
            visit({ from: form.from, to: form.to });
        }
    };

    const applyPreset = (key: string) => {
        setCustomOpen(false);
        if (key === 'all') {
            setForm({ from: '', to: '' });
            visit({ range: 'all' });
            return;
        }
        const range = presetRange(key);
        setForm(range);
        visit(range);
    };

    const activePreset =
        filters.from === null
            ? 'all'
            : PRESETS.find((p) => {
                  if (p.key === 'all') return false;
                  const range = presetRange(p.key);
                  return range.from === filters.from && range.to === filters.to;
              })?.key;

    const totalMinutes = rows.reduce((sum, r) => sum + r.total_minutes, 0);
    const totalCards = rows.reduce((sum, r) => sum + r.cards_count, 0);
    const totalOpen = rows.reduce((sum, r) => sum + r.open_cards_count, 0);
    const rangeLabel = formatRangeLabel(filters.from, filters.to);
    const exportUrl =
        filters.from === null
            ? '/admin/reports/time-summary/export?range=all'
            : `/admin/reports/time-summary/export?from=${filters.from}&to=${filters.to}`;

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
                    <CardContent className="flex flex-col gap-3 pt-6">
                        <div className="flex flex-wrap items-center gap-1.5">
                            {PRESETS.map((p) => (
                                <Button
                                    key={p.key}
                                    type="button"
                                    variant={activePreset === p.key ? 'default' : 'outline'}
                                    size="sm"
                                    className="h-8 rounded-full px-3.5"
                                    onClick={() => applyPreset(p.key)}
                                >
                                    {p.label}
                                </Button>
                            ))}
                            <Button
                                type="button"
                                variant={activePreset === undefined ? 'default' : 'outline'}
                                size="sm"
                                className="h-8 rounded-full px-3.5"
                                onClick={() => setCustomOpen((open) => !open)}
                            >
                                <CalendarRange className="h-3.5 w-3.5" />
                                {activePreset === undefined ? rangeLabel : 'Custom'}
                                <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', customOpen && 'rotate-180')} />
                            </Button>
                        </div>
                        {customOpen && (
                            <form onSubmit={applyCustom} className="flex flex-wrap items-end gap-3 border-t pt-3">
                                <div className="flex min-w-0 flex-col gap-1">
                                    <label htmlFor="from" className="text-muted-foreground text-xs font-medium">
                                        From
                                    </label>
                                    <Input
                                        id="from"
                                        type="date"
                                        value={form.from}
                                        onChange={(e) => setForm({ ...form, from: e.target.value })}
                                        className="h-9 w-full sm:w-40"
                                    />
                                </div>
                                <div className="flex min-w-0 flex-col gap-1">
                                    <label htmlFor="to" className="text-muted-foreground text-xs font-medium">
                                        To
                                    </label>
                                    <Input
                                        id="to"
                                        type="date"
                                        value={form.to}
                                        onChange={(e) => setForm({ ...form, to: e.target.value })}
                                        className="h-9 w-full sm:w-40"
                                    />
                                </div>
                                <Button type="submit" size="sm" className="h-9" disabled={!form.from || !form.to}>
                                    Apply
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <div className="grid auto-rows-min gap-4 sm:grid-cols-3">
                    <StatCard label="Total hours" value={formatHours(totalMinutes)} sub={rangeLabel} icon={<Clock className="h-4 w-4" />} />
                    <StatCard
                        label="Employees with hours"
                        value={String(rows.length)}
                        sub={`${totalCards} time card${totalCards === 1 ? '' : 's'}`}
                        icon={<Users className="h-4 w-4" />}
                    />
                    <StatCard
                        label="Still clocked in"
                        value={String(totalOpen)}
                        sub={totalOpen > 0 ? 'Excluded from totals' : 'All cards closed'}
                        icon={<AlarmClock className="h-4 w-4" />}
                        tone={totalOpen > 0 ? 'warning' : 'default'}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-4 w-4" />
                            Hours by Employee
                        </CardTitle>
                        <CardDescription>Open cards (still clocked in) are excluded from totals.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {rows.length === 0 ? (
                            <p className="text-muted-foreground px-6 pb-6 text-sm">
                                No time cards for {rangeLabel}. Try a wider range or a preset above.
                            </p>
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

function StatCard({
    label,
    value,
    sub,
    icon,
    tone = 'default',
}: {
    label: string;
    value: string;
    sub?: string;
    icon: React.ReactNode;
    tone?: 'default' | 'warning';
}) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-5">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                {icon}
                {label}
            </div>
            <div className={cn('mt-2 text-3xl font-semibold tabular-nums', tone === 'warning' && 'text-amber-600 dark:text-amber-400')}>{value}</div>
            {sub && <div className="text-muted-foreground mt-1 text-xs">{sub}</div>}
        </div>
    );
}
