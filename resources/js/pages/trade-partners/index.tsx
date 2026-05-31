import { Pagination } from '@/components/buffalobuilt/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Paginated, type TradePartner } from '@/types/directory';
import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Mail, Pencil, Phone, Plus, Search, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Filters = {
    search: string;
    trade: string;
    location: string;
    used_only: boolean;
    per_page: number;
};

interface PageProps {
    partners: Paginated<TradePartner>;
    filters: Filters;
    tradeOptions: string[];
    locationOptions: string[];
    isAdmin: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trade Partners', href: '/trade-partners' },
];

const ALL = '__all__';

type PartnerForm = {
    name: string;
    location: string;
    phone: string;
    email: string;
    used_before: boolean;
    negotiated_price: string;
    referral_source: string;
    notes: string;
    do_not_use: boolean;
    trades: string[];
};

const emptyForm: PartnerForm = {
    name: '',
    location: '',
    phone: '',
    email: '',
    used_before: false,
    negotiated_price: '',
    referral_source: '',
    notes: '',
    do_not_use: false,
    trades: [],
};

export default function TradePartnersIndex({ partners, filters, tradeOptions, locationOptions, isAdmin }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TradePartner | null>(null);

    const form = useForm<PartnerForm>(emptyForm);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            route('trade-partners.index'),
            { ...filters, ...next },
            { only: ['partners', 'filters', 'locationOptions'], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (p: TradePartner) => {
        setEditing(p);
        form.setData({
            name: p.name,
            location: p.location ?? '',
            phone: p.phone ?? '',
            email: p.email ?? '',
            used_before: p.used_before,
            negotiated_price: p.negotiated_price ?? '',
            referral_source: p.referral_source ?? '',
            notes: p.notes ?? '',
            do_not_use: p.do_not_use,
            trades: p.trades,
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editing) {
            form.put(route('trade-partners.update', editing.id), opts);
        } else {
            form.post(route('trade-partners.store'), opts);
        }
    };

    const remove = (p: TradePartner) => {
        if (confirm(`Remove ${p.name}?`)) {
            router.delete(route('trade-partners.destroy', p.id), { preserveScroll: true });
        }
    };

    const toggleTrade = (trade: string) => {
        const has = form.data.trades.includes(trade);
        form.setData('trades', has ? form.data.trades.filter((t) => t !== trade) : [...form.data.trades, trade]);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trade Partners" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Trade Partners</h1>
                        <p className="text-muted-foreground text-sm">Subcontractors and crews by trade and location.</p>
                    </div>
                    {isAdmin && (
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add partner
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-end">
                        <div className="space-y-1.5 md:flex-1">
                            <Label htmlFor="search">Search</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    id="search"
                                    className="pl-8"
                                    placeholder="Name, phone, email, notes…"
                                    defaultValue={filters.search}
                                    onChange={(e) => applyFilters({ search: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 md:w-56">
                            <Label htmlFor="trade">Trade</Label>
                            <Select value={filters.trade || ALL} onValueChange={(v) => applyFilters({ trade: v === ALL ? '' : v })}>
                                <SelectTrigger id="trade">
                                    <SelectValue placeholder="All trades" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All trades</SelectItem>
                                    {tradeOptions.map((t) => (
                                        <SelectItem key={t} value={t}>
                                            {t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5 md:w-44">
                            <Label htmlFor="location">Location</Label>
                            <Select value={filters.location || ALL} onValueChange={(v) => applyFilters({ location: v === ALL ? '' : v })}>
                                <SelectTrigger id="location">
                                    <SelectValue placeholder="All locations" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All locations</SelectItem>
                                    {locationOptions.map((l) => (
                                        <SelectItem key={l} value={l}>
                                            {l}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <label className="flex h-10 items-center gap-2 text-sm">
                            <Checkbox checked={filters.used_only} onCheckedChange={(v) => applyFilters({ used_only: v === true })} />
                            Used before
                        </label>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Trades</th>
                                    <th className="px-6 py-3">Location</th>
                                    <th className="px-6 py-3">Contact</th>
                                    <th className="px-6 py-3">Negotiated</th>
                                    <th className="px-6 py-3">Notes</th>
                                    {isAdmin && <th className="px-6 py-3 text-right">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {partners.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={isAdmin ? 7 : 6} className="text-muted-foreground px-6 py-10 text-center">
                                            No trade partners match these filters.
                                        </td>
                                    </tr>
                                ) : (
                                    partners.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-muted/30">
                                            <td className="px-6 py-3">
                                                <div className="flex items-center gap-2 font-medium">
                                                    {p.name}
                                                    {p.do_not_use && (
                                                        <Badge variant="destructive" className="gap-1">
                                                            <Ban className="h-3 w-3" />
                                                            Do not use
                                                        </Badge>
                                                    )}
                                                    {p.used_before && !p.do_not_use && <Badge variant="secondary">Used</Badge>}
                                                </div>
                                                {p.referral_source && <div className="text-muted-foreground text-xs">via {p.referral_source}</div>}
                                            </td>
                                            <td className="px-6 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {p.trades.map((t) => (
                                                        <Badge key={t} variant="outline">
                                                            {t}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-6 py-3">{p.location ?? '—'}</td>
                                            <td className="px-6 py-3">
                                                <div className="flex flex-col gap-0.5">
                                                    {p.phone && (
                                                        <a href={`tel:${p.phone}`} className="hover:text-primary flex items-center gap-1.5">
                                                            <Phone className="h-3.5 w-3.5" />
                                                            {p.phone}
                                                        </a>
                                                    )}
                                                    {p.email && (
                                                        <a href={`mailto:${p.email}`} className="hover:text-primary flex items-center gap-1.5">
                                                            <Mail className="h-3.5 w-3.5" />
                                                            {p.email}
                                                        </a>
                                                    )}
                                                    {!p.phone && !p.email && <span className="text-muted-foreground">—</span>}
                                                </div>
                                            </td>
                                            <td className="px-6 py-3">{p.negotiated_price ?? '—'}</td>
                                            <td className="text-muted-foreground max-w-xs px-6 py-3">{p.notes ?? '—'}</td>
                                            {isAdmin && (
                                                <td className="px-6 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(p)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => remove(p)}>
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

                <Pagination paginator={partners} routeName="trade-partners.index" params={filters} propKey="partners" />
            </div>

            {/* Create / Edit dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit trade partner' : 'Add trade partner'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="f-name">Name</Label>
                                <Input id="f-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="f-location">Location</Label>
                                <Input id="f-location" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                                <InputError message={form.errors.location} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="f-price">Negotiated price</Label>
                                <Input
                                    id="f-price"
                                    value={form.data.negotiated_price}
                                    onChange={(e) => form.setData('negotiated_price', e.target.value)}
                                />
                                <InputError message={form.errors.negotiated_price} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="f-phone">Phone</Label>
                                <Input id="f-phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                                <InputError message={form.errors.phone} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="f-email">Email</Label>
                                <Input id="f-email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="f-referral">How do we know them?</Label>
                                <Input
                                    id="f-referral"
                                    value={form.data.referral_source}
                                    onChange={(e) => form.setData('referral_source', e.target.value)}
                                />
                                <InputError message={form.errors.referral_source} />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Trades</Label>
                            <div className="grid max-h-44 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-3">
                                {tradeOptions.map((t) => (
                                    <label key={t} className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={form.data.trades.includes(t)} onCheckedChange={() => toggleTrade(t)} />
                                        {t}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.trades} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="f-notes">Notes</Label>
                            <Textarea id="f-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="flex flex-wrap gap-6">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.used_before} onCheckedChange={(v) => form.setData('used_before', v === true)} />
                                Used before
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.do_not_use} onCheckedChange={(v) => form.setData('do_not_use', v === true)} />
                                Do not use
                            </label>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save changes' : 'Add partner'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
