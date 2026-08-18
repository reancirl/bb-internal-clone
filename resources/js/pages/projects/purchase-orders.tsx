import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCents } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Download, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type PoStatus = 'draft' | 'sent' | 'confirmed' | 'received' | 'canceled';

interface PoItem {
    id: number;
    takeoff_line_id: number | null;
    description: string;
    qty: string | null;
    unit: string | null;
    unit_price_cents: number | null;
    total_cents: number | null;
}

interface PoRow {
    id: number;
    number: string;
    vendor_id: number | null;
    vendor_name: string;
    status: PoStatus;
    total_cents: number;
    expected_delivery: string | null;
    notes: string | null;
    sent_at: string | null;
    received_at: string | null;
    created_at: string;
    created_by: string | null;
    allowed_transitions: PoStatus[];
    items: PoItem[];
}

interface TakeoffOption {
    id: number;
    category: string | null;
    item: string;
    unit: string | null;
    qty: number | null;
    unit_price_cents: number | null;
    supplier_id: number | null;
    ordered: boolean;
    on_site: boolean;
}

interface PageProps {
    project: { id: number; name: string; client_name: string | null };
    orders: PoRow[];
    takeoffOptions: TakeoffOption[];
    vendors: { id: number; name: string; type: string }[];
    committedCents: number;
    statuses: PoStatus[];
}

const STATUS_VARIANT: Record<PoStatus, 'outline' | 'default' | 'secondary' | 'destructive'> = {
    draft: 'outline',
    sent: 'default',
    confirmed: 'default',
    received: 'secondary',
    canceled: 'destructive',
};

const TRANSITION_LABEL: Record<PoStatus, string> = {
    draft: 'Back to draft',
    sent: 'Mark sent',
    confirmed: 'Mark confirmed',
    received: 'Mark received',
    canceled: 'Cancel PO',
};

type ItemDraft = {
    takeoff_line_id: number | null;
    description: string;
    qty: string;
    unit: string;
    unit_price: string; // dollars in the form; converted to cents on submit
};

type PoForm = {
    vendor_id: string;
    expected_delivery: string;
    notes: string;
    items: ItemDraft[];
};

export default function PurchaseOrders({ project, orders, takeoffOptions, vendors, committedCents }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Purchase Orders', href: `/projects/${project.id}/purchase-orders` },
    ];

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PoRow | null>(null);
    const [expanded, setExpanded] = useState<number | null>(null);
    const [confirm, confirmDialog] = useConfirm();

    const form = useForm<PoForm>({ vendor_id: '', expected_delivery: '', notes: '', items: [] });

    const centsToDollarString = (cents: number | null) => (cents === null ? '' : (cents / 100).toFixed(2));

    const openCreate = () => {
        setEditing(null);
        form.setData({ vendor_id: vendors[0] ? String(vendors[0].id) : '', expected_delivery: '', notes: '', items: [] });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (po: PoRow) => {
        setEditing(po);
        form.setData({
            vendor_id: po.vendor_id ? String(po.vendor_id) : '',
            expected_delivery: po.expected_delivery ?? '',
            notes: po.notes ?? '',
            items: po.items.map((i) => ({
                takeoff_line_id: i.takeoff_line_id,
                description: i.description,
                qty: i.qty ?? '',
                unit: i.unit ?? '',
                unit_price: centsToDollarString(i.unit_price_cents),
            })),
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const toggleTakeoffLine = (option: TakeoffOption, checked: boolean) => {
        if (checked) {
            form.setData('items', [
                ...form.data.items,
                {
                    takeoff_line_id: option.id,
                    description: option.item,
                    qty: option.qty !== null ? String(option.qty) : '',
                    unit: option.unit ?? '',
                    unit_price: centsToDollarString(option.unit_price_cents),
                },
            ]);
        } else {
            form.setData(
                'items',
                form.data.items.filter((i) => i.takeoff_line_id !== option.id),
            );
        }
    };

    const addCustomItem = () => {
        form.setData('items', [...form.data.items, { takeoff_line_id: null, description: '', qty: '', unit: '', unit_price: '' }]);
    };

    const updateItem = (index: number, patch: Partial<ItemDraft>) => {
        form.setData(
            'items',
            form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );
    };

    const removeItem = (index: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };

    const draftTotalCents = form.data.items.reduce((sum, item) => {
        const qty = parseFloat(item.qty);
        const price = parseFloat(item.unit_price);
        return sum + (isNaN(qty) || isNaN(price) ? 0 : Math.round(qty * price * 100));
    }, 0);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            vendor_id: Number(data.vendor_id),
            expected_delivery: data.expected_delivery || null,
            notes: data.notes || null,
            items: data.items.map((item) => {
                const qty = parseFloat(item.qty);
                const price = parseFloat(item.unit_price);
                return {
                    takeoff_line_id: item.takeoff_line_id,
                    description: item.description,
                    qty: isNaN(qty) ? null : qty,
                    unit: item.unit || null,
                    unit_price_cents: isNaN(price) ? null : Math.round(price * 100),
                };
            }),
        }));
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editing) {
            form.put(route('purchase-orders.update', editing.id), opts);
        } else {
            form.post(route('purchase-orders.store', project.id), opts);
        }
    };

    const transition = async (po: PoRow, status: PoStatus) => {
        const destructive = status === 'canceled';
        const ok = await confirm({
            title: `${TRANSITION_LABEL[status]}?`,
            description:
                status === 'sent'
                    ? `${po.number} will be marked sent and its linked takeoff lines flagged as ordered. Committed cost increases by ${formatCents(po.total_cents)}.`
                    : status === 'received'
                      ? `${po.number} will be marked received and its linked takeoff lines flagged as on site.`
                      : `${po.number} will be marked ${status}.`,
            confirmLabel: TRANSITION_LABEL[status],
            destructive,
        });
        if (ok) {
            router.post(route('purchase-orders.transition', po.id), { status }, { preserveScroll: true });
        }
    };

    const removePo = async (po: PoRow) => {
        const ok = await confirm({
            title: 'Delete draft PO?',
            description: `${po.number} and its items will be deleted. Only drafts can be deleted.`,
            confirmLabel: 'Delete',
            destructive: true,
        });
        if (ok) router.delete(route('purchase-orders.destroy', po.id), { preserveScroll: true });
    };

    const selectedLineIds = new Set(form.data.items.map((i) => i.takeoff_line_id).filter(Boolean));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Purchase Orders — ${project.name}`} />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Purchase Orders</h1>
                        <p className="text-muted-foreground text-sm">
                            {project.name}
                            {project.client_name ? ` · ${project.client_name}` : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="text-right">
                            <p className="text-muted-foreground text-xs tracking-wider uppercase">Committed</p>
                            <p className="text-lg font-semibold tabular-nums">{formatCents(committedCents)}</p>
                        </div>
                        <Button className="gap-2" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            New PO
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    {orders.length === 0 ? (
                        <p className="text-muted-foreground p-8 text-center text-sm">
                            No purchase orders yet. Create one to start tracking committed costs.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left text-xs tracking-wider uppercase">
                                        <th className="w-8 px-2 py-2.5" />
                                        <th className="px-3 py-2.5">Number</th>
                                        <th className="px-3 py-2.5">Supplier</th>
                                        <th className="px-3 py-2.5">Status</th>
                                        <th className="px-3 py-2.5 text-right">Total</th>
                                        <th className="px-3 py-2.5">Expected</th>
                                        <th className="px-3 py-2.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((po) => (
                                        <>
                                            <tr key={po.id} className="hover:bg-muted/30 border-b">
                                                <td className="px-2 py-2.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => setExpanded(expanded === po.id ? null : po.id)}
                                                        aria-label="Toggle items"
                                                    >
                                                        {expanded === po.id ? (
                                                            <ChevronDown className="text-muted-foreground h-4 w-4" />
                                                        ) : (
                                                            <ChevronRight className="text-muted-foreground h-4 w-4" />
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="px-3 py-2.5 font-medium whitespace-nowrap">{po.number}</td>
                                                <td className="px-3 py-2.5">{po.vendor_name}</td>
                                                <td className="px-3 py-2.5">
                                                    <Badge variant={STATUS_VARIANT[po.status]}>{po.status}</Badge>
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-semibold tabular-nums">{formatCents(po.total_cents)}</td>
                                                <td className="text-muted-foreground px-3 py-2.5 whitespace-nowrap">{po.expected_delivery ?? '—'}</td>
                                                <td className="px-3 py-2.5">
                                                    <div className="flex justify-end gap-1.5">
                                                        {po.allowed_transitions.map((s) => (
                                                            <Button
                                                                key={s}
                                                                size="sm"
                                                                variant={s === 'canceled' ? 'outline' : 'secondary'}
                                                                className={s === 'canceled' ? 'text-destructive' : ''}
                                                                onClick={() => transition(po, s)}
                                                            >
                                                                {TRANSITION_LABEL[s]}
                                                            </Button>
                                                        ))}
                                                        <Button size="sm" variant="outline" asChild>
                                                            <a href={route('purchase-orders.pdf', po.id)}>
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                        {po.status === 'draft' && (
                                                            <>
                                                                <Button size="sm" variant="outline" onClick={() => openEdit(po)}>
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="text-destructive"
                                                                    onClick={() => removePo(po)}
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                            {expanded === po.id && (
                                                <tr key={`${po.id}-items`} className="border-b">
                                                    <td />
                                                    <td colSpan={6} className="px-3 py-2">
                                                        <table className="w-full text-xs">
                                                            <thead>
                                                                <tr className="text-muted-foreground text-left uppercase">
                                                                    <th className="py-1 pr-3">Item</th>
                                                                    <th className="py-1 pr-3 text-right">Qty</th>
                                                                    <th className="py-1 pr-3">Unit</th>
                                                                    <th className="py-1 pr-3 text-right">Unit price</th>
                                                                    <th className="py-1 text-right">Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {po.items.map((item) => (
                                                                    <tr key={item.id}>
                                                                        <td className="py-1 pr-3">{item.description}</td>
                                                                        <td className="py-1 pr-3 text-right tabular-nums">{item.qty ?? '—'}</td>
                                                                        <td className="py-1 pr-3">{item.unit ?? '—'}</td>
                                                                        <td className="py-1 pr-3 text-right tabular-nums">
                                                                            {item.unit_price_cents !== null
                                                                                ? formatCents(item.unit_price_cents)
                                                                                : '—'}
                                                                        </td>
                                                                        <td className="py-1 text-right tabular-nums">
                                                                            {item.total_cents !== null ? formatCents(item.total_cents) : 'TBD'}
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                        {po.notes && <p className="text-muted-foreground mt-2 text-xs">{po.notes}</p>}
                                                    </td>
                                                </tr>
                                            )}
                                        </>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Edit ${editing.number}` : 'New purchase order'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="po-vendor">Supplier</Label>
                                <Select value={form.data.vendor_id} onValueChange={(v) => form.setData('vendor_id', v)}>
                                    <SelectTrigger id="po-vendor">
                                        <SelectValue placeholder="Choose…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {vendors.map((v) => (
                                            <SelectItem key={v.id} value={String(v.id)}>
                                                {v.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.vendor_id} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="po-delivery">Expected delivery</Label>
                                <Input
                                    id="po-delivery"
                                    type="date"
                                    value={form.data.expected_delivery}
                                    onChange={(e) => form.setData('expected_delivery', e.target.value)}
                                />
                                <InputError message={form.errors.expected_delivery} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Pull from takeoff</Label>
                            <div className="max-h-44 space-y-1 overflow-y-auto rounded-md border p-3">
                                {takeoffOptions.length === 0 && <p className="text-muted-foreground text-xs">This project has no takeoff lines.</p>}
                                {takeoffOptions.map((option) => (
                                    <label key={option.id} className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={selectedLineIds.has(option.id)}
                                            onCheckedChange={(v) => toggleTakeoffLine(option, v === true)}
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            {option.item}
                                            {option.category && <span className="text-muted-foreground"> · {option.category}</span>}
                                        </span>
                                        <span className="text-muted-foreground text-xs tabular-nums">
                                            {option.qty !== null ? `${option.qty} ${option.unit ?? ''}` : 'qty tbd'}
                                            {option.ordered && ' · ordered'}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {form.data.items.length > 0 && (
                            <div className="space-y-2">
                                <Label>Items</Label>
                                <div className="space-y-2">
                                    {form.data.items.map((item, index) => (
                                        <div key={index} className="grid grid-cols-[1fr_5rem_4rem_6rem_2rem] items-center gap-2">
                                            <Input
                                                value={item.description}
                                                placeholder="Description"
                                                onChange={(e) => updateItem(index, { description: e.target.value })}
                                            />
                                            <Input
                                                value={item.qty}
                                                placeholder="Qty"
                                                inputMode="decimal"
                                                onChange={(e) => updateItem(index, { qty: e.target.value })}
                                            />
                                            <Input
                                                value={item.unit}
                                                placeholder="Unit"
                                                onChange={(e) => updateItem(index, { unit: e.target.value })}
                                            />
                                            <Input
                                                value={item.unit_price}
                                                placeholder="$ / unit"
                                                inputMode="decimal"
                                                onChange={(e) => updateItem(index, { unit_price: e.target.value })}
                                            />
                                            <button
                                                type="button"
                                                className="text-muted-foreground hover:text-destructive"
                                                onClick={() => removeItem(index)}
                                                aria-label="Remove item"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        <div className="flex items-center justify-between">
                            <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={addCustomItem}>
                                <Plus className="h-3.5 w-3.5" />
                                Custom item
                            </Button>
                            <p className="text-sm font-semibold tabular-nums">Total: {formatCents(draftTotalCents)}</p>
                        </div>
                        <InputError message={form.errors.items} />

                        <div className="space-y-1.5">
                            <Label htmlFor="po-notes">Notes</Label>
                            <Textarea id="po-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing || form.data.items.length === 0}>
                                {editing ? 'Save draft' : 'Create draft'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
