import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { evaluateFormula } from '@/lib/formula';
import { type BreadcrumbItem } from '@/types';
import { type VendorOption } from '@/types/pricing';
import {
    type DimensionField,
    formatMoney,
    formatQty,
    type PriceItemOption,
    type ProjectCostSummary,
    type ProjectDetail,
    type ProjectStatus,
    STATUS_VARIANT,
    type TakeoffLineRow,
} from '@/types/projects';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    ClipboardCheck,
    ClipboardList,
    Download,
    NotebookPen,
    PackageCheck,
    Pencil,
    Plus,
    Trash2,
    Wallet,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    project: ProjectDetail;
    dimensionFields: DimensionField[];
    lines: TakeoffLineRow[];
    summary: ProjectCostSummary;
    vendors: VendorOption[];
    priceItems: PriceItemOption[];
    statuses: ProjectStatus[];
    isAdmin: boolean;
}

const NONE = '__none__';

type LineForm = {
    price_item_id: string;
    category: string;
    item: string;
    formula: string;
    unit: string;
    waste_pct: string;
    supplier_id: string;
    notes: string;
};

const emptyLine: LineForm = { price_item_id: NONE, category: '', item: '', formula: '', unit: '', waste_pct: '0', supplier_id: NONE, notes: '' };

export default function ProjectShow({ project, dimensionFields, lines, summary, vendors, priceItems, statuses, isAdmin }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: route('projects.show', project.id) },
    ];

    // One form holds details + every dimension; both the details dialog and the
    // dimensions card write into it, and saving recomputes the takeoff.
    const projectForm = useForm<Record<string, string>>({
        name: project.name,
        client_name: project.client_name ?? '',
        address: project.address ?? '',
        status: project.status,
        ...Object.fromEntries(dimensionFields.map((f) => [f.key, f.value])),
    });

    const [detailsOpen, setDetailsOpen] = useState(false);
    const [lineOpen, setLineOpen] = useState(false);
    const [editingLine, setEditingLine] = useState<TakeoffLineRow | null>(null);
    const lineForm = useForm<LineForm>(emptyLine);
    const [confirm, confirmDialog] = useConfirm();

    const saveProject = (onDone?: () => void) => {
        projectForm.put(route('projects.update', project.id), {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
        });
    };

    const toggle = (line: TakeoffLineRow, field: 'ordered' | 'on_site', value: boolean) => {
        router.patch(route('takeoff-lines.toggle', line.id), { field, value }, { preserveScroll: true, preserveState: true, only: ['lines'] });
    };

    const openCreateLine = () => {
        setEditingLine(null);
        lineForm.setData(emptyLine);
        lineForm.clearErrors();
        setLineOpen(true);
    };
    const openEditLine = (l: TakeoffLineRow) => {
        setEditingLine(l);
        lineForm.setData({
            price_item_id: l.price_item_id ? String(l.price_item_id) : NONE,
            category: l.category ?? '',
            item: l.item,
            formula: l.formula ?? '',
            unit: l.unit ?? '',
            waste_pct: l.waste_pct,
            supplier_id: l.supplier_id ? String(l.supplier_id) : NONE,
            notes: l.notes ?? '',
        });
        lineForm.clearErrors();
        setLineOpen(true);
    };
    // Pre-fill the form from a chosen Price Book item, keeping Takeoff and Price Book linked.
    const pickPriceItem = (value: string) => {
        if (value === NONE) {
            lineForm.setData('price_item_id', NONE);
            return;
        }
        const item = priceItems.find((i) => String(i.id) === value);
        if (!item) return;
        lineForm.setData((prev) => ({
            ...prev,
            price_item_id: value,
            item: item.name,
            unit: item.unit ?? prev.unit,
            category: item.category ?? prev.category,
            supplier_id: item.supplier_id ? String(item.supplier_id) : prev.supplier_id,
        }));
    };

    const submitLine: FormEventHandler = (e) => {
        e.preventDefault();
        lineForm.transform((d) => ({
            ...d,
            price_item_id: d.price_item_id === NONE ? '' : d.price_item_id,
            supplier_id: d.supplier_id === NONE ? '' : d.supplier_id,
        }));
        const opts = { preserveScroll: true, onSuccess: () => setLineOpen(false) };
        if (editingLine) {
            lineForm.put(route('takeoff-lines.update', editingLine.id), opts);
        } else {
            lineForm.post(route('takeoff-lines.store', project.id), opts);
        }
    };
    const removeLine = async (l: TakeoffLineRow) => {
        const ok = await confirm({
            title: 'Remove line?',
            description: `"${l.item}" will be removed from this takeoff.`,
            confirmLabel: 'Remove',
            destructive: true,
        });
        if (ok) router.delete(route('takeoff-lines.destroy', l.id), { preserveScroll: true });
    };

    const deleteProject = async () => {
        const ok = await confirm({
            title: 'Delete project?',
            description: `"${project.name}" and its entire takeoff will be permanently deleted. This cannot be undone.`,
            confirmLabel: 'Delete project',
            destructive: true,
        });
        if (ok) router.delete(route('projects.destroy', project.id));
    };

    // Insert a measurement variable or operator into the formula (no typing codes).
    const insertToken = (token: string) => {
        const current = lineForm.data.formula;
        lineForm.setData('formula', current ? `${current} ${token}` : token);
    };

    // Live preview of the line quantity from the project's current dimensions.
    const dimValues: Record<string, number> = Object.fromEntries(dimensionFields.map((f) => [f.key, Number(projectForm.data[f.key]) || 0]));
    const previewFormula = lineForm.data.formula.trim();
    const previewBase = previewFormula ? evaluateFormula(previewFormula, dimValues) : null;
    const previewWaste = Number(lineForm.data.waste_pct) || 0;
    const previewQty = previewBase === null ? null : Math.round(previewBase * (1 + previewWaste / 100) * 100) / 100;

    const orderedCount = lines.filter((l) => l.ordered).length;
    const onSiteCount = lines.filter((l) => l.on_site).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-foreground text-2xl font-semibold">{project.name}</h1>
                            <Badge variant={STATUS_VARIANT[project.status]} className="capitalize">
                                {project.status}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {project.client_name ?? 'No client'}
                            {project.address ? ` · ${project.address}` : ''}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={`/projects/${project.id}/selections`} prefetch>
                                <ClipboardList className="h-4 w-4" />
                                Selections
                            </Link>
                        </Button>
                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={`/daily-logs?project=${project.id}`} prefetch>
                                <NotebookPen className="h-4 w-4" />
                                Logs
                            </Link>
                        </Button>
                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={`/projects/${project.id}/photos`} prefetch>
                                <Camera className="h-4 w-4" />
                                Photos
                            </Link>
                        </Button>
                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={`/projects/${project.id}/tasks`} prefetch>
                                <ClipboardCheck className="h-4 w-4" />
                                Tasks
                            </Link>
                        </Button>
                        {isAdmin && (
                            <Button variant="outline" className="gap-2" asChild>
                                <Link href={`/projects/${project.id}/budget`} prefetch>
                                    <Wallet className="h-4 w-4" />
                                    Budget
                                </Link>
                            </Button>
                        )}
                        {isAdmin && (
                            <Button variant="outline" className="gap-2" asChild>
                                <Link href={`/projects/${project.id}/purchase-orders`} prefetch>
                                    <PackageCheck className="h-4 w-4" />
                                    POs
                                </Link>
                            </Button>
                        )}
                    </div>
                    {isAdmin && (
                        <div className="flex gap-2">
                            <Button variant="outline" className="gap-2" onClick={() => setDetailsOpen(true)}>
                                <Pencil className="h-4 w-4" />
                                Edit details
                            </Button>
                            <Button variant="outline" className="text-destructive gap-2" onClick={deleteProject}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    )}
                </div>

                {/* Dimensions */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">Dimensions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            {dimensionFields.map((f) => (
                                <div key={f.key} className="space-y-1.5">
                                    <Label htmlFor={f.key} className="text-xs">
                                        {f.label}
                                    </Label>
                                    <Input
                                        id={f.key}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        disabled={!isAdmin}
                                        value={projectForm.data[f.key]}
                                        onChange={(e) => projectForm.setData(f.key, e.target.value)}
                                    />
                                </div>
                            ))}
                        </div>
                        {isAdmin && (
                            <div className="flex items-center gap-3">
                                <Button onClick={() => saveProject()} disabled={projectForm.processing}>
                                    Save dimensions &amp; recalculate
                                </Button>
                                <span className="text-muted-foreground text-xs">Quantities below recompute from these.</span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Takeoff lines */}
                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <CardTitle className="text-base font-semibold">Takeoff</CardTitle>
                        <div className="flex items-center gap-3">
                            <span className="text-muted-foreground text-xs tabular-nums">
                                Ordered {orderedCount}/{lines.length} · On site {onSiteCount}/{lines.length}
                            </span>
                            <Button asChild size="sm" variant="outline" className="gap-2">
                                <a href={route('projects.export', project.id)}>
                                    <Download className="h-4 w-4" />
                                    Export CSV
                                </a>
                            </Button>
                            {isAdmin && (
                                <Button size="sm" className="gap-2" onClick={openCreateLine}>
                                    <Plus className="h-4 w-4" />
                                    Add line
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[60rem] text-sm">
                                <thead className="bg-muted/50">
                                    <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                        <th className="px-4 py-3">Category</th>
                                        <th className="px-4 py-3">Item</th>
                                        <th className="px-4 py-3 text-right">Qty</th>
                                        <th className="px-4 py-3">Unit</th>
                                        <th className="px-4 py-3 text-right">Waste</th>
                                        <th className="px-4 py-3 text-right">Est. cost</th>
                                        <th className="px-4 py-3">Supplier</th>
                                        <th className="px-4 py-3 text-center">Ordered</th>
                                        <th className="px-4 py-3 text-center">On site</th>
                                        {isAdmin && <th className="px-4 py-3 text-right">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {lines.length === 0 ? (
                                        <tr>
                                            <td colSpan={isAdmin ? 10 : 9} className="text-muted-foreground px-4 py-10 text-center">
                                                No takeoff lines yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        lines.map((l) => (
                                            <tr key={l.id} className="group hover:bg-muted/30">
                                                <td className="px-4 py-3">{l.category ? <Badge variant="outline">{l.category}</Badge> : '—'}</td>
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">{l.item}</div>
                                                    {l.formula && <div className="text-muted-foreground font-mono text-xs">{l.formula}</div>}
                                                </td>
                                                <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                                    {l.formula_error ? (
                                                        <span className="text-destructive inline-flex items-center gap-1" title={l.formula_error}>
                                                            <AlertTriangle className="h-3.5 w-3.5" />
                                                            error
                                                        </span>
                                                    ) : (
                                                        formatQty(l.computed_qty)
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">{l.unit ?? '—'}</td>
                                                <td className="text-muted-foreground px-4 py-3 text-right tabular-nums">
                                                    {Number(l.waste_pct) > 0 ? `+${Number(l.waste_pct)}%` : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {l.line_cost !== null ? (
                                                        formatMoney(l.line_cost)
                                                    ) : (
                                                        <span className="text-muted-foreground" title="No price book item linked">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">{l.supplier_name ?? '—'}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <Checkbox checked={l.ordered} onCheckedChange={(v) => toggle(l, 'ordered', v === true)} />
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <Checkbox checked={l.on_site} onCheckedChange={(v) => toggle(l, 'on_site', v === true)} />
                                                </td>
                                                {isAdmin && (
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-end gap-1 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100">
                                                            <Button variant="ghost" size="icon" onClick={() => openEditLine(l)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" onClick={() => removeLine(l)}>
                                                                <Trash2 className="text-destructive h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Estimate roll-up */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">Estimate</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {summary.categories.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Link takeoff lines to Price Book items to see estimated costs.</p>
                        ) : (
                            <div className="max-w-md space-y-1.5">
                                {summary.categories.map((c) => (
                                    <div key={c.category} className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">{c.category}</span>
                                        <span className="tabular-nums">{formatMoney(c.total)}</span>
                                    </div>
                                ))}
                                <div className="border-border mt-2 flex items-center justify-between border-t pt-2 text-base font-semibold">
                                    <span>Total estimate</span>
                                    <span className="tabular-nums">{formatMoney(summary.grand_total)}</span>
                                </div>
                            </div>
                        )}
                        {summary.unpriced_count > 0 && (
                            <p className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                <AlertTriangle className="h-3.5 w-3.5" />
                                {summary.unpriced_count} line{summary.unpriced_count === 1 ? '' : 's'} with a quantity but no price — not included in
                                the total.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Edit details dialog */}
            <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit project details</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="d-name">Name</Label>
                            <Input id="d-name" value={projectForm.data.name} onChange={(e) => projectForm.setData('name', e.target.value)} />
                            <InputError message={projectForm.errors.name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="d-client">Client</Label>
                            <Input
                                id="d-client"
                                value={projectForm.data.client_name}
                                onChange={(e) => projectForm.setData('client_name', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="d-address">Address</Label>
                            <Input id="d-address" value={projectForm.data.address} onChange={(e) => projectForm.setData('address', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="d-status">Status</Label>
                            <Select value={projectForm.data.status} onValueChange={(v) => projectForm.setData('status', v)}>
                                <SelectTrigger id="d-status" className="capitalize">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem key={s} value={s} className="capitalize">
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDetailsOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={() => saveProject(() => setDetailsOpen(false))} disabled={projectForm.processing}>
                            Save changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Add / edit line dialog */}
            <Dialog open={lineOpen} onOpenChange={setLineOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-xl">
                    <DialogHeader className="shrink-0">
                        <DialogTitle>{editingLine ? 'Edit line' : 'Add line'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitLine} className="flex min-h-0 flex-1 flex-col gap-4">
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-1">
                            {priceItems.length > 0 && (
                                <div className="bg-muted/40 space-y-1.5 rounded-md p-3">
                                    <Label htmlFor="l-price-item">Pick from Price Book (optional)</Label>
                                    <Select value={lineForm.data.price_item_id} onValueChange={pickPriceItem}>
                                        <SelectTrigger id="l-price-item">
                                            <SelectValue placeholder="Choose an item to pre-fill…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>None / custom item</SelectItem>
                                            {priceItems.map((i) => (
                                                <SelectItem key={i.id} value={String(i.id)}>
                                                    {i.name}
                                                    {i.unit ? ` (${i.unit})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        Fills in the name, category, unit, and supplier. Add the count and waste below.
                                    </p>
                                </div>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="l-category">Category</Label>
                                    <Input
                                        id="l-category"
                                        value={lineForm.data.category}
                                        onChange={(e) => lineForm.setData('category', e.target.value)}
                                        placeholder="FRAMING"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="l-unit">Unit</Label>
                                    <Input
                                        id="l-unit"
                                        value={lineForm.data.unit}
                                        onChange={(e) => lineForm.setData('unit', e.target.value)}
                                        placeholder="EA"
                                    />
                                </div>
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="l-item">Item</Label>
                                    <Input id="l-item" value={lineForm.data.item} onChange={(e) => lineForm.setData('item', e.target.value)} />
                                    <InputError message={lineForm.errors.item} />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="l-formula">How to count it</Label>
                                    <p className="text-muted-foreground text-xs">
                                        Click a measurement and a math symbol to build the count — no typing codes. Example: Exterior wall length × 2
                                        ÷ 16.
                                    </p>
                                    <Input
                                        id="l-formula"
                                        className="font-mono"
                                        value={lineForm.data.formula}
                                        onChange={(e) => lineForm.setData('formula', e.target.value)}
                                        placeholder="Click measurements below…"
                                    />
                                    <InputError message={lineForm.errors.formula} />

                                    <div className="flex flex-wrap gap-1">
                                        {dimensionFields.map((f) => (
                                            <Button
                                                key={f.key}
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="h-7 text-xs font-normal"
                                                onClick={() => insertToken(f.key)}
                                            >
                                                {f.label.replace(/\s*\(.*\)$/, '')}
                                            </Button>
                                        ))}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1">
                                        {[
                                            { label: '+', token: '+' },
                                            { label: '−', token: '-' },
                                            { label: '×', token: '*' },
                                            { label: '÷', token: '/' },
                                            { label: '(', token: '(' },
                                            { label: ')', token: ')' },
                                        ].map((op) => (
                                            <Button
                                                key={op.token}
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="h-7 w-7 text-xs"
                                                onClick={() => insertToken(op.token)}
                                            >
                                                {op.label}
                                            </Button>
                                        ))}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 text-xs"
                                            onClick={() => lineForm.setData('formula', '')}
                                        >
                                            Clear
                                        </Button>
                                    </div>

                                    <div className="bg-muted/50 flex items-center justify-between rounded-md px-3 py-2 text-sm">
                                        <span className="text-muted-foreground">Preview for this project</span>
                                        {previewFormula === '' ? (
                                            <span className="text-muted-foreground">enter a count</span>
                                        ) : previewQty === null ? (
                                            <span className="text-destructive inline-flex items-center gap-1">
                                                <AlertTriangle className="h-3.5 w-3.5" />
                                                check the formula
                                            </span>
                                        ) : (
                                            <span className="font-semibold tabular-nums">
                                                = {formatQty(previewQty)} {lineForm.data.unit}
                                                {previewWaste > 0 && (
                                                    <span className="text-muted-foreground font-normal"> (incl. {previewWaste}% waste)</span>
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="l-waste">Waste %</Label>
                                    <Input
                                        id="l-waste"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={lineForm.data.waste_pct}
                                        onChange={(e) => lineForm.setData('waste_pct', e.target.value)}
                                    />
                                    <InputError message={lineForm.errors.waste_pct} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="l-supplier">Supplier</Label>
                                    <Select value={lineForm.data.supplier_id} onValueChange={(v) => lineForm.setData('supplier_id', v)}>
                                        <SelectTrigger id="l-supplier">
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>None</SelectItem>
                                            {vendors.map((v) => (
                                                <SelectItem key={v.id} value={String(v.id)}>
                                                    {v.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="l-notes">Notes</Label>
                                    <Textarea id="l-notes" value={lineForm.data.notes} onChange={(e) => lineForm.setData('notes', e.target.value)} />
                                </div>
                            </div>
                        </div>
                        <DialogFooter className="shrink-0">
                            <Button type="button" variant="outline" onClick={() => setLineOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={lineForm.processing}>
                                {editingLine ? 'Save changes' : 'Add line'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
