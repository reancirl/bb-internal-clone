import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { JOB_STATUS_LABEL, JOB_STATUS_VARIANT, type JobRow, type JobStatus } from '@/types/jobs';
import { Head, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

interface PageProps {
    today: string;
    jobs: JobRow[];
    statuses: JobStatus[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jobs', href: '/jobs' },
];

function formatDate(date: string): string {
    // date is YYYY-MM-DD; render it in local terms without timezone drift.
    const [y, m, d] = date.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function JobCard({ job, statuses }: { job: JobRow; statuses: JobStatus[] }) {
    const setStatus = (status: string) => {
        router.patch(route('jobs.status', job.id), { status }, { preserveScroll: true });
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{job.project_name ?? 'Project'}</span>
                    <Badge variant={JOB_STATUS_VARIANT[job.status]}>{JOB_STATUS_LABEL[job.status]}</Badge>
                </div>
                {job.title && <p className="text-muted-foreground text-sm">{job.title}</p>}
                {job.notes && <p className="text-muted-foreground mt-1 text-xs">{job.notes}</p>}
            </div>
            <div className="flex items-center gap-3">
                <span className="text-muted-foreground text-sm tabular-nums">{formatDate(job.scheduled_date)}</span>
                <Select value={job.status} onValueChange={setStatus}>
                    <SelectTrigger className="w-36">
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
        </div>
    );
}

export default function JobsIndex({ today, jobs, statuses }: PageProps) {
    const todays = jobs.filter((j) => j.scheduled_date === today);
    const upcoming = jobs.filter((j) => j.scheduled_date > today);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jobs" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-semibold">My jobs</h1>
                    <p className="text-muted-foreground text-sm">Jobs you're assigned to. Update the status as you go.</p>
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">Today</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {todays.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nothing scheduled for you today.</p>
                        ) : (
                            todays.map((job) => <JobCard key={job.id} job={job} statuses={statuses} />)
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">Upcoming</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {upcoming.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No upcoming jobs assigned.</p>
                        ) : (
                            upcoming.map((job) => <JobCard key={job.id} job={job} statuses={statuses} />)
                        )}
                    </CardContent>
                </Card>

                {jobs.length === 0 && (
                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <CalendarDays className="h-3.5 w-3.5" />
                        Assigned jobs show up here. Check the Calendar for the full schedule.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
