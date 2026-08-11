import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { type Lead, type CrmUser } from '@/types/crm';
import { StatusPill } from '@/components/leads/status-pill';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, TrendingUp, AlertCircle, DollarSign, Users } from 'lucide-react';
import { isBefore, parseISO, startOfDay } from 'date-fns';
import { cn } from '@/lib/utils';

const STATUS_LABELS: Record<Lead['status'], string> = {
  new: 'New',
  contacted: 'Contacted',
  qualified: 'Qualified',
  meeting_scheduled: 'Meeting Scheduled',
  proposal_sent: 'Proposal Sent',
  won: 'Won',
  lost: 'Lost',
};

const FUNNEL_STAGES: Lead['status'][] = [
  'new',
  'contacted',
  'qualified',
  'meeting_scheduled',
  'proposal_sent',
  'won',
];

const STATUS_ORDER: Lead['status'][] = [
  'new',
  'contacted',
  'qualified',
  'meeting_scheduled',
  'proposal_sent',
  'won',
  'lost',
];

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'CRM Pipeline', href: '/admin/leads' },
  { title: 'Analytics', href: '/admin/leads/analytics' },
];

function formatCurrency(cents: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(cents / 100);
}

interface LeadsAnalyticsProps {
  leads: Lead[];
  users: CrmUser[];
}

export default function LeadsAnalytics({ leads, users }: LeadsAnalyticsProps) {

  const stats = useMemo(() => {
    const counts = STATUS_ORDER.reduce(
      (acc, status) => {
        acc[status] = leads.filter((l) => l.status === status).length;
        return acc;
      },
      {} as Record<Lead['status'], number>,
    );

    const active = leads.filter((l) => l.status !== 'lost');
    const totalValue = active.reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);
    const wonValue = leads
      .filter((l) => l.status === 'won')
      .reduce((sum, l) => sum + (l.estimated_value_cents ?? 0), 0);

    const today = startOfDay(new Date());
    const overdue = leads.filter(
      (l) =>
        l.next_follow_up_date &&
        l.status !== 'won' &&
        l.status !== 'lost' &&
        isBefore(parseISO(l.next_follow_up_date), today),
    );

    const bySource = leads.reduce(
      (acc, l) => {
        const source = l.source || 'other';
        acc[source] = (acc[source] ?? 0) + 1;
        return acc;
      },
      {} as Record<string, number>,
    );
    const maxSource = Math.max(0, ...Object.values(bySource));

    const funnel = FUNNEL_STAGES.map((status, i) => {
      const count = counts[status];
      const previous = i > 0 ? counts[FUNNEL_STAGES[i - 1]] : null;
      const conversion =
        previous && previous > 0 ? Math.round((count / previous) * 100) : null;
      return { status, count, previous, conversion };
    });

    return { counts, totalValue, wonValue, overdue, bySource, maxSource, funnel };
  }, [leads]);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Pipeline Analytics" />

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-foreground text-2xl font-semibold">Pipeline Analytics</h1>
            <p className="text-muted-foreground text-sm">
              Performance metrics, conversion funnel, and follow-up reminders.
            </p>
          </div>
          <Button variant="outline" asChild>
            <Link href="/admin/leads" prefetch>
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Pipeline
            </Link>
          </Button>
        </div>

        {/* Summary cards */}
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          <StatCard
            icon={<Users className="h-4 w-4" />}
            label="Total Leads"
            value={leads.length.toString()}
          />
          <StatCard
            icon={<DollarSign className="h-4 w-4" />}
            label="Pipeline Value"
            value={formatCurrency(stats.totalValue)}
          />
          <StatCard
            icon={<TrendingUp className="h-4 w-4" />}
            label="Won Value"
            value={formatCurrency(stats.wonValue)}
          />
          <StatCard
            icon={<AlertCircle className="h-4 w-4" />}
            label="Overdue Follow-ups"
            value={stats.overdue.length.toString()}
            accent="text-red-600 dark:text-red-400"
          />
        </div>

        {/* Pipeline summary */}
        <Card>
          <CardHeader>
            <CardTitle>Pipeline Summary</CardTitle>
            <CardDescription>Leads currently in each pipeline stage.</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
              {STATUS_ORDER.map((status) => (
                <div key={status} className="rounded-lg border bg-muted/30 p-4 text-center">
                  <StatusPill status={status} className="mb-2" />
                  <div className="text-2xl font-semibold tabular-nums">{stats.counts[status]}</div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          {/* Conversion funnel */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Conversion Funnel</CardTitle>
              <CardDescription>Share of leads moving from one stage to the next.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {stats.funnel.map((step, i) => {
                const total = stats.counts[FUNNEL_STAGES[0]];
                const share = total > 0 ? Math.round((step.count / total) * 100) : 0;
                return (
                  <div key={step.status}>
                    <div className="mb-1 flex items-center justify-between text-sm">
                      <span className="font-medium">{STATUS_LABELS[step.status]}</span>
                      <span className="text-muted-foreground">
                        {step.count} lead{step.count === 1 ? '' : 's'}
                        {step.conversion !== null && (
                          <span className="ml-2 inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                            <TrendingUp className="h-3 w-3" />
                            {step.conversion}% from previous
                          </span>
                        )}
                      </span>
                    </div>
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                      <div
                        className={cn('h-full rounded-full', i === FUNNEL_STAGES.length - 1 ? 'bg-emerald-500' : 'bg-blue-500')}
                        style={{ width: `${share}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </CardContent>
          </Card>

          <div className="space-y-4">
            {/* Follow-up reminders */}
            <Card>
              <CardHeader>
                <CardTitle>Follow-up Reminders</CardTitle>
                <CardDescription>Leads past their follow-up date.</CardDescription>
              </CardHeader>
              <CardContent>
                {stats.overdue.length === 0 ? (
                  <p className="text-muted-foreground py-4 text-center text-sm">No overdue follow-ups.</p>
                ) : (
                  <ul className="space-y-3">
                    {stats.overdue.map((lead) => {
                      const assignee = users.find((u) => u.id === lead.assigned_to_user_id);
                      return (
                        <li key={lead.id}>
                          <Link
                            href={`/admin/leads/${lead.id}`}
                            className="block rounded-lg border bg-red-50 p-3 transition-colors hover:bg-red-100 dark:bg-red-950/30 dark:hover:bg-red-950/50"
                          >
                            <div className="flex items-center justify-between gap-2">
                              <span className="truncate text-sm font-medium">
                                {lead.first_name} {lead.last_name}
                              </span>
                              <Badge variant="secondary" className="shrink-0 bg-red-200 text-red-800 dark:bg-red-900 dark:text-red-200">
                                Overdue
                              </Badge>
                            </div>
                            <p className="mt-1 text-xs text-red-700 dark:text-red-300">
                              Due {formatFollowUp(lead.next_follow_up_date)}
                              {assignee && ` · ${assignee.name}`}
                            </p>
                          </Link>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </CardContent>
            </Card>

            {/* Lead source breakdown */}
            <Card>
              <CardHeader>
                <CardTitle>By Lead Source</CardTitle>
                <CardDescription>Where leads come from.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {Object.entries(stats.bySource)
                  .sort((a, b) => b[1] - a[1])
                  .map(([source, count]) => (
                    <div key={source}>
                      <div className="mb-1 flex items-center justify-between text-sm">
                        <span className="capitalize">{source.replace('_', ' ')}</span>
                        <span className="text-muted-foreground">{count}</span>
                      </div>
                      <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div
                          className="h-full rounded-full bg-indigo-500"
                          style={{ width: `${stats.maxSource > 0 ? (count / stats.maxSource) * 100 : 0}%` }}
                        />
                      </div>
                    </div>
                  ))}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}

function StatCard({
  icon,
  label,
  value,
  accent,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
  accent?: string;
}) {
  return (
    <Card>
      <CardContent className="p-4">
        <div className="text-muted-foreground flex items-center gap-1.5 text-sm">{icon}{label}</div>
        <div className={cn('mt-2 text-2xl font-semibold tabular-nums', accent)}>{value}</div>
      </CardContent>
    </Card>
  );
}

function formatFollowUp(date: string | null): string {
  if (!date) return '—';
  const [y, m, d] = date.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
