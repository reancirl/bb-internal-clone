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
import { type Paginated, type Vendor, type VendorType } from '@/types/directory';
import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, Mail, Pencil, Phone, Plus, Search, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Filters = {
    search: string;
    type: string;
    per_page: number;
};

interface PageProps {
    vendors: Paginated<Vendor>;
    filters: Filters;
    typeOptions: VendorType[];
    isAdmin: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/vendors' },
];

const ALL = '__all__';

type VendorForm = {
    name: string;
    type: VendorType;
    location: string;
    phone: string;
    email: string;
    url: string;
    notes: string;
};

const emptyForm: VendorForm = {
    name: '',
    type: 'store',
    location: '',
    phone: '',
    email: '',
    url: '',
    notes: '',
};

export default function VendorsIndex({ vendors, filters, typeOptions, isAdmin }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Vendor | null>(null);

    const form = useForm<VendorForm>(emptyForm);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            route('vendors.index'),
            { ...filters, ...next },
            { only: ['vendors', 'filters'], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (v: Vendor) => {
        setEditing(v);
        form.setData({
            name: v.name,
            type: v.type,
            location: v.location ?? '',
            phone: v.phone ?? '',
            email: v.email ?? '',
            url: v.url ?? '',
            notes: v.notes ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editing) {
            form.put(route('vendors.update', editing.id), opts);
        } else {
            form.post(route('vendors.store'), opts);
        }
    };

    const remove = (v: Vendor) => {
        if (confirm(`Remove ${v.name}?`)) {
            router.delete(route('vendors.destroy', v.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendors" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Vendors &amp; Suppliers</h1>
                        <p className="text-muted-foreground text-sm">Preferred stores and material suppliers.</p>
                    </div>
                    {isAdmin && (
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add vendor
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
                                    placeholder="Name, location, notes…"
                                    defaultValue={filters.search}
                                    onChange={(e) => applyFilters({ search: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 md:w-48">
                            <Label htmlFor="type">Type</Label>
                            <Select value={filters.type || ALL} onValueChange={(v) => applyFilters({ type: v === ALL ? '' : v })}>
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All types</SelectItem>
                                    {typeOptions.map((t) => (
                                        <SelectItem key={t} value={t} className="capitalize">
                                            {t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Type</th>
                                    <th className="px-6 py-3">Location</th>
                                    <th className="px-6 py-3">Contact</th>
                                    <th className="px-6 py-3">Notes</th>
                                    {isAdmin && <th className="px-6 py-3 text-right">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {vendors.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={isAdmin ? 6 : 5} className="text-muted-foreground px-6 py-10 text-center">
                                            No vendors match these filters.
                                        </td>
                                    </tr>
                                ) : (
                                    vendors.data.map((v) => (
                                        <tr key={v.id} className="hover:bg-muted/30">
                                            <td className="px-6 py-3 font-medium">{v.name}</td>
                                            <td className="px-6 py-3">
                                                <Badge variant={v.type === 'store' ? 'secondary' : 'outline'} className="capitalize">
                                                    {v.type}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-3">{v.location ?? '—'}</td>
                                            <td className="px-6 py-3">
                                                <div className="flex flex-col gap-0.5">
                                                    {v.phone && (
                                                        <a href={`tel:${v.phone}`} className="hover:text-primary flex items-center gap-1.5">
                                                            <Phone className="h-3.5 w-3.5" />
                                                            {v.phone}
                                                        </a>
                                                    )}
                                                    {v.email && (
                                                        <a href={`mailto:${v.email}`} className="hover:text-primary flex items-center gap-1.5">
                                                            <Mail className="h-3.5 w-3.5" />
                                                            {v.email}
                                                        </a>
                                                    )}
                                                    {v.url && (
                                                        <a
                                                            href={v.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="hover:text-primary flex items-center gap-1.5"
                                                        >
                                                            <ExternalLink className="h-3.5 w-3.5" />
                                                            Website
                                                        </a>
                                                    )}
                                                    {!v.phone && !v.email && !v.url && <span className="text-muted-foreground">—</span>}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground max-w-xs px-6 py-3">{v.notes ?? '—'}</td>
                                            {isAdmin && (
                                                <td className="px-6 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(v)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => remove(v)}>
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

                <Pagination paginator={vendors} routeName="vendors.index" params={filters} propKey="vendors" />
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit vendor' : 'Add vendor'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="v-name">Name</Label>
                                <Input id="v-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="v-type">Type</Label>
                                <Select value={form.data.type} onValueChange={(v) => form.setData('type', v as VendorType)}>
                                    <SelectTrigger id="v-type" className="capitalize">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {typeOptions.map((t) => (
                                            <SelectItem key={t} value={t} className="capitalize">
                                                {t}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.type} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="v-location">Location</Label>
                                <Input id="v-location" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                                <InputError message={form.errors.location} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="v-phone">Phone</Label>
                                <Input id="v-phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                                <InputError message={form.errors.phone} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="v-email">Email</Label>
                                <Input id="v-email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="v-url">Website</Label>
                                <Input
                                    id="v-url"
                                    value={form.data.url}
                                    onChange={(e) => form.setData('url', e.target.value)}
                                    placeholder="https://"
                                />
                                <InputError message={form.errors.url} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="v-notes">Notes</Label>
                            <Textarea id="v-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            <InputError message={form.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save changes' : 'Add vendor'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
