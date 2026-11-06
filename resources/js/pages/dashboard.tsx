import { PriorityBadge } from '@/components/leads/priority-badge';
import { StatusPill } from '@/components/leads/status-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type CrmUser, type Lead } from '@/types/crm';
import { Head, Link, usePage } from '@inertiajs/react';
import { format, isBefore, isToday, parseISO, startOfDay } from 'date-fns';
import {
    AlertCircle,
    ArrowRight,
    BarChart3,
    CalendarClock,
    CheckCircle2,
    DollarSign,
    Inbox,
    Kanban,
    MapPin,
    Phone,
    Sparkles,
    TrendingUp,
    UserPlus,
} from 'lucide-react';
import { useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const ACTIVE_STAGES: { status: Lead['status']; label: string; color: string }[] = [
    { status: 'new', label: 'New', color: 'bg-blue-500' },
    { status: 'contacted', label: 'Contacted', color: 'bg-sky-500' },
    { status: 'qualified', label: 'Qualified', color: 'bg-indigo-500' },
    { status: 'meeting_scheduled', label: 'Meeting Scheduled', color: 'bg-violet-500' },
    { status: 'proposal_sent', label: 'Proposal Sent', color: 'bg-amber-500' },
];

interface RecentActivityItem {
    id: number;
    lead_id: number;
    lead_name: string;
    lead_status: Lead['status'];
    activity_type: string;
    title: string;
    description: string | null;
    actor_name: string;
    created_at: string | null;
}

interface DashboardProps {
    leads?: Lead[];
    users?: CrmUser[];
    recentActivities?: RecentActivityItem[];
}

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

function parseLocalDate(dateStr: string): Date {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d);
}

export default function Dashboard({ leads = [], users = [], recentActivities = [] }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth?.user?.role === 'admin';

    const summary = useMemo(() => {
        const today = startOfDay(new Date());

        const newLeads = leads.filter((l) => l.status === 'new');
        const activeLeads = leads.filter((l) => l.status !== 'won' && l.status !== 'lost');
        const wonLeads = leads.filter((l) => l.status === 'won');
        const lostLeads = leads.filter((l) => l.status === 'lost');

        const activePipelineCents = activeLeads.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
        const wonValueCents = wonLeads.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);

        const closedCount = wonLeads.length + lostLeads.length;
        const winRate = closedCount > 0 ? Math.round((wonLeads.length / closedCount) * 100) : null;

        const followUpsDue = activeLeads
            .filter((l) => {
                if (!l.next_follow_up_date) return false;
                const due = parseLocalDate(l.next_follow_up_date);
                return isBefore(due, today) || isToday(due);
            })
            .sort((a, b) => (a.next_follow_up_date ?? '').localeCompare(b.next_follow_up_date ?? ''));

        const overdueCount = followUpsDue.filter(
            (l) => l.next_follow_up_date && isBefore(parseLocalDate(l.next_follow_up_date), today),
        ).length;

        const stageBreakdown = ACTIVE_STAGES.map((stage) => {
            const stageLeads = leads.filter((l) => l.status === stage.status);
            const valueCents = stageLeads.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
            return {
                ...stage,
                count: stageLeads.length,
                valueCents,
            };
        });

        return {
            newLeads,
            activeLeads,
            wonLeads,
            lostLeads,
            activePipelineCents,
            wonValueCents,
            winRate,
            followUpsDue,
            overdueCount,
            stageBreakdown,
        };
    }, [leads]);

    const userMap = useMemo(() => {
        const map = new Map<number, string>();
        users.forEach((u) => map.set(u.id, u.name));
        return map;
    }, [users]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Page Header & Quick Navigation */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Dashboard</h1>
                        <p className="text-muted-foreground text-sm">
                            Daily overview of incoming leads, follow-up priorities, and sales pipeline health.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/leads" prefetch>
                                <UserPlus className="mr-2 h-4 w-4" />
                                Leads List
                            </Link>
                        </Button>
                        {isAdmin && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href="/admin/leads/analytics" prefetch>
                                        <BarChart3 className="mr-2 h-4 w-4" />
                                        Analytics
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href="/admin/leads" prefetch>
                                        <Kanban className="mr-2 h-4 w-4" />
                                        Open CRM Pipeline
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Top KPI Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        icon={<Inbox className="h-4 w-4 text-blue-600 dark:text-blue-400" />}
                        label="Awaiting First Contact"
                        value={summary.newLeads.length.toString()}
                        subtext={`${leads.length} total leads received`}
                        highlight={summary.newLeads.length > 0 ? 'border-blue-200 dark:border-blue-900/60' : undefined}
                    />
                    <KpiCard
                        icon={<DollarSign className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />}
                        label="Active Pipeline"
                        value={formatCurrency(summary.activePipelineCents)}
                        subtext={`${summary.activeLeads.length} open deal${summary.activeLeads.length === 1 ? '' : 's'} in progress`}
                    />
                    <KpiCard
                        icon={<AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />}
                        label="Follow-ups Due"
                        value={summary.followUpsDue.length.toString()}
                        subtext={
                            summary.overdueCount > 0
                                ? `${summary.overdueCount} overdue · needs action today`
                                : 'All follow-ups on schedule'
                        }
                        valueClassName={summary.overdueCount > 0 ? 'text-red-600 dark:text-red-400' : undefined}
                    />
                    <KpiCard
                        icon={<TrendingUp className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />}
                        label="Won Deals"
                        value={formatCurrency(summary.wonValueCents)}
                        subtext={
                            summary.winRate !== null
                                ? `${summary.wonLeads.length} won · ${summary.winRate}% win rate`
                                : `${summary.wonLeads.length} won deals`
                        }
                    />
                </div>

                {/* Active Pipeline Stage Bar */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-3">
                        <div>
                            <CardTitle className="text-base">Active Pipeline by Stage</CardTitle>
                            <CardDescription>Where open leads and estimated project value currently sit.</CardDescription>
                        </div>
                        {isAdmin && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/admin/leads" prefetch>
                                    Manage Pipeline
                                    <ArrowRight className="ml-1.5 h-4 w-4" />
                                </Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {summary.stageBreakdown.map((stage) => (
                                <div key={stage.status} className="bg-muted/30 flex flex-col justify-between rounded-lg border p-3.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-xs font-medium">{stage.label}</span>
                                        <span className={cn('h-2 w-2 rounded-full', stage.color)} />
                                    </div>
                                    <div className="mt-2 flex items-baseline justify-between gap-2">
                                        <span className="text-2xl font-semibold tabular-nums">{stage.count}</span>
                                        <span className="text-muted-foreground text-xs font-medium tabular-nums">
                                            {formatCurrency(stage.valueCents)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Main Action & Activity Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left Column: Follow-up Priorities & Uncontacted Leads */}
                    <div className="space-y-6 lg:col-span-7">
                        {/* Follow-ups Due Today or Overdue */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <CalendarClock className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                            Follow-up Action List
                                        </CardTitle>
                                        <CardDescription>
                                            Active leads scheduled for follow-up today or past their due date.
                                        </CardDescription>
                                    </div>
                                    <Badge variant={summary.overdueCount > 0 ? 'destructive' : 'secondary'}>
                                        {summary.followUpsDue.length} due
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {summary.followUpsDue.length === 0 ? (
                                    <div className="text-muted-foreground flex items-center justify-center gap-2 rounded-lg border border-dashed py-8 text-sm">
                                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                        You are all caught up—no overdue or today follow-ups.
                                    </div>
                                ) : (
                                    <div className="divide-border divide-y rounded-lg border">
                                        {summary.followUpsDue.slice(0, 6).map((lead) => {
                                            const dueDate = lead.next_follow_up_date
                                                ? parseLocalDate(lead.next_follow_up_date)
                                                : null;
                                            const overdue = dueDate ? isBefore(dueDate, startOfDay(new Date())) : false;
                                            const assignee = lead.assigned_to_user_id
                                                ? userMap.get(lead.assigned_to_user_id)
                                                : null;
                                            const href = isAdmin ? `/admin/leads/${lead.id}` : '/leads';

                                            return (
                                                <Link
                                                    key={lead.id}
                                                    href={href}
                                                    className="hover:bg-muted/50 flex flex-wrap items-center justify-between gap-3 p-3.5 transition-colors"
                                                >
                                                    <div className="min-w-0 flex-1 space-y-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium">
                                                                {lead.first_name} {lead.last_name}
                                                            </span>
                                                            <PriorityBadge priority={lead.priority} />
                                                            <StatusPill status={lead.status} />
                                                        </div>
                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-xs">
                                                            <span className="inline-flex items-center gap-1">
                                                                <Phone className="h-3 w-3" />
                                                                {lead.phone}
                                                            </span>
                                                            {lead.build_location && (
                                                                <span className="inline-flex items-center gap-1">
                                                                    <MapPin className="h-3 w-3" />
                                                                    {lead.build_location}
                                                                </span>
                                                            )}
                                                            {assignee && <span>Assigned: {assignee}</span>}
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <Badge
                                                            variant="secondary"
                                                            className={cn(
                                                                'text-xs',
                                                                overdue
                                                                    ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                                                            )}
                                                        >
                                                            {overdue ? 'Overdue · ' : 'Today · '}
                                                            {dueDate ? format(dueDate, 'MMM d') : ''}
                                                        </Badge>
                                                        {lead.estimated_value_cents !== null && (
                                                            <div className="mt-1 text-xs font-semibold tabular-nums">
                                                                {formatCurrency(lead.estimated_value_cents)}
                                                            </div>
                                                        )}
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* New Leads Awaiting Outreach */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Sparkles className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            New Leads Awaiting First Contact
                                        </CardTitle>
                                        <CardDescription>
                                            Incoming inquiries still in the New stage that need an initial call or email.
                                        </CardDescription>
                                    </div>
                                    <Badge variant="secondary">{summary.newLeads.length}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {summary.newLeads.length === 0 ? (
                                    <div className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                        Every incoming lead has been contacted.
                                    </div>
                                ) : (
                                    <div className="divide-border divide-y rounded-lg border">
                                        {summary.newLeads.slice(0, 5).map((lead) => {
                                            const href = isAdmin ? `/admin/leads/${lead.id}` : '/leads';
                                            return (
                                                <Link
                                                    key={lead.id}
                                                    href={href}
                                                    className="hover:bg-muted/50 flex flex-wrap items-center justify-between gap-3 p-3.5 transition-colors"
                                                >
                                                    <div className="min-w-0 flex-1 space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">
                                                                {lead.first_name} {lead.last_name}
                                                            </span>
                                                            <PriorityBadge priority={lead.priority} />
                                                            <Badge variant="outline" className="text-xs capitalize">
                                                                {lead.source.replace('_', ' ')}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-xs">
                                                            <span>{lead.email}</span>
                                                            <span>·</span>
                                                            <span>{lead.phone}</span>
                                                            {lead.build_location && (
                                                                <>
                                                                    <span>·</span>
                                                                    <span>{lead.build_location}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        {lead.estimated_value_cents !== null && (
                                                            <div className="text-sm font-semibold text-emerald-700 tabular-nums dark:text-emerald-400">
                                                                {formatCurrency(lead.estimated_value_cents)}
                                                            </div>
                                                        )}
                                                        <div className="text-muted-foreground text-xs">
                                                            {lead.submitted_at
                                                                ? format(parseISO(lead.submitted_at), 'MMM d, yyyy')
                                                                : ''}
                                                        </div>
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Recent Pipeline Activity */}
                    <div className="space-y-6 lg:col-span-5">
                        <Card className="h-full">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Recent Pipeline Activity</CardTitle>
                                <CardDescription>
                                    Latest calls, emails, meetings, and stage updates across leads.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {recentActivities.length === 0 ? (
                                    <div className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                        No recent activity logged yet.
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {recentActivities.map((item) => {
                                            const href = isAdmin ? `/admin/leads/${item.lead_id}` : '/leads';
                                            return (
                                                <Link
                                                    key={item.id}
                                                    href={href}
                                                    className="hover:bg-muted/50 block rounded-lg border p-3 transition-colors"
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="truncate text-sm font-medium">
                                                                    {item.lead_name}
                                                                </span>
                                                                <Badge variant="secondary" className="text-[11px] capitalize">
                                                                    {item.activity_type}
                                                                </Badge>
                                                            </div>
                                                            <p className="text-foreground mt-0.5 text-xs font-medium">
                                                                {item.title}
                                                            </p>
                                                            {item.description && (
                                                                <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">
                                                                    {item.description}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="text-muted-foreground shrink-0 text-right text-[11px]">
                                                            <div>
                                                                {item.created_at
                                                                    ? format(parseISO(item.created_at), 'MMM d')
                                                                    : ''}
                                                            </div>
                                                            <div>{item.actor_name}</div>
                                                        </div>
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function KpiCard({
    icon,
    label,
    value,
    subtext,
    highlight,
    valueClassName,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    subtext: string;
    highlight?: string;
    valueClassName?: string;
}) {
    return (
        <Card className={highlight}>
            <CardContent className="p-4">
                <div className="text-muted-foreground flex items-center justify-between gap-2 text-xs font-medium">
                    <span>{label}</span>
                    {icon}
                </div>
                <div className={cn('mt-2 text-2xl font-semibold tabular-nums', valueClassName)}>{value}</div>
                <p className="text-muted-foreground mt-1 text-xs">{subtext}</p>
            </CardContent>
        </Card>
    );
}
