import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ProjectSelection, SelectionChoice, VendorOption } from '@/types/selections';
import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  ArrowLeft,
  CalendarClock,
  Check,
  ChevronDown,
  Circle,
  CircleCheck,
  ListPlus,
  Pencil,
  Plus,
  Trash2,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

interface SelectionsPageProps {
  project: { id: number; name: string; client_name: string | null };
  selections: ProjectSelection[];
  vendors: VendorOption[];
  isAdmin: boolean;
}

const SCOPE_LABELS = { shared: 'Whole build', living: 'Living', garage: 'Garage' };
const SCOPE_ORDER: Array<'shared' | 'living' | 'garage'> = ['shared', 'living', 'garage'];

function formatMoney(cents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
  }).format(cents / 100);
}

function centsToDollarsInput(cents: number | null): string {
  return cents === null ? '' : (cents / 100).toString();
}

function dollarsInputToCents(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === '') return null;
  const parsed = parseFloat(trimmed);
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

function isOverdue(s: ProjectSelection): boolean {
  if (!s.deadline_date || s.approved_choice_id !== null) return false;
  const [y, m, d] = s.deadline_date.split('-').map(Number);
  const deadline = new Date(y, m - 1, d);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return deadline < today;
}

interface CategoryGroup {
  category: ProjectSelection['category'];
  rows: ProjectSelection[];
  approved: number;
  overdue: number;
}

export default function ProjectSelections({ project, selections, vendors, isAdmin }: SelectionsPageProps) {
  const [confirm, confirmDialog] = useConfirm();
  const [approving, setApproving] = useState<{ selection: ProjectSelection; choice: SelectionChoice } | null>(null);
  const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: project.name, href: `/projects/${project.id}` },
    { title: 'Selections', href: `/projects/${project.id}/selections` },
  ];

  const stats = useMemo(() => {
    const approved = selections.filter((s) => s.approved_choice_id !== null);
    const overdue = selections.filter(isOverdue);
    const variance = approved.reduce((sum, s) => sum + (s.variance_cents ?? 0), 0);
    return { total: selections.length, approved: approved.length, overdue: overdue.length, variance };
  }, [selections]);

  // Group by category, preserving server order (category sort, then item sort).
  const groups = useMemo<CategoryGroup[]>(() => {
    const map = new Map<number, CategoryGroup>();
    for (const s of selections) {
      if (!map.has(s.category.id)) {
        map.set(s.category.id, { category: s.category, rows: [], approved: 0, overdue: 0 });
      }
      const g = map.get(s.category.id)!;
      g.rows.push(s);
      if (s.approved_choice_id !== null) g.approved += 1;
      if (isOverdue(s)) g.overdue += 1;
    }
    return Array.from(map.values());
  }, [selections]);

  const activeGroup = groups.find((g) => g.category.id === activeCategoryId) ?? groups[0] ?? null;

  const generate = () => {
    router.post(`/projects/${project.id}/selections/generate`, {}, { preserveScroll: true });
  };

  const removeSelection = async (s: ProjectSelection) => {
    const ok = await confirm({
      title: 'Remove selection?',
      description: `"${s.item.label}" and its choices will be removed from this project. The catalog is not affected.`,
      confirmLabel: 'Remove',
      destructive: true,
    });
    if (ok) router.delete(`/selections/${s.id}`, { preserveScroll: true });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Selections — ${project.name}`} />
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
              <h1 className="text-foreground text-2xl font-semibold">Customer Selections</h1>
              <p className="text-muted-foreground text-sm">
                {project.name}
                {project.client_name ? ` · ${project.client_name}` : ''}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-4">
            {selections.length > 0 && (
              <div className="text-right text-sm">
                <span className="font-semibold tabular-nums">
                  {stats.approved}/{stats.total}
                </span>{' '}
                <span className="text-muted-foreground">approved</span>
                {stats.overdue > 0 && (
                  <span className="ml-2 font-medium text-red-600 dark:text-red-400">{stats.overdue} overdue</span>
                )}
                {stats.variance !== 0 && (
                  <div
                    className={cn(
                      'text-xs font-medium',
                      stats.variance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400',
                    )}
                  >
                    {stats.variance > 0 ? '+' : '−'}
                    {formatMoney(Math.abs(stats.variance))} vs allowance
                  </div>
                )}
              </div>
            )}
            {isAdmin && (
              <Button onClick={generate} className="gap-2">
                <ListPlus className="h-4 w-4" />
                Generate from catalog
              </Button>
            )}
          </div>
        </div>

        {/* Empty state */}
        {selections.length === 0 ? (
          <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-8 text-center">
            <ListPlus className="text-muted-foreground/40 h-10 w-10" />
            <div>
              <p className="text-foreground font-medium">No selections yet</p>
              <p className="text-muted-foreground text-sm">
                Generate the selection list from the decision catalog to start the customer walkthrough.
              </p>
            </div>
            {isAdmin && (
              <Button onClick={generate} className="gap-2">
                <ListPlus className="h-4 w-4" />
                Generate from catalog
              </Button>
            )}
          </div>
        ) : (
          /* Master–detail: category nav left, one category's selections right */
          <div className="flex flex-col gap-4 lg:grid lg:grid-cols-[240px_minmax(0,1fr)] lg:items-start">
            {/* Category nav — sticky sidebar on desktop, horizontal scroller on mobile */}
            <nav className="flex gap-1 overflow-x-auto pb-1 lg:sticky lg:top-4 lg:flex-col lg:overflow-visible lg:pb-0">
              {SCOPE_ORDER.map((scope) => {
                const scoped = groups.filter((g) => g.category.scope === scope);
                if (scoped.length === 0) return null;
                return (
                  <div key={scope} className="flex shrink-0 gap-1 lg:flex-col">
                    <div className="text-muted-foreground hidden px-2 pt-3 pb-1 text-xs font-semibold tracking-wide uppercase first:pt-0 lg:block">
                      {SCOPE_LABELS[scope]}
                    </div>
                    {scoped.map((g) => {
                      const isActive = activeGroup?.category.id === g.category.id;
                      const done = g.approved === g.rows.length;
                      return (
                        <button
                          key={g.category.id}
                          onClick={() => {
                            setActiveCategoryId(g.category.id);
                            setExpandedId(null);
                          }}
                          className={cn(
                            'flex shrink-0 items-center gap-2 rounded-md px-2.5 py-1.5 text-sm whitespace-nowrap transition-colors',
                            isActive ? 'bg-accent text-accent-foreground font-medium' : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                          )}
                        >
                          {done ? (
                            <CircleCheck className="h-3.5 w-3.5 shrink-0 text-green-600 dark:text-green-400" />
                          ) : g.overdue > 0 ? (
                            <CalendarClock className="h-3.5 w-3.5 shrink-0 text-red-600 dark:text-red-400" />
                          ) : (
                            <Circle className="text-muted-foreground/50 h-3.5 w-3.5 shrink-0" />
                          )}
                          <span className="min-w-0 flex-1 truncate text-left capitalize">{g.category.name.toLowerCase()}</span>
                          <span className="text-muted-foreground text-xs tabular-nums">
                            {g.approved}/{g.rows.length}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                );
              })}
            </nav>

            {/* Active category */}
            {activeGroup && (
              <div className="min-w-0 space-y-2">
                <div className="flex items-center gap-2">
                  <h2 className="text-foreground text-lg font-semibold capitalize">
                    {activeGroup.category.name.toLowerCase()}
                  </h2>
                  <Badge variant="secondary">{SCOPE_LABELS[activeGroup.category.scope]}</Badge>
                  <span className="text-muted-foreground text-sm tabular-nums">
                    {activeGroup.approved}/{activeGroup.rows.length} approved
                  </span>
                </div>

                <Card>
                  <CardContent className="divide-y p-0">
                    {activeGroup.rows.map((s) => (
                      <SelectionRow
                        key={s.id}
                        selection={s}
                        vendors={vendors}
                        isAdmin={isAdmin}
                        expanded={expandedId === s.id}
                        onToggle={() => setExpandedId(expandedId === s.id ? null : s.id)}
                        onApprove={(choice) => setApproving({ selection: s, choice })}
                        onRemove={() => removeSelection(s)}
                      />
                    ))}
                  </CardContent>
                </Card>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Approval dialog — Buildertrend-style "approve on behalf of the customer" */}
      <ApproveDialog state={approving} onClose={() => setApproving(null)} />
    </AppLayout>
  );
}

/**
 * One selection as a compact row; expands in place for editing.
 */
function SelectionRow({
  selection: s,
  vendors,
  isAdmin,
  expanded,
  onToggle,
  onApprove,
  onRemove,
}: {
  selection: ProjectSelection;
  vendors: VendorOption[];
  isAdmin: boolean;
  expanded: boolean;
  onToggle: () => void;
  onApprove: (choice: SelectionChoice) => void;
  onRemove: () => void;
}) {
  const overdue = isOverdue(s);
  const approved = s.approved_choice_id !== null;
  const approvedChoice = s.choices.find((c) => c.id === s.approved_choice_id);

  return (
    <div className={cn(overdue && 'bg-red-50/50 dark:bg-red-950/20')}>
      {/* Collapsed summary row */}
      <button
        type="button"
        onClick={onToggle}
        className="hover:bg-accent/40 flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors"
      >
        {approved ? (
          <CircleCheck className="h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
        ) : overdue ? (
          <CalendarClock className="h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
        ) : (
          <Circle className="text-muted-foreground/40 h-4 w-4 shrink-0" />
        )}

        <div className="min-w-0 flex-1">
          <div className="truncate text-sm font-medium">{s.item.label}</div>
          <div className="text-muted-foreground truncate text-xs">
            {approved && approvedChoice ? (
              <>
                {approvedChoice.label}
                {approvedChoice.price_cents !== null && ` · ${formatMoney(approvedChoice.price_cents)}`}
              </>
            ) : s.choices.length > 0 ? (
              `${s.choices.length} choice${s.choices.length === 1 ? '' : 's'} presented`
            ) : (
              (s.item.recommended && `Recommended: ${s.item.recommended}`) || 'No choices yet'
            )}
          </div>
        </div>

        {s.variance_cents !== null && s.variance_cents !== 0 && (
          <span
            className={cn(
              'shrink-0 text-xs font-medium tabular-nums',
              s.variance_cents > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400',
            )}
          >
            {s.variance_cents > 0 ? '+' : '−'}
            {formatMoney(Math.abs(s.variance_cents))}
          </span>
        )}

        {s.deadline_date && !approved && (
          <span
            className={cn(
              'shrink-0 text-xs tabular-nums',
              overdue ? 'font-semibold text-red-600 dark:text-red-400' : 'text-muted-foreground',
            )}
          >
            {new Date(s.deadline_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
          </span>
        )}

        <ChevronDown
          className={cn('text-muted-foreground h-4 w-4 shrink-0 transition-transform', expanded && 'rotate-180')}
        />
      </button>

      {/* Expanded editor */}
      {expanded && (
        <div className="space-y-4 border-t border-dashed px-4 py-4">
          {s.item.guidance && <p className="text-muted-foreground text-sm">{s.item.guidance}</p>}

          {/* Allowance / deadline / notes */}
          <SelectionFields selection={s} isAdmin={isAdmin} />

          {/* Choices */}
          <div className="space-y-2">
            {s.choices.map((choice) => (
              <ChoiceRow
                key={choice.id}
                choice={choice}
                selection={s}
                vendors={vendors}
                isAdmin={isAdmin}
                onApprove={() => onApprove(choice)}
              />
            ))}
            {s.choices.length === 0 && <p className="text-muted-foreground/70 text-sm">No choices presented yet.</p>}
            {isAdmin && <AddChoice selection={s} vendors={vendors} />}
          </div>

          {/* Approval details + remove */}
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-muted-foreground text-xs">
              {s.approved_choice_id !== null && (
                <>
                  Approved{s.approved_by ? ` by ${s.approved_by}` : ''}
                  {s.approved_at ? ` on ${new Date(s.approved_at).toLocaleDateString()}` : ''}
                  {s.approval_comment ? ` — “${s.approval_comment}”` : ''}
                </>
              )}
            </p>
            {isAdmin && (
              <Button
                variant="ghost"
                size="sm"
                className="text-muted-foreground hover:text-destructive gap-1"
                onClick={onRemove}
              >
                <Trash2 className="h-3.5 w-3.5" />
                Remove from project
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function SelectionFields({ selection: s, isAdmin }: { selection: ProjectSelection; isAdmin: boolean }) {
  const [allowance, setAllowance] = useState(centsToDollarsInput(s.allowance_cents));
  const [deadline, setDeadline] = useState(s.deadline_date ?? '');
  const [notes, setNotes] = useState(s.notes ?? '');

  const saveField = (payload: Record<string, string | number | null>) => {
    router.put(`/selections/${s.id}`, payload, { preserveScroll: true });
  };

  if (!isAdmin) {
    return (
      <div className="text-muted-foreground grid gap-1 text-sm sm:grid-cols-3">
        <span>Allowance: {s.allowance_cents !== null ? formatMoney(s.allowance_cents) : '—'}</span>
        <span>Deadline: {s.deadline_date ?? '—'}</span>
        <span>Notes: {s.notes ?? '—'}</span>
      </div>
    );
  }

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      <div className="space-y-1">
        <Label className="text-muted-foreground text-xs">Allowance ($)</Label>
        <Input
          type="number"
          min="0"
          step="50"
          placeholder="No allowance"
          value={allowance}
          onChange={(e) => setAllowance(e.target.value)}
          onBlur={() => {
            const cents = dollarsInputToCents(allowance);
            if (cents !== s.allowance_cents) saveField({ allowance_cents: cents });
          }}
        />
      </div>
      <div className="space-y-1">
        <Label className="text-muted-foreground text-xs">Decision deadline</Label>
        <Input
          type="date"
          value={deadline}
          onChange={(e) => setDeadline(e.target.value)}
          onBlur={() => {
            if ((deadline || null) !== s.deadline_date) saveField({ deadline_date: deadline || null });
          }}
        />
      </div>
      <div className="space-y-1">
        <Label className="text-muted-foreground text-xs">Notes</Label>
        <Input
          placeholder="—"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          onBlur={() => {
            if ((notes.trim() || null) !== s.notes) saveField({ notes: notes.trim() || null });
          }}
        />
      </div>
    </div>
  );
}

function AddChoice({ selection, vendors }: { selection: ProjectSelection; vendors: VendorOption[] }) {
  const [adding, setAdding] = useState(false);

  if (!adding) {
    return (
      <Button variant="outline" size="sm" className="gap-1" onClick={() => setAdding(true)}>
        <Plus className="h-3.5 w-3.5" />
        Add choice
      </Button>
    );
  }

  return (
    <ChoiceForm
      vendors={vendors}
      onCancel={() => setAdding(false)}
      onSave={(data) => {
        router.post(`/selections/${selection.id}/choices`, data, {
          preserveScroll: true,
          onSuccess: () => setAdding(false),
          onError: () => toast.error('Could not add choice.'),
        });
      }}
    />
  );
}

function ChoiceRow({
  choice,
  selection,
  vendors,
  isAdmin,
  onApprove,
}: {
  choice: SelectionChoice;
  selection: ProjectSelection;
  vendors: VendorOption[];
  isAdmin: boolean;
  onApprove: () => void;
}) {
  const [editing, setEditing] = useState(false);
  const isApproved = selection.approved_choice_id === choice.id;

  if (editing) {
    return (
      <ChoiceForm
        vendors={vendors}
        initial={choice}
        onCancel={() => setEditing(false)}
        onSave={(data) => {
          router.put(`/selection-choices/${choice.id}`, data, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
            onError: () => toast.error('Could not update choice.'),
          });
        }}
      />
    );
  }

  return (
    <div
      className={cn(
        'flex items-center gap-3 rounded-md border p-2.5',
        isApproved
          ? 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/30'
          : 'bg-muted/30',
      )}
    >
      {isAdmin && (
        <Button
          variant={isApproved ? 'default' : 'outline'}
          size="sm"
          className={cn('h-7 gap-1 px-2', isApproved && 'bg-green-600 hover:bg-green-700')}
          onClick={onApprove}
          title={isApproved ? 'Remove approval' : 'Approve this choice'}
        >
          <Check className="h-3.5 w-3.5" />
          {isApproved ? 'Approved' : 'Approve'}
        </Button>
      )}
      {!isAdmin && isApproved && <CircleCheck className="h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />}

      <div className="min-w-0 flex-1">
        <span className="text-sm font-medium">{choice.label}</span>
        {choice.description && <span className="text-muted-foreground text-sm"> — {choice.description}</span>}
        {choice.vendor_name && <span className="text-muted-foreground text-xs"> · {choice.vendor_name}</span>}
      </div>

      <span className="text-sm font-semibold tabular-nums">
        {choice.price_cents !== null ? formatMoney(choice.price_cents) : '—'}
      </span>

      {isAdmin && (
        <div className="flex gap-0.5">
          <Button variant="ghost" size="icon" className="text-muted-foreground h-7 w-7" onClick={() => setEditing(true)}>
            <Pencil className="h-3.5 w-3.5" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="text-muted-foreground hover:text-destructive h-7 w-7"
            onClick={() => router.delete(`/selection-choices/${choice.id}`, { preserveScroll: true })}
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        </div>
      )}
    </div>
  );
}

function ChoiceForm({
  vendors,
  initial,
  onSave,
  onCancel,
}: {
  vendors: VendorOption[];
  initial?: SelectionChoice;
  onSave: (data: Record<string, string | number | null>) => void;
  onCancel: () => void;
}) {
  const [label, setLabel] = useState(initial?.label ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [price, setPrice] = useState(centsToDollarsInput(initial?.price_cents ?? null));
  const [vendorId, setVendorId] = useState(initial?.vendor_id?.toString() ?? '');

  const submit = () => {
    if (!label.trim()) return;
    onSave({
      label: label.trim(),
      description: description.trim() || null,
      price_cents: dollarsInputToCents(price),
      vendor_id: vendorId && vendorId !== 'none' ? parseInt(vendorId) : null,
    });
  };

  return (
    <div className="bg-muted/30 space-y-2 rounded-md border border-dashed p-3">
      <div className="grid gap-2 sm:grid-cols-[1fr_120px_160px]">
        <Input placeholder="Choice (e.g. Quartz — Calacatta)" value={label} onChange={(e) => setLabel(e.target.value)} autoFocus />
        <Input type="number" min="0" step="50" placeholder="Price $" value={price} onChange={(e) => setPrice(e.target.value)} />
        <Select value={vendorId} onValueChange={setVendorId}>
          <SelectTrigger>
            <SelectValue placeholder="Vendor" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">No vendor</SelectItem>
            {vendors.map((v) => (
              <SelectItem key={v.id} value={v.id.toString()}>
                {v.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <Input placeholder="Description (optional)" value={description} onChange={(e) => setDescription(e.target.value)} />
      <div className="flex gap-2">
        <Button size="sm" onClick={submit} disabled={!label.trim()}>
          {initial ? 'Save' : 'Add'}
        </Button>
        <Button size="sm" variant="ghost" onClick={onCancel} className="gap-1">
          <X className="h-3.5 w-3.5" />
          Cancel
        </Button>
      </div>
    </div>
  );
}

function ApproveDialog({
  state,
  onClose,
}: {
  state: { selection: ProjectSelection; choice: SelectionChoice } | null;
  onClose: () => void;
}) {
  const [comment, setComment] = useState('');
  const isUnapprove = state !== null && state.selection.approved_choice_id === state.choice.id;

  const submit = () => {
    if (!state) return;
    router.post(
      `/selections/${state.selection.id}/approve`,
      { choice_id: state.choice.id, comment: comment.trim() || null },
      {
        preserveScroll: true,
        onSuccess: () => {
          setComment('');
          onClose();
        },
        onError: () => toast.error('Could not update approval.'),
      },
    );
  };

  return (
    <Dialog open={state !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isUnapprove ? 'Remove approval?' : 'Approve choice'}</DialogTitle>
          <DialogDescription>
            {state &&
              (isUnapprove
                ? `"${state.choice.label}" will go back to pending for ${state.selection.item.label}.`
                : `You are approving "${state.choice.label}" for ${state.selection.item.label} on behalf of the customer.`)}
          </DialogDescription>
        </DialogHeader>

        {!isUnapprove && (
          <div className="space-y-2 py-2">
            <Label htmlFor="approval_comment">Comment (optional)</Label>
            <Textarea
              id="approval_comment"
              placeholder="e.g. Confirmed with customer by phone, Aug 12"
              rows={2}
              value={comment}
              onChange={(e) => setComment(e.target.value)}
            />
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button variant={isUnapprove ? 'destructive' : 'default'} onClick={submit}>
            {isUnapprove ? 'Remove approval' : 'Approve'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
