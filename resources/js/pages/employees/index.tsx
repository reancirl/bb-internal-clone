import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { type Employee, ROLE_LABEL, type Role } from '@/types/employees';
import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, RotateCcw, Search, Sparkles, UserX } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface PageProps {
    employees: Employee[];
    filters: { show_inactive: boolean };
    roles: Role[];
    currentUserId: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Crew & Accounts', href: '/admin/employees' },
];

type EmployeeForm = {
    name: string;
    email: string;
    role: Role;
    password: string;
};

const emptyForm: EmployeeForm = { name: '', email: '', role: 'crew', password: '' };

function generateTempPassword(): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$';
    let out = '';
    for (let i = 0; i < 12; i++) {
        out += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return out;
}

export default function EmployeesIndex({ employees, filters, roles, currentUserId }: PageProps) {
    const [editing, setEditing] = useState<Employee | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [pwTarget, setPwTarget] = useState<Employee | null>(null);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<'all' | Role>('all');
    const [confirm, confirmDialog] = useConfirm();

    const form = useForm<EmployeeForm>(emptyForm);
    const pwForm = useForm<{ password: string }>({ password: '' });

    const counts = useMemo(() => {
        const active = employees.filter((e) => e.active);
        const crew = active.filter((e) => e.role === 'crew');
        const admins = active.filter((e) => e.role === 'admin');
        const inactive = employees.filter((e) => !e.active);
        return {
            active: active.length,
            crew: crew.length,
            admins: admins.length,
            inactive: inactive.length,
        };
    }, [employees]);

    const filteredEmployees = useMemo(() => {
        return employees.filter((e) => {
            if (roleFilter !== 'all' && e.role !== roleFilter) return false;
            if (search.trim()) {
                const q = search.toLowerCase();
                return e.name.toLowerCase().includes(q) || e.email.toLowerCase().includes(q);
            }
            return true;
        });
    }, [employees, roleFilter, search]);

    const toggleInactive = (checked: boolean) => {
        router.get(
            route('admin.employees.index'),
            { show_inactive: checked ? 1 : 0 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
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
            title: 'Deactivate account?',
            description: `${e.name} will no longer be able to sign in on the web or mobile app. You can reactivate them later.`,
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
            <Head title="Crew & Accounts" />
            {confirmDialog}

            <SettingsLayout wide>
                <div className="space-y-6">
                    {/* Section Header */}
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <HeadingSmall
                            title="Crew & Account Management"
                            description="Create crew and admin accounts, update roles, reset passwords, or deactivate access."
                        />
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add Account
                        </Button>
                    </div>

                    {/* Account Summary Pills */}
                    <div className="grid grid-cols-3 gap-3">
                        <div className="bg-muted/30 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs">Active Accounts</div>
                            <div className="mt-1 text-xl font-semibold tabular-nums">{counts.active}</div>
                        </div>
                        <div className="bg-muted/30 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs">Crew Members</div>
                            <div className="mt-1 text-xl font-semibold tabular-nums">{counts.crew}</div>
                        </div>
                        <div className="bg-muted/30 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs">Administrators</div>
                            <div className="mt-1 text-xl font-semibold tabular-nums">{counts.admins}</div>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-1 flex-wrap items-center gap-3">
                            <div className="relative min-w-[200px] flex-1 max-w-xs">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search name or email..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={roleFilter} onValueChange={(v) => setRoleFilter(v as 'all' | Role)}>
                                <SelectTrigger className="w-[140px]">
                                    <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Roles</SelectItem>
                                    <SelectItem value="crew">Crew</SelectItem>
                                    <SelectItem value="admin">Admin</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <label className="text-muted-foreground flex w-fit cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={filters.show_inactive}
                                onCheckedChange={(v) => toggleInactive(v === true)}
                            />
                            Show deactivated
                        </label>
                    </div>

                    {/* Accounts Table */}
                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead className="bg-muted/50">
                                    <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                        <th className="px-4 py-3">Name</th>
                                        <th className="px-4 py-3">Email</th>
                                        <th className="px-4 py-3">Role</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {filteredEmployees.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="text-muted-foreground px-4 py-10 text-center"
                                            >
                                                No matching accounts found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredEmployees.map((e) => (
                                            <tr
                                                key={e.id}
                                                className={`hover:bg-muted/30 ${e.active ? '' : 'opacity-60'}`}
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {e.name}
                                                    {e.id === currentUserId && (
                                                        <span className="text-muted-foreground font-normal">
                                                            {' '}
                                                            (you)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">{e.email}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={e.role === 'admin' ? 'default' : 'outline'}>
                                                        {ROLE_LABEL[e.role]}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {e.active ? (
                                                        <span className="text-xs font-medium text-green-600 dark:text-green-400">
                                                            Active
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">
                                                            Deactivated
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        {e.active ? (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 gap-1.5 px-2.5 text-xs"
                                                                    onClick={() => openEdit(e)}
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                    Edit
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 gap-1.5 px-2.5 text-xs"
                                                                    onClick={() => {
                                                                        pwForm.reset();
                                                                        pwForm.clearErrors();
                                                                        setPwTarget(e);
                                                                    }}
                                                                >
                                                                    <KeyRound className="h-3.5 w-3.5" />
                                                                    Password
                                                                </Button>
                                                                {e.id !== currentUserId && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-destructive hover:text-destructive h-8 gap-1.5 px-2.5 text-xs"
                                                                        onClick={() => deactivate(e)}
                                                                    >
                                                                        <UserX className="h-3.5 w-3.5" />
                                                                        Deactivate
                                                                    </Button>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 gap-1.5 text-xs"
                                                                onClick={() => reactivate(e)}
                                                            >
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
            </SettingsLayout>

            {/* Add / edit dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Account' : 'Add Crew or Admin Account'}</DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this team member’s name, email address, or system role.'
                                : 'Create a login account for a crew member or administrator.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="e-name">Full Name</Label>
                            <Input
                                id="e-name"
                                placeholder="e.g. Wyatt Carter"
                                value={form.data.name}
                                onChange={(ev) => form.setData('name', ev.target.value)}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="e-email">Email Address</Label>
                            <Input
                                id="e-email"
                                type="email"
                                placeholder="name@buffalobuiltusa.com"
                                value={form.data.email}
                                onChange={(ev) => form.setData('email', ev.target.value)}
                            />
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
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="e-password">Temporary Password</Label>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 gap-1 px-2 text-xs"
                                        onClick={() => form.setData('password', generateTempPassword())}
                                    >
                                        <Sparkles className="h-3 w-3" />
                                        Generate
                                    </Button>
                                </div>
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
                                {editing ? 'Save Changes' : 'Create Account'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Reset password dialog */}
            <Dialog open={pwTarget !== null} onOpenChange={(open) => !open && setPwTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset Password{pwTarget ? ` — ${pwTarget.name}` : ''}</DialogTitle>
                        <DialogDescription>
                            Enter a new password for this account. They can change it anytime in their own Settings.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPassword} className="space-y-4">
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="e-newpw">New Password</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 gap-1 px-2 text-xs"
                                    onClick={() => pwForm.setData('password', generateTempPassword())}
                                >
                                    <Sparkles className="h-3 w-3" />
                                    Generate
                                </Button>
                            </div>
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
                                Reset Password
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
