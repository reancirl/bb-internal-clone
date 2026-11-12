import { StatusPill } from '@/components/leads/status-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { LEAD_FUNNELS, type CrmUser, type Lead, type LeadFunnel } from '@/types/crm';
import { Head, Link, router } from '@inertiajs/react';
import { isBefore, startOfDay } from 'date-fns';
import { AlertCircle, ArrowLeft, Award, DollarSign, Target } from 'lucide-react';
import { useMemo } from 'react';

const STATUS_ORDER: Lead['status'][] = [
    'new',
    'contacted',
    'qualified',
    'meeting_scheduled',
    'proposal_sent',
    'won',
    'lost',
];

const STAGE_BAR_COLORS: Record<Lead['status'], string> = {
    new: 'bg-blue-500',
    contacted: 'bg-sky-500',
    qualified: 'bg-indigo-500',
    meeting_scheduled: 'bg-violet-500',
    proposal_sent: 'bg-amber-500',
    won: 'bg-emerald-500',
    lost: 'bg-rose-400',
};


function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

function parseLocalDate(date: string): Date {
    const [y, m, d] = date.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function formatFollowUp(date: string | null): string {
    if (!date) return '—';
    return parseLocalDate(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

interface LeadsAnalyticsProps {
    leads: Lead[];
    users: CrmUser[];
    funnel: LeadFunnel;
}

export default function LeadsAnalytics({ leads, users, funnel }: LeadsAnalyticsProps) {
    const pipelineHref = funnel === 'cornerstone' ? '/admin/leads?funnel=cornerstone' : '/admin/leads';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'CRM Pipeline', href: pipelineHref },
        { title: 'Analytics', href: funnel === 'general' ? '/admin/leads/analytics' : `/admin/leads/analytics?funnel=${funnel}` },
    ];
    const stats = useMemo(() => {
        const today = startOfDay(new Date());

        const openLeads = leads.filter((l) => l.status !== 'won' && l.status !== 'lost');
        const wonLeads = leads.filter((l) => l.status === 'won');
        const lostLeads = leads.filter((l) => l.status === 'lost');

        const openPipelineCents = openLeads.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
        const wonValueCents = wonLeads.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);

        const closedCount = wonLeads.length + lostLeads.length;
        const winRate = closedCount > 0 ? Math.round((wonLeads.length / closedCount) * 100) : null;

        const leadsWithValue = leads.filter((l) => (l.estimated_value_cents ?? 0) > 0);
        const avgDealCents =
            leadsWithValue.length > 0
                ? Math.round(
                      leadsWithValue.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0) /
                          leadsWithValue.length,
                  )
                : 0;

        // Stage breakdown with both count and dollar value
        const stages = STATUS_ORDER.map((status) => {
            const matching = leads.filter((l) => l.status === status);
            const valueCents = matching.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
            const shareOfLeads = leads.length > 0 ? Math.round((matching.length / leads.length) * 100) : 0;
            return {
                status,
                count: matching.length,
                valueCents,
                shareOfLeads,
            };
        });

        // Overdue follow-ups
        const overdue = openLeads
            .filter((l) => l.next_follow_up_date && isBefore(parseLocalDate(l.next_follow_up_date), today))
            .sort((a, b) => (a.next_follow_up_date ?? '').localeCompare(b.next_follow_up_date ?? ''));

        // Lead source performance (count, won count, total value)
        const sourceMap = new Map<string, { source: string; count: number; wonCount: number; valueCents: number }>();
        leads.forEach((l) => {
            const key = l.source || 'other';
            const entry = sourceMap.get(key) ?? { source: key, count: 0, wonCount: 0, valueCents: 0 };
            entry.count += 1;
            if (l.status === 'won') entry.wonCount += 1;
            entry.valueCents += l.estimated_value_cents ?? 0;
            sourceMap.set(key, entry);
        });
        const bySource = Array.from(sourceMap.values()).sort((a, b) => b.count - a.count);

        // Team workload (active count, won count, active pipeline value)
        const userNameMap = new Map<number, string>();
        users.forEach((u) => userNameMap.set(u.id, u.name));

        const repMap = new Map<
            string,
            { label: string; activeCount: number; wonCount: number; activeValueCents: number }
        >();
        leads.forEach((l) => {
            const key = l.assigned_to_user_id ? `user-${l.assigned_to_user_id}` : 'unassigned';
            const label = l.assigned_to_user_id
                ? (userNameMap.get(l.assigned_to_user_id) ?? 'Assigned')
                : 'Unassigned';
            const entry = repMap.get(key) ?? { label, activeCount: 0, wonCount: 0, activeValueCents: 0 };
            if (l.status !== 'won' && l.status !== 'lost') {
                entry.activeCount += 1;
                entry.activeValueCents += l.estimated_value_cents ?? 0;
            } else if (l.status === 'won') {
                entry.wonCount += 1;
            }
            repMap.set(key, entry);
        });
        const byRep = Array.from(repMap.values()).sort((a, b) => b.activeValueCents - a.activeValueCents);

        return {
            openLeads,
            wonLeads,
            lostLeads,
            openPipelineCents,
            wonValueCents,
            winRate,
            avgDealCents,
            stages,
            overdue,
            bySource,
            byRep,
            userNameMap,
        };
    }, [leads, users]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pipeline Analytics" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Pipeline Analytics</h1>
                        <p className="text-muted-foreground text-sm">
                            {funnel === 'cornerstone'
                                ? 'Cornerstone / spec homes only. Sales pipeline deals are not included.'
                                : 'Sales pipeline only. Cornerstone / spec homes deals are not included.'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={pipelineHref} prefetch>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Pipeline
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {LEAD_FUNNELS.map((item) => (
                        <Button
                            key={item.value}
                            size="sm"
                            variant={funnel === item.value ? 'default' : 'outline'}
                            onClick={() => {
                                if (item.value === funnel) return;
                                router.get(
                                    '/admin/leads/analytics',
                                    item.value === 'general' ? {} : { funnel: item.value },
                                );
                            }}
                        >
                            {item.label}
                        </Button>
                    ))}
                </div>

                {/* Top 4 KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        icon={<DollarSign className="h-4 w-4 text-blue-600 dark:text-blue-400" />}
                        label="Open Pipeline Value"
                        value={formatCurrency(stats.openPipelineCents)}
                        subtext={`${stats.openLeads.length} active deal${stats.openLeads.length === 1 ? '' : 's'} in progress`}
                    />
                    <SummaryCard
                        icon={<Award className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />}
                        label="Closed Won Value"
                        value={formatCurrency(stats.wonValueCents)}
                        subtext={`${stats.wonLeads.length} won · ${stats.lostLeads.length} lost`}
                    />
                    <SummaryCard
                        icon={<Target className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />}
                        label="Win Rate"
                        value={stats.winRate !== null ? `${stats.winRate}%` : '—'}
                        subtext={`Avg. est. deal: ${formatCurrency(stats.avgDealCents)}`}
                    />
                    <SummaryCard
                        icon={<AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />}
                        label="Overdue Follow-ups"
                        value={stats.overdue.length.toString()}
                        subtext={
                            stats.overdue.length > 0
                                ? 'Active leads past their follow-up date'
                                : 'All active leads on schedule'
                        }
                        accent={stats.overdue.length > 0 ? 'text-red-600 dark:text-red-400' : undefined}
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left Column: Stage Breakdown & Lead Sources */}
                    <div className="space-y-6 lg:col-span-7">
                        {/* Pipeline Stage Breakdown */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Pipeline by Stage</CardTitle>
                                <CardDescription>
                                    Lead count and total estimated project value in each stage ({leads.length} total leads).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {stats.stages.map((stage) => (
                                    <div key={stage.status} className="space-y-1.5">
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <div className="flex items-center gap-2.5">
                                                <StatusPill status={stage.status} />
                                                <span className="text-muted-foreground text-xs">
                                                    {stage.count} lead{stage.count === 1 ? '' : 's'} ({stage.shareOfLeads}%)
                                                </span>
                                            </div>
                                            <span className="font-semibold tabular-nums">
                                                {formatCurrency(stage.valueCents)}
                                            </span>
                                        </div>
                                        <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all',
                                                    STAGE_BAR_COLORS[stage.status],
                                                )}
                                                style={{ width: `${stage.shareOfLeads}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Lead Source Breakdown */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Lead Sources</CardTitle>
                                <CardDescription>
                                    Where leads originate and how much value each channel brings.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {stats.bySource.length === 0 ? (
                                    <p className="text-muted-foreground py-6 text-center text-sm">No leads yet.</p>
                                ) : (
                                    <div className="divide-border divide-y rounded-lg border">
                                        {stats.bySource.map((item) => (
                                            <div
                                                key={item.source}
                                                className="flex items-center justify-between gap-4 p-3 text-sm"
                                            >
                                                <div>
                                                    <span className="font-medium capitalize">
                                                        {item.source.replace('_', ' ')}
                                                    </span>
                                                    <div className="text-muted-foreground text-xs">
                                                        {item.count} lead{item.count === 1 ? '' : 's'} · {item.wonCount}{' '}
                                                        won
                                                    </div>
                                                </div>
                                                <div className="text-right font-semibold tabular-nums">
                                                    {formatCurrency(item.valueCents)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Team Workload & Overdue Follow-ups */}
                    <div className="space-y-6 lg:col-span-5">
                        {/* Team Ownership */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Pipeline by Owner</CardTitle>
                                <CardDescription>
                                    Open deals and won count assigned to each team member.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {stats.byRep.length === 0 ? (
                                    <p className="text-muted-foreground py-6 text-center text-sm">No leads yet.</p>
                                ) : (
                                    <div className="divide-border divide-y rounded-lg border">
                                        {stats.byRep.map((rep) => (
                                            <div
                                                key={rep.label}
                                                className="flex items-center justify-between gap-4 p-3 text-sm"
                                            >
                                                <div>
                                                    <span
                                                        className={cn(
                                                            'font-medium',
                                                            rep.label === 'Unassigned' &&
                                                                'text-amber-700 dark:text-amber-400',
                                                        )}
                                                    >
                                                        {rep.label}
                                                    </span>
                                                    <div className="text-muted-foreground text-xs">
                                                        {rep.activeCount} open · {rep.wonCount} won
                                                    </div>
                                                </div>
                                                <div className="text-right font-semibold tabular-nums">
                                                    {formatCurrency(rep.activeValueCents)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Overdue Follow-ups */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Overdue Follow-ups</CardTitle>
                                <CardDescription>Active leads that need a follow-up call or email.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {stats.overdue.length === 0 ? (
                                    <p className="text-muted-foreground rounded-lg border border-dashed py-6 text-center text-sm">
                                        No overdue follow-ups.
                                    </p>
                                ) : (
                                    <ul className="space-y-2.5">
                                        {stats.overdue.map((lead) => {
                                            const assignee = lead.assigned_to_user_id
                                                ? stats.userNameMap.get(lead.assigned_to_user_id)
                                                : null;
                                            return (
                                                <li key={lead.id}>
                                                    <Link
                                                        href={`/admin/leads/${lead.id}`}
                                                        className="block rounded-lg border bg-red-50/70 p-3 transition-colors hover:bg-red-100/80 dark:bg-red-950/30 dark:hover:bg-red-950/50"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="truncate text-sm font-medium">
                                                                {lead.first_name} {lead.last_name}
                                                            </span>
                                                            <Badge
                                                                variant="secondary"
                                                                className="shrink-0 bg-red-200 text-red-800 dark:bg-red-900 dark:text-red-200"
                                                            >
                                                                Due {formatFollowUp(lead.next_follow_up_date)}
                                                            </Badge>
                                                        </div>
                                                        <div className="mt-1 flex items-center justify-between text-xs text-red-700 dark:text-red-300">
                                                            <span>{assignee ? `Owner: ${assignee}` : 'Unassigned'}</span>
                                                            {lead.estimated_value_cents !== null && (
                                                                <span className="font-semibold tabular-nums">
                                                                    {formatCurrency(lead.estimated_value_cents)}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </Link>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    icon,
    label,
    value,
    subtext,
    accent,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    subtext: string;
    accent?: string;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="text-muted-foreground flex items-center justify-between gap-1.5 text-xs font-medium">
                    <span>{label}</span>
                    {icon}
                </div>
                <div className={cn('mt-2 text-2xl font-semibold tabular-nums', accent)}>{value}</div>
                <p className="text-muted-foreground mt-1 text-xs">{subtext}</p>
            </CardContent>
        </Card>
    );
}
