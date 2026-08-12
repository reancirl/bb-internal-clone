import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
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
import { Archive, ArchiveRestore, Circle, Layers, Pencil, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

interface CatalogItem {
  id: number;
  label: string;
  recommended: string | null;
  guidance: string | null;
  sort_order: number;
  is_active: boolean;
}

interface CatalogCategory {
  id: number;
  name: string;
  scope: 'living' | 'garage' | 'shared';
  sort_order: number;
  notes: string | null;
  is_active: boolean;
  items: CatalogItem[];
}

const SCOPE_LABELS = { shared: 'Whole build', living: 'Living', garage: 'Garage' };
const SCOPE_ORDER: Array<'shared' | 'living' | 'garage'> = ['shared', 'living', 'garage'];

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Decision Catalog', href: '/admin/decision-catalog' },
];

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="text-destructive text-xs">{message}</p>;
}

export default function DecisionCatalog({ categories }: { categories: CatalogCategory[] }) {
  const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null);

  // Modal state — null means closed; 'new' opens create mode.
  const [categoryModal, setCategoryModal] = useState<'new' | CatalogCategory | null>(null);
  const [itemModal, setItemModal] = useState<{ category: CatalogCategory; item: CatalogItem | null } | null>(null);
  const [saving, setSaving] = useState(false);

  const activeCategory = categories.find((c) => c.id === activeCategoryId) ?? categories[0] ?? null;

  const requestOptions = (onDone: () => void) => ({
    preserveScroll: true,
    onStart: () => setSaving(true),
    onFinish: () => setSaving(false),
    onSuccess: onDone,
  });

  const saveCategory = (data: Record<string, string | number | null>) => {
    if (categoryModal === 'new') {
      router.post('/admin/decision-categories', data, requestOptions(() => setCategoryModal(null)));
    } else if (categoryModal) {
      router.put(`/admin/decision-categories/${categoryModal.id}`, data, requestOptions(() => setCategoryModal(null)));
    }
  };

  const saveItem = (data: Record<string, string | number | null>) => {
    if (!itemModal) return;
    if (itemModal.item) {
      router.put(`/admin/decision-items/${itemModal.item.id}`, data, requestOptions(() => setItemModal(null)));
    } else {
      router.post(
        `/admin/decision-categories/${itemModal.category.id}/items`,
        data,
        requestOptions(() => setItemModal(null)),
      );
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Decision Catalog" />

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-foreground text-2xl font-semibold">Decision Catalog</h1>
            <p className="text-muted-foreground text-sm">
              The selection template shared by all projects. Archived entries stay on existing projects
              but are skipped when generating new selections.
            </p>
          </div>
          <Button className="gap-2" onClick={() => setCategoryModal('new')}>
            <Plus className="h-4 w-4" />
            New category
          </Button>
        </div>

        {/* Master–detail: category nav left, one category right */}
        <div className="flex flex-col gap-4 lg:grid lg:grid-cols-[240px_minmax(0,1fr)] lg:items-start">
          {/* Category nav — sticky sidebar on desktop, horizontal scroller on mobile */}
          <nav className="flex gap-1 overflow-x-auto pb-1 lg:sticky lg:top-4 lg:flex-col lg:overflow-visible lg:pb-0">
            {SCOPE_ORDER.map((scope) => {
              const scoped = categories.filter((c) => c.scope === scope);
              if (scoped.length === 0) return null;
              return (
                <div key={scope} className="flex shrink-0 gap-1 lg:flex-col">
                  <div className="text-muted-foreground hidden px-2 pt-3 pb-1 text-xs font-semibold tracking-wide uppercase first:pt-0 lg:block">
                    {SCOPE_LABELS[scope]}
                  </div>
                  {scoped.map((c) => {
                    const isActive = activeCategory?.id === c.id;
                    return (
                      <button
                        key={c.id}
                        onClick={() => setActiveCategoryId(c.id)}
                        className={cn(
                          'flex shrink-0 items-center gap-2 rounded-md px-2.5 py-1.5 text-sm whitespace-nowrap transition-colors',
                          isActive
                            ? 'bg-accent text-accent-foreground font-medium'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                          !c.is_active && 'opacity-50',
                        )}
                      >
                        {c.is_active ? (
                          <Circle className="text-muted-foreground/50 h-3.5 w-3.5 shrink-0" />
                        ) : (
                          <Archive className="h-3.5 w-3.5 shrink-0" />
                        )}
                        <span className="min-w-0 flex-1 truncate text-left capitalize">{c.name.toLowerCase()}</span>
                        <span className="text-muted-foreground text-xs tabular-nums">{c.items.length}</span>
                      </button>
                    );
                  })}
                </div>
              );
            })}
          </nav>

          {/* Active category */}
          {activeCategory ? (
            <CategoryDetail
              category={activeCategory}
              onEdit={() => setCategoryModal(activeCategory)}
              onAddItem={() => setItemModal({ category: activeCategory, item: null })}
              onEditItem={(item) => setItemModal({ category: activeCategory, item })}
            />
          ) : (
            <div className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-center">
              <Layers className="text-muted-foreground/40 h-8 w-8" />
              <p className="text-muted-foreground text-sm">No categories yet — create the first one.</p>
            </div>
          )}
        </div>
      </div>

      <CategoryFormModal
        state={categoryModal}
        saving={saving}
        onClose={() => setCategoryModal(null)}
        onSave={saveCategory}
      />
      <ItemFormModal state={itemModal} saving={saving} onClose={() => setItemModal(null)} onSave={saveItem} />
    </AppLayout>
  );
}

function CategoryDetail({
  category,
  onEdit,
  onAddItem,
  onEditItem,
}: {
  category: CatalogCategory;
  onEdit: () => void;
  onAddItem: () => void;
  onEditItem: (item: CatalogItem) => void;
}) {
  const toggleActive = () => {
    router.put(
      `/admin/decision-categories/${category.id}`,
      { is_active: !category.is_active },
      { preserveScroll: true },
    );
  };

  return (
    <div className="min-w-0 space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <h2 className="text-foreground text-lg font-semibold capitalize">{category.name.toLowerCase()}</h2>
          <Badge variant="secondary">{SCOPE_LABELS[category.scope]}</Badge>
          {!category.is_active && <Badge variant="outline">Archived</Badge>}
          <span className="text-muted-foreground text-sm tabular-nums">
            {category.items.length} item{category.items.length === 1 ? '' : 's'}
          </span>
        </div>
        <div className="flex gap-1">
          <Button variant="ghost" size="sm" className="text-muted-foreground gap-1" onClick={onEdit}>
            <Pencil className="h-3.5 w-3.5" />
            Edit
          </Button>
          <Button variant="ghost" size="sm" className="text-muted-foreground gap-1" onClick={toggleActive}>
            {category.is_active ? <Archive className="h-3.5 w-3.5" /> : <ArchiveRestore className="h-3.5 w-3.5" />}
            {category.is_active ? 'Archive' : 'Restore'}
          </Button>
        </div>
      </div>
      {category.notes && <p className="text-muted-foreground text-sm">{category.notes}</p>}

      <Card>
        <CardContent className="space-y-1.5 p-3">
          {category.items.map((item) => (
            <div
              key={item.id}
              className={cn(
                'flex items-center gap-3 rounded-md border p-2',
                item.is_active ? 'bg-muted/30' : 'opacity-60',
              )}
            >
              <div className="min-w-0 flex-1 text-sm">
                <span className="font-medium">{item.label}</span>
                {item.recommended && <span className="text-muted-foreground"> — recommended: {item.recommended}</span>}
                {item.guidance && <span className="text-muted-foreground text-xs"> · {item.guidance}</span>}
              </div>
              {!item.is_active && <Badge variant="outline">Archived</Badge>}
              <Button
                variant="ghost"
                size="icon"
                className="text-muted-foreground h-7 w-7"
                onClick={() => onEditItem(item)}
              >
                <Pencil className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                className="text-muted-foreground h-7 w-7"
                title={item.is_active ? 'Archive' : 'Restore'}
                onClick={() =>
                  router.put(
                    `/admin/decision-items/${item.id}`,
                    { is_active: !item.is_active },
                    { preserveScroll: true },
                  )
                }
              >
                {item.is_active ? <Archive className="h-3.5 w-3.5" /> : <ArchiveRestore className="h-3.5 w-3.5" />}
              </Button>
            </div>
          ))}
          {category.items.length === 0 && (
            <p className="text-muted-foreground/70 py-2 text-sm">No items in this category yet.</p>
          )}

          <Button variant="outline" size="sm" className="mt-1 gap-1" onClick={onAddItem}>
            <Plus className="h-3.5 w-3.5" />
            Add item
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}

function CategoryFormModal({
  state,
  saving,
  onClose,
  onSave,
}: {
  state: 'new' | CatalogCategory | null;
  saving: boolean;
  onClose: () => void;
  onSave: (data: Record<string, string | number | null>) => void;
}) {
  const { errors } = usePage().props;
  const isEdit = state !== null && state !== 'new';
  const [name, setName] = useState('');
  const [scope, setScope] = useState('shared');
  const [notes, setNotes] = useState('');

  // Populate from the category being edited (or blank) each time it opens.
  useEffect(() => {
    if (state === 'new') {
      setName('');
      setScope('shared');
      setNotes('');
    } else if (state) {
      setName(state.name);
      setScope(state.scope);
      setNotes(state.notes ?? '');
    }
  }, [state]);

  const submit = () => {
    if (!name.trim()) return;
    onSave({ name: name.trim(), scope, notes: notes.trim() || null });
  };

  return (
    <Dialog open={state !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Category' : 'New Category'}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? 'Renaming applies everywhere, including existing project selections.'
              : 'Add a category to the selection template.'}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="category_name">Name *</Label>
            <Input
              id="category_name"
              placeholder="e.g. KITCHEN"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoFocus
            />
            <FieldError message={errors.name} />
          </div>

          <div className="space-y-2">
            <Label htmlFor="category_scope">Scope</Label>
            <Select value={scope} onValueChange={setScope}>
              <SelectTrigger id="category_scope">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="shared">Whole build</SelectItem>
                <SelectItem value="living">Living</SelectItem>
                <SelectItem value="garage">Garage</SelectItem>
              </SelectContent>
            </Select>
            <FieldError message={errors.scope} />
          </div>

          <div className="space-y-2">
            <Label htmlFor="category_notes">Notes</Label>
            <Textarea
              id="category_notes"
              placeholder="Shown under the category heading (optional)"
              rows={2}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={saving || !name.trim()}>
            {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Category'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ItemFormModal({
  state,
  saving,
  onClose,
  onSave,
}: {
  state: { category: CatalogCategory; item: CatalogItem | null } | null;
  saving: boolean;
  onClose: () => void;
  onSave: (data: Record<string, string | number | null>) => void;
}) {
  const { errors } = usePage().props;
  const isEdit = state?.item != null;
  const [label, setLabel] = useState('');
  const [recommended, setRecommended] = useState('');
  const [guidance, setGuidance] = useState('');

  useEffect(() => {
    setLabel(state?.item?.label ?? '');
    setRecommended(state?.item?.recommended ?? '');
    setGuidance(state?.item?.guidance ?? '');
  }, [state]);

  const submit = () => {
    if (!label.trim()) return;
    onSave({
      label: label.trim(),
      recommended: recommended.trim() || null,
      guidance: guidance.trim() || null,
    });
  };

  return (
    <Dialog open={state !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Item' : 'Add Item'}</DialogTitle>
          <DialogDescription>
            {state && (
              <>
                {isEdit ? 'Editing' : 'Adding'} an item in{' '}
                <span className="capitalize">{state.category.name.toLowerCase()}</span>.
              </>
            )}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="item_label">Item *</Label>
            <Input
              id="item_label"
              placeholder="e.g. Siding Material"
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              autoFocus
            />
            <FieldError message={errors.label} />
          </div>

          <div className="space-y-2">
            <Label htmlFor="item_recommended">Recommended</Label>
            <Input
              id="item_recommended"
              placeholder="e.g. EIFS"
              value={recommended}
              onChange={(e) => setRecommended(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="item_guidance">Guidance</Label>
            <Input
              id="item_guidance"
              placeholder="e.g. Color options in office"
              value={guidance}
              onChange={(e) => setGuidance(e.target.value)}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={saving || !label.trim()}>
            {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Item'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
