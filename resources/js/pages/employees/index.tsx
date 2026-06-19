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
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Employee, ROLE_LABEL, type Role } from '@/types/employees';
import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, RotateCcw, UserX } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    employees: Employee[];
    filters: { show_inactive: boolean };
    roles: Role[];
    currentUserId: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
];

type EmployeeForm = {
    name: string;
    email: string;
    role: Role;
    password: string;
};

const emptyForm: EmployeeForm = { name: '', email: '', role: 'crew', password: '' };

export default function EmployeesIndex({ employees, filters, roles, currentUserId }: PageProps) {
    const [editing, setEditing] = useState<Employee | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [pwTarget, setPwTarget] = useState<Employee | null>(null);
    const [confirm, confirmDialog] = useConfirm();

    const form = useForm<EmployeeForm>(emptyForm);
    const pwForm = useForm<{ password: string }>({ password: '' });

    const toggleInactive = (checked: boolean) => {
        router.get(route('admin.employees.index'), { show_inactive: checked ? 1 : 0 }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (e: Employee) => {
        setEditing(e);
        form.setData({ name: e.name, email: e.email, role: e.role, password: '' });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        if (editing) {
            form.put(route('admin.employees.update', editing.id), opts);
        } else {
            form.post(route('admin.employees.store'), opts);
        }
    };

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        if (!pwTarget) return;
        pwForm.patch(route('admin.employees.password', pwTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                pwForm.reset();
                setPwTarget(null);
            },
        });
    };

    const deactivate = async (e: Employee) => {
        const ok = await confirm({
            title: 'Deactivate employee?',
            description: `${e.name} will no longer be able to sign in. You can reactivate them later.`,
            confirmLabel: 'Deactivate',
            destructive: true,
        });
        if (ok) router.delete(route('admin.employees.destroy', e.id), { preserveScroll: true });
    };

    const reactivate = (e: Employee) => {
        router.patch(route('admin.employees.restore', e.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employees" />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Employees</h1>
                        <p className="text-muted-foreground text-sm">Manage who can sign in and what they can do.</p>
                    </div>
                    <Button onClick={openCreate} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Add employee
                    </Button>
                </div>

                <label className="text-muted-foreground flex w-fit items-center gap-2 text-sm">
                    <Checkbox checked={filters.show_inactive} onCheckedChange={(v) => toggleInactive(v === true)} />
                    Show deactivated
                </label>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Email</th>
                                    <th className="px-6 py-3">Role</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {employees.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-6 py-10 text-center">
                                            No employees yet.
                                        </td>
                                    </tr>
                                ) : (
                                    employees.map((e) => (
                                        <tr key={e.id} className={`hover:bg-muted/30 ${e.active ? '' : 'opacity-60'}`}>
                                            <td className="px-6 py-3 font-medium">
                                                {e.name}
                                                {e.id === currentUserId && <span className="text-muted-foreground font-normal"> (you)</span>}
                                            </td>
                                            <td className="text-muted-foreground px-6 py-3">{e.email}</td>
                                            <td className="px-6 py-3">
                                                <Badge variant={e.role === 'admin' ? 'default' : 'outline'}>{ROLE_LABEL[e.role]}</Badge>
                                            </td>
                                            <td className="px-6 py-3">
                                                {e.active ? (
                                                    <span className="text-green-600 dark:text-green-400">Active</span>
                                                ) : (
                                                    <span className="text-muted-foreground">Deactivated</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3">
                                                <div className="flex justify-end gap-1">
                                                    {e.active ? (
                                                        <>
                                                            <Button variant="ghost" size="icon" title="Edit" onClick={() => openEdit(e)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" title="Reset password" onClick={() => setPwTarget(e)}>
                                                                <KeyRound className="h-4 w-4" />
                                                            </Button>
                                                            {e.id !== currentUserId && (
                                                                <Button variant="ghost" size="icon" title="Deactivate" onClick={() => deactivate(e)}>
                                                                    <UserX className="text-destructive h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <Button variant="ghost" size="sm" className="gap-1.5" onClick={() => reactivate(e)}>
                                                            <RotateCcw className="h-3.5 w-3.5" />
                                                            Reactivate
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Add / edit dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit employee' : 'Add employee'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="e-name">Name</Label>
                            <Input id="e-name" value={form.data.name} onChange={(ev) => form.setData('name', ev.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="e-email">Email</Label>
                            <Input id="e-email" type="email" value={form.data.email} onChange={(ev) => form.setData('email', ev.target.value)} />
                            <InputError message={form.errors.email} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="e-role">Role</Label>
                            <Select value={form.data.role} onValueChange={(v) => form.setData('role', v as Role)}>
                                <SelectTrigger id="e-role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (
                                        <SelectItem key={r} value={r}>
                                            {ROLE_LABEL[r]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.role} />
                        </div>
                        {!editing && (
                            <div className="space-y-1.5">
                                <Label htmlFor="e-password">Temporary password</Label>
                                <Input
                                    id="e-password"
                                    type="text"
                                    value={form.data.password}
                                    onChange={(ev) => form.setData('password', ev.target.value)}
                                    placeholder="At least 8 characters"
                                />
                                <InputError message={form.errors.password} />
                            </div>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save' : 'Add'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Reset password dialog */}
            <Dialog open={pwTarget !== null} onOpenChange={(open) => !open && setPwTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset password{pwTarget ? ` — ${pwTarget.name}` : ''}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitPassword} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="e-newpw">New password</Label>
                            <Input
                                id="e-newpw"
                                type="text"
                                value={pwForm.data.password}
                                onChange={(ev) => pwForm.setData('password', ev.target.value)}
                                placeholder="At least 8 characters"
                            />
                            <InputError message={pwForm.errors.password} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setPwTarget(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={pwForm.processing}>
                                Reset password
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
