import { PriorityBadge } from '@/components/leads/priority-badge';
import { StatusPill } from '@/components/leads/status-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Lead } from '@/types/crm';
import { Head, Link } from '@inertiajs/react';
import { format, isBefore, parseISO, startOfDay } from 'date-fns';
import {
    Activity,
    AlertTriangle,
    Award,
    BarChart3,
    DollarSign,
    Kanban,
    ShieldCheck,
    TrendingDown,
    UserPlus,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';

interface TeamUser {
    id: number;
    name: string;
    email?: string;
    role?: string;
}

interface RecentActivityItem {
    id: number;
    lead_id: number;
    lead_name: string;
    activity_type: string;
    title: string;
    actor_name: string;
    created_at: string | null;
}

interface AdminDashboardProps {
    stats: {
        users_total: number;
        admins_total: number;
        crew_total: number;
    };
    leads?: Lead[];
    users?: TeamUser[];
    recentActivities?: RecentActivityItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Dashboard', href: '/admin/dashboard' },
];

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

export default function AdminDashboard({
    stats,
    leads = [],
    users = [],
    recentActivities = [],
}: AdminDashboardProps) {
    const data = useMemo(() => {
        const today = startOfDay(new Date());

        const active = leads.filter((l) => l.status !== 'won' && l.status !== 'lost');
        const won = leads.filter((l) => l.status === 'won');
        const lost = leads.filter((l) => l.status === 'lost');
        const unassigned = active.filter((l) => l.assigned_to_user_id === null);

        const activeValue = active.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
        const wonValue = won.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);

        const closedCount = won.length + lost.length;
        const winRate = closedCount > 0 ? Math.round((won.length / closedCount) * 100) : null;

        const overdue = active.filter(
            (l) => l.next_follow_up_date && isBefore(parseLocalDate(l.next_follow_up_date), today),
        );

        const highPriorityUnassigned = unassigned.filter((l) => l.priority === 'high');

        const userMap = new Map<number, TeamUser>();
        users.forEach((u) => userMap.set(u.id, u));

        // Team performance & workload rows
        const teamRows = users.map((u) => {
            const memberLeads = leads.filter((l) => l.assigned_to_user_id === u.id);
            const memberActive = memberLeads.filter((l) => l.status !== 'won' && l.status !== 'lost');
            const memberWon = memberLeads.filter((l) => l.status === 'won');
            const memberOverdue = memberActive.filter(
                (l) => l.next_follow_up_date && isBefore(parseLocalDate(l.next_follow_up_date), today),
            );
            const memberActiveValue = memberActive.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
            const memberWonValue = memberWon.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);

            return {
                id: u.id,
                name: u.name,
                role: u.role ?? 'crew',
                activeCount: memberActive.length,
                overdueCount: memberOverdue.length,
                wonCount: memberWon.length,
                activeValue: memberActiveValue,
                wonValue: memberWonValue,
            };
        });

        // Sort team rows by active pipeline value, then active count
        teamRows.sort((a, b) => b.activeValue - a.activeValue || b.activeCount - a.activeCount);

        const topOpportunities = [...active]
            .sort((a, b) => (b.estimated_value_cents ?? 0) - (a.estimated_value_cents ?? 0))
            .slice(0, 5);

        const recentLost = [...lost]
            .sort((a, b) => (b.lost_at ?? '').localeCompare(a.lost_at ?? ''))
            .slice(0, 4);

        return {
            activeCount: active.length,
            wonCount: won.length,
            lostCount: lost.length,
            unassignedCount: unassigned.length,
            highPriorityUnassignedCount: highPriorityUnassigned.length,
            overdueCount: overdue.length,
            activeValue,
            wonValue,
            winRate,
            unassignedLeads: unassigned.slice(0, 5),
            topOpportunities,
            recentLost,
            teamRows,
            userMap,
        };
    }, [leads, users]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Admin Dashboard</h1>
                        <p className="text-muted-foreground text-sm">
                            Management oversight of team workload, unassigned leads, win/loss health, and pipeline risk.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/employees" prefetch>
                                <Users className="mr-2 h-4 w-4" />
                                Manage Accounts
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/leads" prefetch>
                                <UserPlus className="mr-2 h-4 w-4" />
                                Leads List
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/leads/analytics" prefetch>
                                <BarChart3 className="mr-2 h-4 w-4" />
                                Pipeline Analytics
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/leads" prefetch>
                                <Kanban className="mr-2 h-4 w-4" />
                                CRM Pipeline
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* 4 Executive Summary Cards */}
                <div className="grid auto-rows-min gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={<DollarSign className="h-4 w-4 text-blue-600 dark:text-blue-400" />}
                        label="Active Pipeline"
                        value={formatCurrency(data.activeValue)}
                        subtext={`${data.activeCount} open leads · ${data.unassignedCount} unassigned`}
                    />
                    <StatCard
                        icon={<Award className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />}
                        label="Closed Won Revenue"
                        value={formatCurrency(data.wonValue)}
                        subtext={
                            data.winRate !== null
                                ? `${data.wonCount} won · ${data.winRate}% win rate`
                                : `${data.wonCount} closed-won deals`
                        }
                    />
                    <StatCard
                        icon={<AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />}
                        label="Needs Admin Attention"
                        value={(data.unassignedCount + data.overdueCount).toString()}
                        subtext={`${data.unassignedCount} unassigned · ${data.overdueCount} overdue follow-ups`}
                        valueClassName={
                            data.unassignedCount + data.overdueCount > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : undefined
                        }
                    />
                    <StatCard
                        icon={<Users className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />}
                        label="Team Accounts"
                        value={stats.users_total.toString()}
                        subtext={`${stats.admins_total} admins · ${stats.crew_total} crew`}
                    />
                </div>

                {/* Team Workload & Rep Performance Table */}
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <CardTitle className="text-base">Team Workload & Performance</CardTitle>
                                <CardDescription>
                                    Lead ownership, overdue follow-ups, and pipeline value across team members.
                                </CardDescription>
                            </div>
                            {data.unassignedCount > 0 && (
                                <Badge variant="secondary" className="bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                    {data.unassignedCount} unassigned active lead{data.unassignedCount === 1 ? '' : 's'}
                                </Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left text-xs font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-2.5">Team Member</th>
                                        <th className="px-4 py-2.5">Role</th>
                                        <th className="px-4 py-2.5 text-right">Open Leads</th>
                                        <th className="px-4 py-2.5 text-right">Overdue</th>
                                        <th className="px-4 py-2.5 text-right">Won Deals</th>
                                        <th className="px-4 py-2.5 text-right">Open Pipeline</th>
                                        <th className="px-4 py-2.5 text-right">Won Value</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {data.teamRows.map((row) => (
                                        <tr key={row.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-2.5 font-medium">{row.name}</td>
                                            <td className="px-4 py-2.5">
                                                <Badge variant="outline" className="text-[11px] capitalize">
                                                    {row.role}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">{row.activeCount}</td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {row.overdueCount > 0 ? (
                                                    <span className="font-semibold text-red-600 dark:text-red-400">
                                                        {row.overdueCount}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">{row.wonCount}</td>
                                            <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                {formatCurrency(row.activeValue)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-medium text-emerald-700 tabular-nums dark:text-emerald-400">
                                                {formatCurrency(row.wonValue)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Two-Column Oversight Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Unassigned Active Leads */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShieldCheck className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                Unassigned Open Leads ({data.unassignedCount})
                            </CardTitle>
                            <CardDescription>
                                Active leads waiting to be assigned to a team member.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {data.unassignedLeads.length === 0 ? (
                                <p className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                    All active leads have an assigned team member.
                                </p>
                            ) : (
                                <div className="divide-border divide-y rounded-lg border">
                                    {data.unassignedLeads.map((lead) => (
                                        <Link
                                            key={lead.id}
                                            href={`/admin/leads/${lead.id}`}
                                            className="hover:bg-muted/50 flex items-center justify-between gap-3 p-3 transition-colors"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium">
                                                        {lead.first_name} {lead.last_name}
                                                    </span>
                                                    <PriorityBadge priority={lead.priority} />
                                                    <StatusPill status={lead.status} />
                                                </div>
                                                <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                                    {lead.email} · {lead.phone}
                                                </p>
                                            </div>
                                            {lead.estimated_value_cents !== null && (
                                                <span className="text-xs font-semibold tabular-nums">
                                                    {formatCurrency(lead.estimated_value_cents)}
                                                </span>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top Open Opportunities */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <DollarSign className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                Top Open Opportunities
                            </CardTitle>
                            <CardDescription>
                                Highest-value active deals currently moving through the pipeline.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {data.topOpportunities.length === 0 ? (
                                <p className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                    No open opportunities in the pipeline.
                                </p>
                            ) : (
                                <div className="divide-border divide-y rounded-lg border">
                                    {data.topOpportunities.map((lead) => {
                                        const owner = lead.assigned_to_user_id
                                            ? (data.userMap.get(lead.assigned_to_user_id)?.name ?? 'Assigned')
                                            : 'Unassigned';
                                        return (
                                            <Link
                                                key={lead.id}
                                                href={`/admin/leads/${lead.id}`}
                                                className="hover:bg-muted/50 flex items-center justify-between gap-3 p-3 transition-colors"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium">
                                                            {lead.first_name} {lead.last_name}
                                                        </span>
                                                        <StatusPill status={lead.status} />
                                                    </div>
                                                    <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                                        Owner: {owner}
                                                        {lead.build_location ? ` · ${lead.build_location}` : ''}
                                                    </p>
                                                </div>
                                                <span className="text-sm font-semibold text-emerald-700 tabular-nums dark:text-emerald-400">
                                                    {lead.estimated_value_cents !== null
                                                        ? formatCurrency(lead.estimated_value_cents)
                                                        : '—'}
                                                </span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Lost Deals & Reasons */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingDown className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                                Lost Deals & Feedback ({data.lostCount})
                            </CardTitle>
                            <CardDescription>
                                Recently lost leads and recorded reasons to spot pricing or timing trends.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {data.recentLost.length === 0 ? (
                                <p className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                    No lost leads recorded.
                                </p>
                            ) : (
                                <div className="divide-border divide-y rounded-lg border">
                                    {data.recentLost.map((lead) => (
                                        <Link
                                            key={lead.id}
                                            href={`/admin/leads/${lead.id}`}
                                            className="hover:bg-muted/50 block p-3 transition-colors"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium">
                                                    {lead.first_name} {lead.last_name}
                                                </span>
                                                {lead.estimated_value_cents !== null && (
                                                    <span className="text-muted-foreground text-xs font-medium tabular-nums">
                                                        {formatCurrency(lead.estimated_value_cents)}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {lead.lost_reason
                                                    ? `Reason: "${lead.lost_reason}"`
                                                    : 'No lost reason recorded'}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Team Activity */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Activity className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                Recent Team Activity
                            </CardTitle>
                            <CardDescription>
                                Latest calls, emails, notes, and status changes logged by the team.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentActivities.length === 0 ? (
                                <p className="text-muted-foreground rounded-lg border border-dashed py-8 text-center text-sm">
                                    No recent activity logged yet.
                                </p>
                            ) : (
                                <div className="divide-border divide-y rounded-lg border">
                                    {recentActivities.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={`/admin/leads/${item.lead_id}`}
                                            className="hover:bg-muted/50 flex items-center justify-between gap-3 p-3 transition-colors"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium">
                                                        {item.lead_name}
                                                    </span>
                                                    <Badge variant="secondary" className="text-[11px] capitalize">
                                                        {item.activity_type}
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                                    {item.title}
                                                </p>
                                            </div>
                                            <div className="text-muted-foreground shrink-0 text-right text-[11px]">
                                                <div>
                                                    {item.created_at
                                                        ? format(parseISO(item.created_at), 'MMM d')
                                                        : ''}
                                                </div>
                                                <div>{item.actor_name}</div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({
    icon,
    label,
    value,
    subtext,
    valueClassName,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    subtext: string;
    valueClassName?: string;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="text-muted-foreground flex items-center justify-between gap-2 text-xs font-medium">
                    <span>{label}</span>
                    {icon}
                </div>
                <div className={cn('mt-2 text-2xl font-semibold tabular-nums', valueClassName)}>{value}</div>
                <div className="text-muted-foreground mt-1 text-xs">{subtext}</div>
            </CardContent>
        </Card>
    );
}
