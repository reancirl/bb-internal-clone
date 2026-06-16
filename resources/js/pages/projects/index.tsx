import { Pagination } from '@/components/buffalobuilt/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/directory';
import { type ProjectListItem, type ProjectStatus, STATUS_VARIANT } from '@/types/projects';
import { Head, Link, useForm } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    projects: Paginated<ProjectListItem>;
    filters: { per_page: number };
    statuses: ProjectStatus[];
    isAdmin: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects', href: '/projects' },
];

type ProjectForm = {
    name: string;
    client_name: string;
    address: string;
    status: ProjectStatus;
};

const emptyForm: ProjectForm = { name: '', client_name: '', address: '', status: 'lead' };

export default function ProjectsIndex({ projects, filters, statuses, isAdmin }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm<ProjectForm>(emptyForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('projects.store'), { onSuccess: () => setDialogOpen(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Projects</h1>
                        <p className="text-muted-foreground text-sm">Jobs with their material takeoffs and order status.</p>
                    </div>
                    {isAdmin && (
                        <Button
                            onClick={() => {
                                form.setData(emptyForm);
                                form.clearErrors();
                                setDialogOpen(true);
                            }}
                            className="gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            New project
                        </Button>
                    )}
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Project</th>
                                    <th className="px-6 py-3">Client</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3">Takeoff lines</th>
                                    <th className="px-6 py-3">Ordered</th>
                                    <th className="px-6 py-3">On site</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {projects.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground px-6 py-10 text-center">
                                            No projects yet.
                                        </td>
                                    </tr>
                                ) : (
                                    projects.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-muted/30">
                                            <td className="px-6 py-3 font-medium">
                                                <Link href={route('projects.show', p.id)} className="hover:text-primary hover:underline">
                                                    {p.name}
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground px-6 py-3">{p.client_name ?? '—'}</td>
                                            <td className="px-6 py-3">
                                                <Badge variant={STATUS_VARIANT[p.status]} className="capitalize">
                                                    {p.status}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-3 tabular-nums">{p.lines_count}</td>
                                            <td className="px-6 py-3 tabular-nums">
                                                {p.ordered_count}/{p.lines_count}
                                            </td>
                                            <td className="px-6 py-3 tabular-nums">
                                                {p.on_site_count}/{p.lines_count}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination paginator={projects} routeName="projects.index" params={filters} propKey="projects" />

                {projects.total === 0 && (
                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <FolderKanban className="h-3.5 w-3.5" />
                        Create a project to start a takeoff.
                    </p>
                )}
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New project</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="p-name">Name</Label>
                            <Input id="p-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="p-client">Client</Label>
                            <Input id="p-client" value={form.data.client_name} onChange={(e) => form.setData('client_name', e.target.value)} />
                            <InputError message={form.errors.client_name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="p-address">Address</Label>
                            <Input id="p-address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                            <InputError message={form.errors.address} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="p-status">Status</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v as ProjectStatus)}>
                                <SelectTrigger id="p-status" className="capitalize">
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
                            <InputError message={form.errors.status} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Create
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
