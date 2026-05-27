import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type OpenTimeCard, type SharedData } from '@/types';
import {
    type EmployeeOption,
    formatDateTime,
    formatDuration,
    type TimeCardSummary,
} from '@/types/time-card';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Clock, LogIn, LogOut } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface Filters {
    from: string;
    to: string;
    employee_id: number;
}

interface TimeCardIndexProps {
    cards: TimeCardSummary[];
    filters: Filters;
    employees: EmployeeOption[] | null;
    isAdmin: boolean;
    viewingUserId: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Time Card', href: '/time-card' },
];

function useElapsed(start: string | null): string {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!start) return;
        setNow(Date.now());
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, [start]);
    if (!start) return '0:00:00';
    const seconds = Math.max(0, Math.floor((now - new Date(start).getTime()) / 1000));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export default function TimeCardIndex({ cards, filters, employees, isAdmin, viewingUserId }: TimeCardIndexProps) {
    const { openTimeCard, auth } = usePage<SharedData>().props;
    const ownCardShown = viewingUserId === auth.user.id;
    const openCard: OpenTimeCard | null = ownCardShown ? openTimeCard : null;

    const elapsed = useElapsed(openCard?.clock_in_at ?? null);

    const clockInForm = useForm({ notes: '' });
    const clockOutForm = useForm({ notes: '' });

    const submitClockIn: FormEventHandler = (e) => {
        e.preventDefault();
        clockInForm.post(route('time-card.clock-in'), {
            preserveScroll: true,
            onSuccess: () => clockInForm.reset('notes'),
        });
    };

    const submitClockOut: FormEventHandler = (e) => {
        e.preventDefault();
        clockOutForm.post(route('time-card.clock-out'), {
            preserveScroll: true,
            onSuccess: () => clockOutForm.reset('notes'),
        });
    };

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            route('time-card.index'),
            { ...filters, ...next },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const totalMinutes = cards.reduce((sum, c) => sum + (c.duration_minutes ?? 0), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Time Card" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-semibold">Time Card</h1>
                    <p className="text-muted-foreground text-sm">Clock in and out to track your hours.</p>
                </div>

                {/* Clock In / Out card — only when viewing own data */}
                {ownCardShown && (
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base font-semibold">
                                {openCard ? 'You are clocked in' : 'Clock In / Out'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {openCard ? (
                                <form onSubmit={submitClockOut} className="space-y-4">
                                    <div className="bg-muted flex items-center gap-3 rounded-md px-4 py-3 text-sm">
                                        <Clock className="text-secondary h-5 w-5" />
                                        <div className="flex-1">
                                            <div className="font-medium">Since {formatDateTime(openCard.clock_in_at)}</div>
                                            <div className="text-muted-foreground font-mono text-xs tabular-nums">
                                                Elapsed {elapsed}
                                            </div>
                                        </div>
                                    </div>
                                    {openCard.notes && (
                                        <div className="text-muted-foreground text-xs">
                                            <span className="font-medium">Notes from clock-in:</span> {openCard.notes}
                                        </div>
                                    )}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="clockout-notes">Add notes before clocking out (optional)</Label>
                                        <Textarea
                                            id="clockout-notes"
                                            placeholder="What did you work on?"
                                            value={clockOutForm.data.notes}
                                            onChange={(e) => clockOutForm.setData('notes', e.target.value)}
                                        />
                                        <InputError message={clockOutForm.errors.notes} />
                                    </div>
                                    <Button type="submit" className="gap-2" disabled={clockOutForm.processing}>
                                        <LogOut className="h-4 w-4" />
                                        Clock Out
                                    </Button>
                                </form>
                            ) : (
                                <form onSubmit={submitClockIn} className="space-y-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="clockin-notes">Notes (optional)</Label>
                                        <Textarea
                                            id="clockin-notes"
                                            placeholder="Add notes for this shift"
                                            value={clockInForm.data.notes}
                                            onChange={(e) => clockInForm.setData('notes', e.target.value)}
                                        />
                                        <InputError message={clockInForm.errors.notes} />
                                    </div>
                                    <Button type="submit" className="gap-2" disabled={clockInForm.processing}>
                                        <LogIn className="h-4 w-4" />
                                        Clock In
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Filter card */}
                <Card>
                    <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-end">
                        <div className="space-y-1.5 md:flex-1">
                            <Label htmlFor="from">From</Label>
                            <Input id="from" type="date" value={filters.from} onChange={(e) => applyFilters({ from: e.target.value })} />
                        </div>
                        <div className="space-y-1.5 md:flex-1">
                            <Label htmlFor="to">To</Label>
                            <Input id="to" type="date" value={filters.to} onChange={(e) => applyFilters({ to: e.target.value })} />
                        </div>
                        {isAdmin && employees && (
                            <div className="space-y-1.5 md:flex-1">
                                <Label htmlFor="employee">Employee</Label>
                                <Select
                                    value={String(filters.employee_id)}
                                    onValueChange={(v) => applyFilters({ employee_id: Number(v) })}
                                >
                                    <SelectTrigger id="employee">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((e) => (
                                            <SelectItem key={e.id} value={String(e.id)}>
                                                {e.name} ({e.role})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <Button onClick={() => applyFilters({})} className="md:w-32">
                            Apply
                        </Button>
                    </CardContent>
                </Card>

                {/* History table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Clock in</th>
                                    <th className="px-6 py-3">Clock out</th>
                                    <th className="px-6 py-3">Duration</th>
                                    <th className="px-6 py-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {cards.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground px-6 py-10 text-center text-sm">
                                            No time cards in this range.
                                        </td>
                                    </tr>
                                ) : (
                                    cards.map((c) => (
                                        <tr key={c.id} className="hover:bg-muted/30">
                                            <td className="px-6 py-3">{formatDateTime(c.clock_in_at)}</td>
                                            <td className="px-6 py-3">
                                                {c.clock_out_at ? formatDateTime(c.clock_out_at) : <span className="text-secondary">Open</span>}
                                            </td>
                                            <td className="px-6 py-3 tabular-nums">{formatDuration(c.duration_minutes)}</td>
                                            <td className="text-muted-foreground px-6 py-3">{c.notes ?? '—'}</td>
                                        </tr>
                                    ))
                                )}
                                {cards.length > 0 && (
                                    <tr className="bg-muted/40 border-t-2">
                                        <td colSpan={2} className="px-6 py-3 text-right font-medium">
                                            Total
                                        </td>
                                        <td className="px-6 py-3 font-mono font-semibold tabular-nums">{formatDuration(totalMinutes)}</td>
                                        <td />
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
