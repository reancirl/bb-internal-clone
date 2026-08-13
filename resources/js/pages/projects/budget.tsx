import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { BudgetLine, BudgetSectionOption, MoneyField } from '@/types/budget';
import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ArrowLeft, Circle, CircleCheck, Download, ListPlus, Plus, Trash2, X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface BudgetPageProps {
  project: { id: number; name: string; client_name: string | null };
  sections: BudgetSectionOption[];
  lines: BudgetLine[];
}

function formatMoney(cents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
  }).format(cents / 100);
}

function centsToInput(cents: number | null): string {
  return cents === null ? '' : (cents / 100).toString();
}

function inputToCents(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === '') return null;
  const parsed = parseFloat(trimmed);
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

const MONEY_COLUMNS: Array<{ field: MoneyField; label: string; group: string }> = [
  { field: 'bid_sub_cents', label: 'Bid', group: 'Sub' },
  { field: 'actual_sub_cents', label: 'Actual', group: 'Sub' },
  { field: 'estimated_material_cents', label: 'Est.', group: 'Material' },
  { field: 'actual_material_cents', label: 'Actual', group: 'Material' },
  { field: 'estimated_labor_cents', label: 'Est.', group: 'Labor' },
  { field: 'actual_labor_cents', label: 'Actual', group: 'Labor' },
];

export default function ProjectBudget({ project, sections, lines }: BudgetPageProps) {
  const [confirm, confirmDialog] = useConfirm();
  const [activeSectionId, setActiveSectionId] = useState<number | null>(null);
  const [addingLine, setAddingLine] = useState(false);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: project.name, href: `/projects/${project.id}` },
    { title: 'Budget', href: `/projects/${project.id}/budget` },
  ];

  const totals = useMemo(() => {
    const budgeted = lines.reduce((s, l) => s + l.budgeted_cents, 0);
    const actual = lines.reduce((s, l) => s + l.actual_cents, 0);
    return { budgeted, actual, variance: budgeted - actual };
  }, [lines]);

  const bySection = useMemo(() => {
    const map = new Map<number, { section: BudgetSectionOption; rows: BudgetLine[]; budgeted: number; actual: number }>();
    for (const section of sections) {
      map.set(section.id, { section, rows: [], budgeted: 0, actual: 0 });
    }
    for (const l of lines) {
      if (!map.has(l.section_id)) {
        map.set(l.section_id, { section: { id: l.section_id, name: l.section_name }, rows: [], budgeted: 0, actual: 0 });
      }
      const g = map.get(l.section_id)!;
      g.rows.push(l);
      g.budgeted += l.budgeted_cents;
      g.actual += l.actual_cents;
    }
    return Array.from(map.values());
  }, [sections, lines]);

  const activeGroup = bySection.find((g) => g.section.id === activeSectionId) ?? bySection[0] ?? null;

  const generate = () => {
    router.post(`/projects/${project.id}/budget/generate`, {}, { preserveScroll: true });
  };

  const removeLine = async (l: BudgetLine) => {
    const ok = await confirm({
      title: 'Remove budget line?',
      description: `"${l.name}" and its amounts will be removed from this project's budget.`,
      confirmLabel: 'Remove',
      destructive: true,
    });
    if (ok) router.delete(`/budget-lines/${l.id}`, { preserveScroll: true });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Budget — ${project.name}`} />
      {confirmDialog}

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-4">
            <Button variant="outline" size="icon" asChild>
              <Link href={`/projects/${project.id}`}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <h1 className="text-foreground text-2xl font-semibold">Budget — Bid vs Actual</h1>
              <p className="text-muted-foreground text-sm">
                {project.name}
                {project.client_name ? ` · ${project.client_name}` : ''}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-4">
            {lines.length > 0 && (
              <div className="text-right text-sm">
                <div>
                  <span className="text-muted-foreground">Budgeted </span>
                  <span className="font-semibold tabular-nums">{formatMoney(totals.budgeted)}</span>
                  <span className="text-muted-foreground"> · Actual </span>
                  <span className="font-semibold tabular-nums">{formatMoney(totals.actual)}</span>
                </div>
                <div
                  className={cn(
                    'text-xs font-medium tabular-nums',
                    totals.variance < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400',
                  )}
                >
                  {totals.variance < 0 ? formatMoney(totals.variance) + ' over budget' : formatMoney(totals.variance) + ' remaining'}
                </div>
              </div>
            )}
            <div className="flex gap-2">
              {lines.length > 0 && (
                <Button variant="outline" className="gap-2" asChild>
                  <a href={`/projects/${project.id}/budget/export`}>
                    <Download className="h-4 w-4" />
                    CSV
                  </a>
                </Button>
              )}
              <Button onClick={generate} className="gap-2">
                <ListPlus className="h-4 w-4" />
                Generate from catalog
              </Button>
            </div>
          </div>
        </div>

        {lines.length === 0 ? (
          <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-8 text-center">
            <ListPlus className="text-muted-foreground/40 h-10 w-10" />
            <div>
              <p className="text-foreground font-medium">No budget lines yet</p>
              <p className="text-muted-foreground text-sm">
                Generate the standard budget grid from the catalog, then fill in bid and actual amounts.
              </p>
            </div>
            <Button onClick={generate} className="gap-2">
              <ListPlus className="h-4 w-4" />
              Generate from catalog
            </Button>
          </div>
        ) : (
          <div className="flex flex-col gap-4 lg:grid lg:grid-cols-[240px_minmax(0,1fr)] lg:items-start">
            {/* Section nav */}
            <nav className="flex gap-1 overflow-x-auto pb-1 lg:sticky lg:top-4 lg:flex-col lg:overflow-visible lg:pb-0">
              {bySection.map((g) => {
                const isActive = activeGroup?.section.id === g.section.id;
                const variance = g.budgeted - g.actual;
                return (
                  <button
                    key={g.section.id}
                    onClick={() => {
                      setActiveSectionId(g.section.id);
                      setAddingLine(false);
                    }}
                    className={cn(
                      'flex shrink-0 items-center gap-2 rounded-md px-2.5 py-1.5 text-sm whitespace-nowrap transition-colors',
                      isActive ? 'bg-accent text-accent-foreground font-medium' : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    )}
                  >
                    {g.actual > 0 && variance < 0 ? (
                      <Circle className="h-3.5 w-3.5 shrink-0 fill-red-500 text-red-500" />
                    ) : g.actual > 0 ? (
                      <CircleCheck className="h-3.5 w-3.5 shrink-0 text-green-600 dark:text-green-400" />
                    ) : (
                      <Circle className="text-muted-foreground/50 h-3.5 w-3.5 shrink-0" />
                    )}
                    <span className="min-w-0 flex-1 truncate text-left capitalize">{g.section.name.toLowerCase()}</span>
                    <span className="text-muted-foreground text-xs tabular-nums">{g.rows.length}</span>
                  </button>
                );
              })}
            </nav>

            {/* Active section grid */}
            {activeGroup && (
              <div className="min-w-0 space-y-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-3">
                    <h2 className="text-foreground text-lg font-semibold capitalize">
                      {activeGroup.section.name.toLowerCase()}
                    </h2>
                    <span className="text-muted-foreground text-sm tabular-nums">
                      {formatMoney(activeGroup.budgeted)} budgeted · {formatMoney(activeGroup.actual)} actual
                    </span>
                  </div>
                  <Button variant="outline" size="sm" className="gap-1" onClick={() => setAddingLine(true)}>
                    <Plus className="h-3.5 w-3.5" />
                    Add line
                  </Button>
                </div>

                <Card>
                  <CardContent className="overflow-x-auto p-0">
                    <table className="w-full min-w-[56rem] text-sm">
                      <thead>
                        <tr className="text-muted-foreground border-b text-xs">
                          <th className="px-3 py-2 text-left font-medium">Budget item</th>
                          <th className="border-l px-2 py-2 text-center font-medium" colSpan={2}>
                            Sub cost
                          </th>
                          <th className="border-l px-2 py-2 text-center font-medium" colSpan={2}>
                            Material
                          </th>
                          <th className="border-l px-2 py-2 text-center font-medium" colSpan={2}>
                            Labor
                          </th>
                          <th className="border-l px-3 py-2 text-right font-medium">Variance</th>
                          <th className="w-8" />
                        </tr>
                        <tr className="text-muted-foreground border-b text-xs">
                          <th className="px-3 py-1 text-left font-normal">Notes inline</th>
                          {MONEY_COLUMNS.map((c, i) => (
                            <th key={c.field} className={cn('px-2 py-1 text-right font-normal', i % 2 === 0 && 'border-l')}>
                              {c.label}
                            </th>
                          ))}
                          <th className="border-l px-3 py-1 text-right font-normal">Bud − Act</th>
                          <th className="w-8" />
                        </tr>
                      </thead>
                      <tbody>
                        {addingLine && (
                          <NewLineRow
                            projectId={project.id}
                            sectionId={activeGroup.section.id}
                            onDone={() => setAddingLine(false)}
                          />
                        )}
                        {activeGroup.rows.map((l) => (
                          <BudgetRow key={l.id} line={l} onRemove={() => removeLine(l)} />
                        ))}
                        {activeGroup.rows.length === 0 && !addingLine && (
                          <tr>
                            <td colSpan={9} className="text-muted-foreground/70 px-3 py-4 text-sm">
                              No lines in this section — use "Add line" for project-specific entries
                              {activeGroup.section.name === 'CHANGE ORDERS' && ' (change orders are always added per project)'}
                              .
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </CardContent>
                </Card>
              </div>
            )}
          </div>
        )}
      </div>
    </AppLayout>
  );
}

function BudgetRow({ line: l, onRemove }: { line: BudgetLine; onRemove: () => void }) {
  const [values, setValues] = useState<Record<MoneyField, string>>({
    bid_sub_cents: centsToInput(l.bid_sub_cents),
    actual_sub_cents: centsToInput(l.actual_sub_cents),
    estimated_material_cents: centsToInput(l.estimated_material_cents),
    actual_material_cents: centsToInput(l.actual_material_cents),
    estimated_labor_cents: centsToInput(l.estimated_labor_cents),
    actual_labor_cents: centsToInput(l.actual_labor_cents),
  });
  const [notes, setNotes] = useState(l.notes ?? '');

  const saveMoney = (field: MoneyField) => {
    const cents = inputToCents(values[field]);
    if (cents !== l[field]) {
      router.put(`/budget-lines/${l.id}`, { [field]: cents }, { preserveScroll: true });
    }
  };

  return (
    <tr className="group border-b last:border-0">
      <td className="max-w-64 px-3 py-1.5">
        <div className="truncate font-medium">{l.name}</div>
        <Input
          value={notes}
          placeholder="Notes"
          onChange={(e) => setNotes(e.target.value)}
          onBlur={() => {
            if ((notes.trim() || null) !== l.notes) {
              router.put(`/budget-lines/${l.id}`, { notes: notes.trim() || null }, { preserveScroll: true });
            }
          }}
          className="text-muted-foreground mt-0.5 h-6 border-transparent bg-transparent px-1 text-xs shadow-none focus-visible:border-input"
        />
      </td>
      {MONEY_COLUMNS.map((c, i) => (
        <td key={c.field} className={cn('px-1.5 py-1.5 text-right', i % 2 === 0 && 'border-l')}>
          <Input
            type="number"
            min="0"
            step="100"
            placeholder="—"
            value={values[c.field]}
            onChange={(e) => setValues((prev) => ({ ...prev, [c.field]: e.target.value }))}
            onBlur={() => saveMoney(c.field)}
            className="h-7 w-24 px-1.5 text-right text-sm tabular-nums"
          />
        </td>
      ))}
      <td
        className={cn(
          'border-l px-3 py-1.5 text-right font-medium tabular-nums',
          l.variance_cents < 0
            ? 'text-red-600 dark:text-red-400'
            : l.actual_cents > 0
              ? 'text-green-700 dark:text-green-400'
              : 'text-muted-foreground',
        )}
      >
        {l.budgeted_cents === 0 && l.actual_cents === 0 ? '—' : formatMoney(l.variance_cents)}
      </td>
      <td className="px-1 py-1.5">
        <Button
          variant="ghost"
          size="icon"
          className="text-muted-foreground hover:text-destructive h-6 w-6 opacity-0 group-hover:opacity-100"
          onClick={onRemove}
        >
          <Trash2 className="h-3.5 w-3.5" />
        </Button>
      </td>
    </tr>
  );
}

function NewLineRow({ projectId, sectionId, onDone }: { projectId: number; sectionId: number; onDone: () => void }) {
  const [name, setName] = useState('');

  const save = () => {
    if (!name.trim()) return;
    router.post(
      `/projects/${projectId}/budget/lines`,
      { budget_section_id: sectionId, name: name.trim() },
      { preserveScroll: true, onSuccess: onDone },
    );
  };

  return (
    <tr className="bg-muted/30 border-b border-dashed">
      <td className="px-3 py-2" colSpan={7}>
        <Input
          placeholder="New line name (e.g. Change Order #1 — extra window)"
          value={name}
          onChange={(e) => setName(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && save()}
          autoFocus
        />
      </td>
      <td className="px-2 py-2 text-right" colSpan={2}>
        <div className="flex justify-end gap-1">
          <Button size="sm" onClick={save} disabled={!name.trim()}>
            Add
          </Button>
          <Button size="sm" variant="ghost" onClick={onDone}>
            <X className="h-3.5 w-3.5" />
          </Button>
        </div>
      </td>
    </tr>
  );
}
