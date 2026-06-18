import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type CrewOption, JOB_STATUS_DOT, JOB_STATUS_LABEL, type JobRow, type JobStatus, type ProjectOption } from '@/types/jobs';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    month: string; // YYYY-MM
    jobs: JobRow[];
    projects: ProjectOption[];
    crew: CrewOption[];
    statuses: JobStatus[];
    isAdmin: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calendar', href: '/calendar' },
];

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const pad = (n: number) => String(n).padStart(2, '0');

type JobForm = {
    project_id: string;
    title: string;
    scheduled_date: string;
    status: JobStatus;
    notes: string;
    crew: number[];
};

export default function CalendarIndex({ month, jobs, projects, crew, statuses, isAdmin }: PageProps) {
    const [year, mon] = month.split('-').map(Number);
    const monthLabel = new Date(year, mon - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const prevMonth = mon === 1 ? `${year - 1}-12` : `${year}-${pad(mon - 1)}`;
    const nextMonth = mon === 12 ? `${year + 1}-01` : `${year}-${pad(mon + 1)}`;

    const firstWeekday = new Date(year, mon - 1, 1).getDay();
    const daysInMonth = new Date(year, mon, 0).getDate();
    const cells: (number | null)[] = [];
    for (let i = 0; i < firstWeekday; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);

    const now = new Date();
    const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

    const jobsByDay = new Map<number, JobRow[]>();
    for (const job of jobs) {
        const day = Number(job.scheduled_date.slice(8, 10));
        jobsByDay.set(day, [...(jobsByDay.get(day) ?? []), job]);
    }

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<JobRow | null>(null);
    const [confirm, confirmDialog] = useConfirm();
    const form = useForm<JobForm>({
        project_id: '',
        title: '',
        scheduled_date: '',
        status: 'scheduled',
        notes: '',
        crew: [],
    });

    const openCreate = (day: number) => {
        if (!isAdmin) return;
        setEditing(null);
        form.setData({
            project_id: projects[0] ? String(projects[0].id) : '',
            title: '',
            scheduled_date: `${month}-${pad(day)}`,
            status: 'scheduled',
            notes: '',
            crew: [],
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (job: JobRow) => {
        if (!isAdmin) return;
        setEditing(job);
        form.setData({
            project_id: String(job.project_id),
            title: job.title ?? '',
            scheduled_date: job.scheduled_date,
            status: job.status,
            notes: job.notes ?? '',
            crew: job.crew.map((c) => c.id),
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const toggleCrew = (id: number, checked: boolean) => {
        form.setData('crew', checked ? [...form.data.crew, id] : form.data.crew.filter((c) => c !== id));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editing) {
            form.put(route('jobs.update', editing.id), opts);
        } else {
            form.post(route('jobs.store'), opts);
        }
    };

    const remove = async () => {
        if (!editing) return;
        const ok = await confirm({
            title: 'Remove job?',
            description: 'This job and its crew assignments will be deleted.',
            confirmLabel: 'Remove',
            destructive: true,
        });
        if (ok) router.delete(route('jobs.destroy', editing.id), { preserveScroll: true, onSuccess: () => setDialogOpen(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendar" />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Calendar</h1>
                        <p className="text-muted-foreground text-sm">Scheduled jobs across all projects.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="icon">
                            <Link href={route('calendar.index', { month: prevMonth })} preserveScroll>
                                <ChevronLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <span className="min-w-40 text-center font-medium">{monthLabel}</span>
                        <Button asChild variant="outline" size="icon">
                            <Link href={route('calendar.index', { month: nextMonth })} preserveScroll>
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    <div className="grid grid-cols-7 border-b">
                        {WEEKDAYS.map((d) => (
                            <div key={d} className="text-muted-foreground px-2 py-2 text-center text-xs font-semibold tracking-wider uppercase">
                                {d}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {cells.map((day, i) => {
                            const dateStr = day ? `${month}-${pad(day)}` : '';
                            const dayJobs = day ? (jobsByDay.get(day) ?? []) : [];
                            const isToday = dateStr === todayStr;
                            return (
                                <div
                                    key={i}
                                    className={`min-h-24 border-r border-b p-1.5 ${day ? '' : 'bg-muted/30'} ${isAdmin && day ? 'hover:bg-muted/30' : ''}`}
                                    onClick={day && dayJobs.length === 0 ? () => openCreate(day) : undefined}
                                    role={isAdmin && day ? 'button' : undefined}
                                >
                                    {day && (
                                        <>
                                            <div className="mb-1 flex items-center justify-between">
                                                <span
                                                    className={`text-xs ${isToday ? 'bg-primary text-primary-foreground flex h-5 w-5 items-center justify-center rounded-full' : 'text-muted-foreground'}`}
                                                >
                                                    {day}
                                                </span>
                                                {isAdmin && (
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            openCreate(day);
                                                        }}
                                                        aria-label="Add job"
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                    </button>
                                                )}
                                            </div>
                                            <div className="space-y-1">
                                                {dayJobs.map((job) => (
                                                    <button
                                                        key={job.id}
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            openEdit(job);
                                                        }}
                                                        className={`flex w-full items-center gap-1 rounded px-1.5 py-1 text-left text-xs ${isAdmin ? 'hover:bg-muted cursor-pointer' : 'cursor-default'}`}
                                                        title={`${job.project_name ?? ''} · ${JOB_STATUS_LABEL[job.status]}`}
                                                    >
                                                        <span className={`h-2 w-2 shrink-0 rounded-full ${JOB_STATUS_DOT[job.status]}`} />
                                                        <span className="truncate">{job.title || job.project_name}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </Card>

                {isAdmin && <p className="text-muted-foreground text-xs">Click a day to schedule a job, or a job to edit it.</p>}
            </div>

            {isAdmin && (
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Edit job' : 'Schedule job'}</DialogTitle>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="j-project">Project</Label>
                                    <Select value={form.data.project_id} onValueChange={(v) => form.setData('project_id', v)}>
                                        <SelectTrigger id="j-project">
                                            <SelectValue placeholder="Choose…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {projects.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.project_id} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="j-date">Date</Label>
                                    <Input
                                        id="j-date"
                                        type="date"
                                        value={form.data.scheduled_date}
                                        onChange={(e) => form.setData('scheduled_date', e.target.value)}
                                    />
                                    <InputError message={form.errors.scheduled_date} />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="j-title">Title</Label>
                                <Input
                                    id="j-title"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    placeholder="e.g. Framing — west elevation"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="j-status">Status</Label>
                                <Select value={form.data.status} onValueChange={(v) => form.setData('status', v as JobStatus)}>
                                    <SelectTrigger id="j-status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {JOB_STATUS_LABEL[s]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Crew</Label>
                                <div className="grid max-h-40 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-3">
                                    {crew.map((c) => (
                                        <label key={c.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox checked={form.data.crew.includes(c.id)} onCheckedChange={(v) => toggleCrew(c.id, v === true)} />
                                            <span className="truncate">{c.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="j-notes">Notes</Label>
                                <Textarea id="j-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            </div>
                            <DialogFooter className="flex items-center justify-between sm:justify-between">
                                {editing ? (
                                    <Button type="button" variant="outline" className="text-destructive gap-2" onClick={remove}>
                                        <Trash2 className="h-4 w-4" />
                                        Remove
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={form.processing}>
                                        {editing ? 'Save' : 'Schedule'}
                                    </Button>
                                </div>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
