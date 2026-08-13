import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart3, Download, ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ReportRow {
  id: number;
  name: string;
  client_name: string | null;
  status: string;
  lines_count: number;
  budgeted_cents: number;
  actual_cents: number;
  variance_cents: number;
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Reports', href: '/admin/reports' },
];

function formatMoney(cents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(cents / 100);
}

export default function Reports({ projects }: { projects: ReportRow[] }) {
  const totals = projects.reduce(
    (acc, p) => ({
      budgeted: acc.budgeted + p.budgeted_cents,
      actual: acc.actual + p.actual_cents,
    }),
    { budgeted: 0, actual: 0 },
  );
  const totalVariance = totals.budgeted - totals.actual;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Reports" />

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
          <h1 className="text-foreground text-2xl font-semibold">Reports</h1>
          <p className="text-muted-foreground text-sm">Cross-project financial reporting.</p>
        </div>

        <Card>
          <CardHeader>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <BarChart3 className="h-4 w-4" />
                  Budget Variance
                </CardTitle>
                <CardDescription>Bid vs actual across every project with a budget.</CardDescription>
              </div>
              {projects.length > 0 && (
                <div className="text-right text-sm">
                  <span className="text-muted-foreground">Budgeted </span>
                  <span className="font-semibold tabular-nums">{formatMoney(totals.budgeted)}</span>
                  <span className="text-muted-foreground"> · Actual </span>
                  <span className="font-semibold tabular-nums">{formatMoney(totals.actual)}</span>
                  <span
                    className={cn(
                      'ml-2 font-semibold tabular-nums',
                      totalVariance < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400',
                    )}
                  >
                    {formatMoney(totalVariance)}
                  </span>
                </div>
              )}
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {projects.length === 0 ? (
              <p className="text-muted-foreground px-6 pb-6 text-sm">
                No project budgets yet. Open a project and generate its budget from the catalog to see it here.
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[44rem] text-sm">
                  <thead>
                    <tr className="text-muted-foreground border-b text-xs">
                      <th className="px-4 py-2 text-left font-medium">Project</th>
                      <th className="px-4 py-2 text-left font-medium">Status</th>
                      <th className="px-4 py-2 text-right font-medium">Lines</th>
                      <th className="px-4 py-2 text-right font-medium">Budgeted</th>
                      <th className="px-4 py-2 text-right font-medium">Actual</th>
                      <th className="px-4 py-2 text-right font-medium">Variance</th>
                      <th className="w-24" />
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((p) => (
                      <tr key={p.id} className="border-b last:border-0">
                        <td className="px-4 py-2.5">
                          <div className="font-medium">{p.name}</div>
                          {p.client_name && <div className="text-muted-foreground text-xs">{p.client_name}</div>}
                        </td>
                        <td className="px-4 py-2.5">
                          <Badge variant="secondary" className="capitalize">
                            {p.status}
                          </Badge>
                        </td>
                        <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">{p.lines_count}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{formatMoney(p.budgeted_cents)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{formatMoney(p.actual_cents)}</td>
                        <td
                          className={cn(
                            'px-4 py-2.5 text-right font-semibold tabular-nums',
                            p.variance_cents < 0
                              ? 'text-red-600 dark:text-red-400'
                              : 'text-green-700 dark:text-green-400',
                          )}
                        >
                          {formatMoney(p.variance_cents)}
                        </td>
                        <td className="px-2 py-2.5">
                          <div className="flex justify-end gap-1">
                            <Button variant="ghost" size="icon" className="h-7 w-7" title="Download CSV" asChild>
                              <a href={`/projects/${p.id}/budget/export`}>
                                <Download className="h-3.5 w-3.5" />
                              </a>
                            </Button>
                            <Button variant="ghost" size="icon" className="h-7 w-7" title="Open budget" asChild>
                              <Link href={`/projects/${p.id}/budget`}>
                                <ExternalLink className="h-3.5 w-3.5" />
                              </Link>
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
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
