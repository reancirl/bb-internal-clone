import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type EquipmentRate, formatUsd, type LaborRate } from '@/types/pricing';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    laborRates: LaborRate[];
    equipmentRates: EquipmentRate[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Rates', href: '/admin/rates' },
];

type LaborForm = {
    class_name: string;
    base_rate: string;
    burden_rate: string;
    bill_rate: string;
    total: string;
    notes: string;
};

const emptyLabor: LaborForm = { class_name: '', base_rate: '', burden_rate: '', bill_rate: '', total: '', notes: '' };

type EquipmentForm = {
    name: string;
    day_rate: string;
    week_rate: string;
    month_rate: string;
    notes: string;
};

const emptyEquipment: EquipmentForm = { name: '', day_rate: '', week_rate: '', month_rate: '', notes: '' };

export default function RatesPage({ laborRates, equipmentRates }: PageProps) {
    const [laborOpen, setLaborOpen] = useState(false);
    const [equipOpen, setEquipOpen] = useState(false);
    const [editLabor, setEditLabor] = useState<LaborRate | null>(null);
    const [editEquip, setEditEquip] = useState<EquipmentRate | null>(null);

    const laborForm = useForm<LaborForm>(emptyLabor);
    const equipForm = useForm<EquipmentForm>(emptyEquipment);

    const openLaborCreate = () => {
        setEditLabor(null);
        laborForm.setData(emptyLabor);
        laborForm.clearErrors();
        setLaborOpen(true);
    };
    const openLaborEdit = (r: LaborRate) => {
        setEditLabor(r);
        laborForm.setData({
            class_name: r.class_name,
            base_rate: r.base_rate ?? '',
            burden_rate: r.burden_rate ?? '',
            bill_rate: r.bill_rate ?? '',
            total: r.total ?? '',
            notes: r.notes ?? '',
        });
        laborForm.clearErrors();
        setLaborOpen(true);
    };
    const submitLabor: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setLaborOpen(false) };
        if (editLabor) {
            laborForm.put(route('admin.labor-rates.update', editLabor.id), opts);
        } else {
            laborForm.post(route('admin.labor-rates.store'), opts);
        }
    };
    const removeLabor = (r: LaborRate) => {
        if (confirm(`Remove ${r.class_name}?`)) router.delete(route('admin.labor-rates.destroy', r.id), { preserveScroll: true });
    };

    const openEquipCreate = () => {
        setEditEquip(null);
        equipForm.setData(emptyEquipment);
        equipForm.clearErrors();
        setEquipOpen(true);
    };
    const openEquipEdit = (r: EquipmentRate) => {
        setEditEquip(r);
        equipForm.setData({
            name: r.name,
            day_rate: r.day_rate ?? '',
            week_rate: r.week_rate ?? '',
            month_rate: r.month_rate ?? '',
            notes: r.notes ?? '',
        });
        equipForm.clearErrors();
        setEquipOpen(true);
    };
    const submitEquip: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setEquipOpen(false) };
        if (editEquip) {
            equipForm.put(route('admin.equipment-rates.update', editEquip.id), opts);
        } else {
            equipForm.post(route('admin.equipment-rates.store'), opts);
        }
    };
    const removeEquip = (r: EquipmentRate) => {
        if (confirm(`Remove ${r.name}?`)) router.delete(route('admin.equipment-rates.destroy', r.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rates" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-semibold">Labor &amp; Equipment Rates</h1>
                    <p className="text-muted-foreground text-sm">Cost-sensitive reference rates used to build estimates.</p>
                </div>

                {/* Labor rates */}
                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <CardTitle className="text-base font-semibold">Labor classes</CardTitle>
                        <Button onClick={openLaborCreate} size="sm" className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add class
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead className="bg-muted/50">
                                    <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                        <th className="px-6 py-3">Class</th>
                                        <th className="px-6 py-3 text-right">Base</th>
                                        <th className="px-6 py-3 text-right">w/ Burden</th>
                                        <th className="px-6 py-3 text-right">Bill rate</th>
                                        <th className="px-6 py-3 text-right">Total</th>
                                        <th className="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {laborRates.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground px-6 py-8 text-center">
                                                No labor rates yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        laborRates.map((r) => (
                                            <tr key={r.id} className="hover:bg-muted/30">
                                                <td className="px-6 py-3 font-medium">{r.class_name}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.base_rate)}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.burden_rate)}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.bill_rate)}</td>
                                                <td className="px-6 py-3 text-right font-semibold tabular-nums">{formatUsd(r.total)}</td>
                                                <td className="px-6 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openLaborEdit(r)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => removeLabor(r)}>
                                                            <Trash2 className="text-destructive h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Equipment rates */}
                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <CardTitle className="text-base font-semibold">Equipment rental</CardTitle>
                        <Button onClick={openEquipCreate} size="sm" className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add equipment
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead className="bg-muted/50">
                                    <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                        <th className="px-6 py-3">Equipment</th>
                                        <th className="px-6 py-3 text-right">Day</th>
                                        <th className="px-6 py-3 text-right">Week</th>
                                        <th className="px-6 py-3 text-right">Month</th>
                                        <th className="px-6 py-3">Notes</th>
                                        <th className="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {equipmentRates.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground px-6 py-8 text-center">
                                                No equipment yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        equipmentRates.map((r) => (
                                            <tr key={r.id} className="hover:bg-muted/30">
                                                <td className="px-6 py-3 font-medium">{r.name}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.day_rate)}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.week_rate)}</td>
                                                <td className="px-6 py-3 text-right tabular-nums">{formatUsd(r.month_rate)}</td>
                                                <td className="text-muted-foreground max-w-xs px-6 py-3">{r.notes ?? '—'}</td>
                                                <td className="px-6 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEquipEdit(r)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => removeEquip(r)}>
                                                            <Trash2 className="text-destructive h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Labor dialog */}
            <Dialog open={laborOpen} onOpenChange={setLaborOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editLabor ? 'Edit labor class' : 'Add labor class'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitLabor} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="l-class">Class name</Label>
                            <Input id="l-class" value={laborForm.data.class_name} onChange={(e) => laborForm.setData('class_name', e.target.value)} />
                            <InputError message={laborForm.errors.class_name} />
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="l-base">Base rate</Label>
                                <Input
                                    id="l-base"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={laborForm.data.base_rate}
                                    onChange={(e) => laborForm.setData('base_rate', e.target.value)}
                                />
                                <InputError message={laborForm.errors.base_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="l-burden">w/ Burden</Label>
                                <Input
                                    id="l-burden"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={laborForm.data.burden_rate}
                                    onChange={(e) => laborForm.setData('burden_rate', e.target.value)}
                                />
                                <InputError message={laborForm.errors.burden_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="l-bill">Bill rate</Label>
                                <Input
                                    id="l-bill"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={laborForm.data.bill_rate}
                                    onChange={(e) => laborForm.setData('bill_rate', e.target.value)}
                                />
                                <InputError message={laborForm.errors.bill_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="l-total">Total</Label>
                                <Input
                                    id="l-total"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={laborForm.data.total}
                                    onChange={(e) => laborForm.setData('total', e.target.value)}
                                />
                                <InputError message={laborForm.errors.total} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="l-notes">Notes</Label>
                            <Textarea id="l-notes" value={laborForm.data.notes} onChange={(e) => laborForm.setData('notes', e.target.value)} />
                            <InputError message={laborForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setLaborOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={laborForm.processing}>
                                {editLabor ? 'Save changes' : 'Add class'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Equipment dialog */}
            <Dialog open={equipOpen} onOpenChange={setEquipOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editEquip ? 'Edit equipment' : 'Add equipment'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEquip} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="e-name">Name</Label>
                            <Input id="e-name" value={equipForm.data.name} onChange={(e) => equipForm.setData('name', e.target.value)} />
                            <InputError message={equipForm.errors.name} />
                        </div>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="e-day">Day</Label>
                                <Input
                                    id="e-day"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={equipForm.data.day_rate}
                                    onChange={(e) => equipForm.setData('day_rate', e.target.value)}
                                />
                                <InputError message={equipForm.errors.day_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-week">Week</Label>
                                <Input
                                    id="e-week"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={equipForm.data.week_rate}
                                    onChange={(e) => equipForm.setData('week_rate', e.target.value)}
                                />
                                <InputError message={equipForm.errors.week_rate} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-month">Month</Label>
                                <Input
                                    id="e-month"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={equipForm.data.month_rate}
                                    onChange={(e) => equipForm.setData('month_rate', e.target.value)}
                                />
                                <InputError message={equipForm.errors.month_rate} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="e-notes">Notes</Label>
                            <Textarea id="e-notes" value={equipForm.data.notes} onChange={(e) => equipForm.setData('notes', e.target.value)} />
                            <InputError message={equipForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEquipOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={equipForm.processing}>
                                {editEquip ? 'Save changes' : 'Add equipment'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
