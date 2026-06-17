import { Pagination } from '@/components/buffalobuilt/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/directory';
import { formatUsd, type PriceCategoryOption, type PriceItemRow, type VendorOption } from '@/types/pricing';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Filters = {
    search: string;
    category: number | '';
    per_page: number;
};

interface PageProps {
    items: Paginated<PriceItemRow>;
    filters: Filters;
    categories: PriceCategoryOption[];
    vendors: VendorOption[];
    unitOptions: string[];
    isAdmin: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Price Book', href: '/price-book' },
];

const ALL = '__all__';
const NONE = '__none__';

type ItemForm = {
    price_category_id: string;
    name: string;
    unit: string;
    fast_price: string;
    material_cost: string;
    bb_install_rate: string;
    sub_install_rate: string;
    preferred_vendor_id: string;
    notes: string;
};

const emptyForm: ItemForm = {
    price_category_id: '',
    name: '',
    unit: '',
    fast_price: '',
    material_cost: '',
    bb_install_rate: '',
    sub_install_rate: '',
    preferred_vendor_id: NONE,
    notes: '',
};

export default function PriceBookIndex({ items, filters, categories, vendors, unitOptions, isAdmin }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PriceItemRow | null>(null);

    const form = useForm<ItemForm>(emptyForm);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            route('price-book.index'),
            { ...filters, ...next },
            { only: ['items', 'filters'], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (i: PriceItemRow) => {
        setEditing(i);
        form.setData({
            price_category_id: String(i.price_category_id),
            name: i.name,
            unit: i.unit ?? '',
            fast_price: i.fast_price ?? '',
            material_cost: i.material_cost ?? '',
            bb_install_rate: i.bb_install_rate ?? '',
            sub_install_rate: i.sub_install_rate ?? '',
            preferred_vendor_id: i.preferred_vendor_id ? String(i.preferred_vendor_id) : NONE,
            notes: i.notes ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        // The NONE sentinel maps to no vendor; empty strings become null server-side.
        form.transform((data) => ({ ...data, preferred_vendor_id: data.preferred_vendor_id === NONE ? '' : data.preferred_vendor_id }));
        if (editing) {
            form.put(route('price-book.update', editing.id), opts);
        } else {
            form.post(route('price-book.store'), opts);
        }
    };

    const remove = (i: PriceItemRow) => {
        if (confirm(`Remove ${i.name}?`)) {
            router.delete(route('price-book.destroy', i.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Price Book" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Price Book</h1>
                        <p className="text-muted-foreground text-sm">Material and install costs by category. All prices are our cost.</p>
                    </div>
                    {isAdmin && (
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add item
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-end">
                        <div className="space-y-1.5 md:flex-1">
                            <Label htmlFor="search">Search</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    id="search"
                                    className="pl-8"
                                    placeholder="Item or notes…"
                                    defaultValue={filters.search}
                                    onChange={(e) => applyFilters({ search: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 md:w-64">
                            <Label htmlFor="category">Category</Label>
                            <Select
                                value={filters.category ? String(filters.category) : ALL}
                                onValueChange={(v) => applyFilters({ category: v === ALL ? '' : Number(v) })}
                            >
                                <SelectTrigger id="category">
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All categories</SelectItem>
                                    {categories.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.code} — {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[64rem] text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-4 py-3">Category</th>
                                    <th className="px-4 py-3">Item</th>
                                    <th className="px-4 py-3">Unit</th>
                                    <th className="px-4 py-3 text-right">Fast price</th>
                                    <th className="px-4 py-3 text-right">Material</th>
                                    <th className="px-4 py-3 text-right">BB install</th>
                                    <th className="px-4 py-3 text-right">Sub install</th>
                                    <th className="px-4 py-3">Vendor</th>
                                    <th className="px-4 py-3">Notes</th>
                                    {isAdmin && <th className="px-4 py-3 text-right">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={isAdmin ? 10 : 9} className="text-muted-foreground px-4 py-10 text-center">
                                            No items match these filters.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((i) => (
                                        <tr key={i.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">{i.category_code}</Badge>
                                            </td>
                                            <td className="px-4 py-3 font-medium">{i.name}</td>
                                            <td className="text-muted-foreground px-4 py-3">{i.unit ?? '—'}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">{formatUsd(i.fast_price)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">{formatUsd(i.material_cost)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">{formatUsd(i.bb_install_rate)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">{formatUsd(i.sub_install_rate)}</td>
                                            <td className="text-muted-foreground px-4 py-3">{i.vendor_name ?? '—'}</td>
                                            <td className="text-muted-foreground max-w-xs px-4 py-3">{i.notes ?? '—'}</td>
                                            {isAdmin && (
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(i)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => remove(i)}>
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
                </Card>

                <Pagination paginator={items} routeName="price-book.index" params={filters} propKey="items" />
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-2xl">
                    <DialogHeader className="shrink-0">
                        <DialogTitle>{editing ? 'Edit item' : 'Add item'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col gap-4">
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-1">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="i-category">Category</Label>
                                <Select value={form.data.price_category_id} onValueChange={(v) => form.setData('price_category_id', v)}>
                                    <SelectTrigger id="i-category">
                                        <SelectValue placeholder="Select…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.code} — {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.price_category_id} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="i-unit">Unit</Label>
                                <Select value={form.data.unit || NONE} onValueChange={(v) => form.setData('unit', v === NONE ? '' : v)}>
                                    <SelectTrigger id="i-unit">
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>—</SelectItem>
                                        {unitOptions.map((u) => (
                                            <SelectItem key={u} value={u}>
                                                {u}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.unit} />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="i-name">Item name</Label>
                                <Input id="i-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="i-fast">Fast price</Label>
                                <Input
                                    id="i-fast"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.fast_price}
                                    onChange={(e) => form.setData('fast_price', e.target.value)}
                                />
                                <InputError message={form.errors.fast_price} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="i-material">Material cost</Label>
                                <Input
                                    id="i-material"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.material_cost}
                                    onChange={(e) => form.setData('material_cost', e.target.value)}
                                />
                                <InputError message={form.errors.material_cost} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="i-bb">BB install rate</Label>
                                <Input
                                    id="i-bb"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.bb_install_rate}
                                    onChange={(e) => form.setData('bb_install_rate', e.target.value)}
                                />
                                <InputError message={form.errors.bb_install_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="i-sub">Sub install rate</Label>
                                <Input
                                    id="i-sub"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.sub_install_rate}
                                    onChange={(e) => form.setData('sub_install_rate', e.target.value)}
                                />
                                <InputError message={form.errors.sub_install_rate} />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="i-vendor">Preferred vendor</Label>
                                <Select value={form.data.preferred_vendor_id} onValueChange={(v) => form.setData('preferred_vendor_id', v)}>
                                    <SelectTrigger id="i-vendor">
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
                                <InputError message={form.errors.preferred_vendor_id} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="i-notes">Notes</Label>
                            <Textarea id="i-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            <InputError message={form.errors.notes} />
                        </div>
                        </div>
                        <DialogFooter className="shrink-0">
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save changes' : 'Add item'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
