import { Card } from '@/components/ui/card';
import { JOB_STATUS_LABEL, type JobRow, type JobStatus } from '@/types/jobs';
import { differenceInCalendarDays, parseISO } from 'date-fns';
import { useMemo } from 'react';

const DAY_W = 28; // px per day column
const ROW_H = 36; // px per row
const LABEL_W = 192; // left label column (w-48)

const STATUS_BAR: Record<JobStatus, string> = {
    scheduled: 'bg-muted-foreground/60',
    in_progress: 'bg-blue-500',
    done: 'bg-green-500',
    canceled: 'bg-red-400/70 line-through',
};

interface TimelineProps {
    month: string; // YYYY-MM
    jobs: JobRow[];
    isAdmin: boolean;
    onJobClick: (job: JobRow) => void;
}

type Row = { kind: 'project'; label: string } | { kind: 'job'; job: JobRow };

/**
 * Month timeline: one row per job grouped by project, duration bars on a
 * per-day grid, and elbow connectors from each predecessor's end to its
 * successor's start.
 */
export default function ScheduleTimeline({ month, jobs, isAdmin, onJobClick }: TimelineProps) {
    const [year, mon] = month.split('-').map(Number);
    const daysInMonth = new Date(year, mon, 0).getDate();
    const monthStart = parseISO(`${month}-01`);

    const now = new Date();
    const todayIdx = now.getFullYear() === year && now.getMonth() === mon - 1 ? now.getDate() - 1 : null;

    const { rows, rowIndexByJobId } = useMemo(() => {
        const byProject = new Map<number, { name: string; jobs: JobRow[] }>();
        for (const job of jobs) {
            const group = byProject.get(job.project_id) ?? { name: job.project_name ?? 'Project', jobs: [] };
            group.jobs.push(job);
            byProject.set(job.project_id, group);
        }
        const groups = [...byProject.values()].sort((a, b) => a.name.localeCompare(b.name));

        const rows: Row[] = [];
        const rowIndexByJobId = new Map<number, number>();
        for (const group of groups) {
            rows.push({ kind: 'project', label: group.name });
            for (const job of group.jobs) {
                rowIndexByJobId.set(job.id, rows.length);
                rows.push({ kind: 'job', job });
            }
        }
        return { rows, rowIndexByJobId };
    }, [jobs]);

    if (jobs.length === 0) {
        return <Card className="text-muted-foreground p-8 text-center text-sm">No jobs overlap this month.</Card>;
    }

    // Bar geometry per job, clipped to the visible month.
    const barFor = (job: JobRow) => {
        const start = differenceInCalendarDays(parseISO(job.scheduled_date), monthStart);
        const end = differenceInCalendarDays(parseISO(job.end_date), monthStart);
        const clippedStart = Math.max(start, 0);
        const clippedEnd = Math.min(end, daysInMonth - 1);
        return {
            x: clippedStart * DAY_W,
            width: (clippedEnd - clippedStart + 1) * DAY_W,
            clippedLeft: start < 0,
            clippedRight: end > daysInMonth - 1,
            startIdx: clippedStart,
            endIdx: clippedEnd,
        };
    };

    const gridW = daysInMonth * DAY_W;
    const gridH = rows.length * ROW_H;

    // Elbow connectors between bars when both ends are on this month's view.
    const arrows = jobs.flatMap((job) => {
        if (!job.predecessor_job_id) return [];
        const fromRow = rowIndexByJobId.get(job.predecessor_job_id);
        const toRow = rowIndexByJobId.get(job.id);
        const predecessor = jobs.find((j) => j.id === job.predecessor_job_id);
        if (fromRow === undefined || toRow === undefined || !predecessor) return [];

        const from = barFor(predecessor);
        const to = barFor(job);
        const x1 = from.x + from.width - DAY_W / 2;
        const y1 = fromRow * ROW_H + ROW_H / 2;
        const x2 = to.x;
        const y2 = toRow * ROW_H + ROW_H / 2;
        const bendX = Math.max(x1 + DAY_W / 2, x2 - DAY_W / 2);
        return [{ id: `${predecessor.id}-${job.id}`, path: `M ${x1} ${y1} H ${bendX} V ${y2} H ${x2}` }];
    });

    const weekendIdxs = Array.from({ length: daysInMonth }, (_, i) => i).filter((i) => {
        const dow = new Date(year, mon - 1, i + 1).getDay();
        return dow === 0 || dow === 6;
    });

    return (
        <Card className="overflow-hidden p-0">
            <div className="flex">
                {/* Left labels */}
                <div className="shrink-0 border-r" style={{ width: LABEL_W }}>
                    <div className="text-muted-foreground border-b px-3 py-2 text-xs font-semibold tracking-wider uppercase">Job</div>
                    {rows.map((row, i) => (
                        <div
                            key={i}
                            className={`flex items-center truncate border-b px-3 text-sm ${row.kind === 'project' ? 'bg-muted/50 font-medium' : ''}`}
                            style={{ height: ROW_H }}
                        >
                            {row.kind === 'project' ? (
                                <span className="truncate">{row.label}</span>
                            ) : (
                                <span className="text-muted-foreground truncate pl-3" title={row.job.title ?? undefined}>
                                    {row.job.title || 'Untitled job'}
                                    {row.job.trade && <span className="text-muted-foreground/70"> · {row.job.trade}</span>}
                                </span>
                            )}
                        </div>
                    ))}
                </div>

                {/* Scrollable day grid */}
                <div className="overflow-x-auto">
                    <div style={{ width: gridW }}>
                        <div className="flex border-b">
                            {Array.from({ length: daysInMonth }, (_, i) => (
                                <div
                                    key={i}
                                    className={`text-muted-foreground shrink-0 py-2 text-center text-[10px] ${weekendIdxs.includes(i) ? 'bg-muted/40' : ''}`}
                                    style={{ width: DAY_W }}
                                >
                                    {i + 1}
                                </div>
                            ))}
                        </div>

                        <div className="relative" style={{ width: gridW, height: gridH }}>
                            {/* Weekend shading + row separators */}
                            {weekendIdxs.map((i) => (
                                <div key={i} className="bg-muted/40 absolute top-0 bottom-0" style={{ left: i * DAY_W, width: DAY_W }} />
                            ))}
                            {rows.map((row, i) => (
                                <div
                                    key={i}
                                    className={`absolute right-0 left-0 border-b ${row.kind === 'project' ? 'bg-muted/50' : ''}`}
                                    style={{ top: i * ROW_H, height: ROW_H }}
                                />
                            ))}

                            {/* Today marker */}
                            {todayIdx !== null && (
                                <div className="bg-primary/60 absolute top-0 bottom-0 z-10 w-px" style={{ left: todayIdx * DAY_W + DAY_W / 2 }} />
                            )}

                            {/* Dependency connectors */}
                            <svg className="pointer-events-none absolute inset-0 z-10" width={gridW} height={gridH}>
                                <defs>
                                    <marker id="dep-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
                                        <path d="M 0 0 L 6 3 L 0 6 z" className="fill-muted-foreground" />
                                    </marker>
                                </defs>
                                {arrows.map((arrow) => (
                                    <path
                                        key={arrow.id}
                                        d={arrow.path}
                                        className="stroke-muted-foreground"
                                        strokeWidth={1.5}
                                        fill="none"
                                        markerEnd="url(#dep-arrow)"
                                    />
                                ))}
                            </svg>

                            {/* Bars */}
                            {rows.map((row, i) => {
                                if (row.kind !== 'job') return null;
                                const bar = barFor(row.job);
                                return (
                                    <button
                                        key={row.job.id}
                                        type="button"
                                        onClick={() => onJobClick(row.job)}
                                        disabled={!isAdmin}
                                        className={`absolute z-20 flex items-center truncate rounded px-1.5 text-[11px] text-white ${STATUS_BAR[row.job.status]} ${
                                            isAdmin ? 'cursor-pointer hover:brightness-110' : 'cursor-default'
                                        } ${bar.clippedLeft ? 'rounded-l-none' : ''} ${bar.clippedRight ? 'rounded-r-none' : ''}`}
                                        style={{ left: bar.x, width: bar.width, top: i * ROW_H + 7, height: ROW_H - 14 }}
                                        title={`${row.job.title ?? ''} · ${row.job.scheduled_date} → ${row.job.end_date} · ${JOB_STATUS_LABEL[row.job.status]}`}
                                    >
                                        <span className="truncate">{row.job.duration_days > 1 ? `${row.job.duration_days}d` : ''}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </Card>
    );
}
