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
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Camera, CheckCircle2, ClipboardCheck, MapPin, Pencil, Plus, Trash2, X } from 'lucide-react';
import { ChangeEventHandler, FormEventHandler, useMemo, useRef, useState } from 'react';

type TaskStatus = 'open' | 'in_progress' | 'done';

interface ChecklistItem {
    id: number;
    label: string;
    done: boolean;
}

interface TaskPhoto {
    id: number;
    stage: 'before' | 'after';
    thumb_url: string;
    full_url: string;
}

interface TaskRow {
    id: number;
    number: number;
    title: string;
    description: string | null;
    location: string | null;
    category: string | null;
    priority: 'low' | 'medium' | 'high';
    is_punch: boolean;
    assigned_to_user_id: number | null;
    assignee: string | null;
    due_date: string | null;
    status: TaskStatus;
    completed_at: string | null;
    completed_by: string | null;
    created_by: string | null;
    can_edit: boolean;
    can_work: boolean;
    checklist: ChecklistItem[];
    photos: TaskPhoto[];
}

interface PageProps {
    project: { id: number; name: string; client_name: string | null };
    punchSignedOffAt: string | null;
    punchReady: boolean;
    tasks: TaskRow[];
    users: { id: number; name: string; role: string }[];
    statuses: TaskStatus[];
    priorities: string[];
    categories: string[];
    isAdmin: boolean;
}

const STATUS_LABEL: Record<TaskStatus, string> = { open: 'Open', in_progress: 'In progress', done: 'Done' };

const PRIORITY_VARIANT: Record<string, 'outline' | 'default' | 'destructive'> = {
    low: 'outline',
    medium: 'default',
    high: 'destructive',
};

type ChecklistDraft = { label: string; done: boolean };

type TaskForm = {
    title: string;
    description: string;
    location: string;
    category: string;
    priority: string;
    is_punch: boolean;
    assigned_to_user_id: string; // 'none' | id
    due_date: string;
    checklist: ChecklistDraft[];
};

const emptyForm: TaskForm = {
    title: '',
    description: '',
    location: '',
    category: '',
    priority: 'medium',
    is_punch: false,
    assigned_to_user_id: 'none',
    due_date: '',
    checklist: [],
};

export default function ProjectTasks({ project, punchSignedOffAt, punchReady, tasks, users, statuses, priorities, categories, isAdmin }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Tasks', href: `/projects/${project.id}/tasks` },
    ];

    const [view, setView] = useState<'all' | 'punch'>('all');
    const [assigneeFilter, setAssigneeFilter] = useState('all');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TaskRow | null>(null);
    const [photoTask, setPhotoTask] = useState<{ id: number; stage: 'before' | 'after' } | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const [confirm, confirmDialog] = useConfirm();
    const form = useForm<TaskForm>(emptyForm);

    const filtered = useMemo(
        () => tasks.filter((t) => (view === 'all' || t.is_punch) && (assigneeFilter === 'all' || String(t.assigned_to_user_id) === assigneeFilter)),
        [tasks, view, assigneeFilter],
    );

    const groups = (['open', 'in_progress', 'done'] as TaskStatus[]).map((status) => ({
        status,
        items: filtered.filter((t) => t.status === status),
    }));

    const punchItems = tasks.filter((t) => t.is_punch);
    const punchDone = punchItems.filter((t) => t.status === 'done').length;

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm, is_punch: view === 'punch' });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (task: TaskRow) => {
        setEditing(task);
        form.setData({
            title: task.title,
            description: task.description ?? '',
            location: task.location ?? '',
            category: task.category ?? '',
            priority: task.priority,
            is_punch: task.is_punch,
            assigned_to_user_id: task.assigned_to_user_id ? String(task.assigned_to_user_id) : 'none',
            due_date: task.due_date ?? '',
            checklist: task.checklist.map((i) => ({ label: i.label, done: i.done })),
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            description: data.description || null,
            location: data.location || null,
            category: data.category || null,
            assigned_to_user_id: data.assigned_to_user_id === 'none' ? null : Number(data.assigned_to_user_id),
            due_date: data.due_date || null,
            checklist: data.checklist.filter((i) => i.label.trim() !== ''),
        }));
        const opts = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editing) {
            form.put(route('tasks.update', editing.id), opts);
        } else {
            form.post(route('tasks.store', project.id), opts);
        }
    };

    const setStatus = (task: TaskRow, status: string) => {
        router.patch(route('tasks.status', task.id), { status }, { preserveScroll: true });
    };

    const toggleItem = (task: TaskRow, item: ChecklistItem) => {
        if (!task.can_work) return;
        router.patch(route('tasks.checklist.toggle', [task.id, item.id]), {}, { preserveScroll: true });
    };

    const removeTask = async (task: TaskRow) => {
        const ok = await confirm({
            title: `Remove task #${task.number}?`,
            description: 'The task, its checklist, and its photos will be deleted.',
            confirmLabel: 'Remove',
            destructive: true,
        });
        if (ok) router.delete(route('tasks.destroy', task.id), { preserveScroll: true, onSuccess: () => setDialogOpen(false) });
    };

    const pickPhotos = (taskId: number, stage: 'before' | 'after') => {
        setPhotoTask({ id: taskId, stage });
        fileInput.current?.click();
    };

    const uploadPhotos: ChangeEventHandler<HTMLInputElement> = (e) => {
        const files = e.target.files;
        if (!files || files.length === 0 || !photoTask) return;
        router.post(
            route('tasks.photos.store', photoTask.id),
            { stage: photoTask.stage, photos: [...files] },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    if (fileInput.current) fileInput.current.value = '';
                    setPhotoTask(null);
                },
            },
        );
    };

    const removePhoto = async (photo: TaskPhoto) => {
        const ok = await confirm({
            title: 'Delete photo?',
            description: 'This cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
        });
        if (ok) router.delete(route('tasks.photos.destroy', photo.id), { preserveScroll: true });
    };

    const signOff = async () => {
        const ok = await confirm({
            title: 'Record punch-list sign-off?',
            description: `All ${punchItems.length} punch items are done. This records that the customer approved the completed walkthrough.`,
            confirmLabel: 'Record sign-off',
        });
        if (ok) router.post(route('tasks.punch-sign-off', project.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tasks — ${project.name}`} />
            {confirmDialog}
            <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden onChange={uploadPhotos} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Tasks</h1>
                        <p className="text-muted-foreground text-sm">
                            {project.name}
                            {project.client_name ? ` · ${project.client_name}` : ''}
                            {punchItems.length > 0 && ` · punch list ${punchDone}/${punchItems.length} done`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex rounded-md border">
                            <Button
                                variant={view === 'all' ? 'secondary' : 'ghost'}
                                size="sm"
                                className="rounded-r-none"
                                onClick={() => setView('all')}
                            >
                                All tasks
                            </Button>
                            <Button
                                variant={view === 'punch' ? 'secondary' : 'ghost'}
                                size="sm"
                                className="gap-1.5 rounded-l-none"
                                onClick={() => setView('punch')}
                            >
                                <ClipboardCheck className="h-4 w-4" />
                                Punch list
                            </Button>
                        </div>
                        <Select value={assigneeFilter} onValueChange={setAssigneeFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Everyone</SelectItem>
                                {users.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button className="gap-2" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            New task
                        </Button>
                    </div>
                </div>

                {punchSignedOffAt ? (
                    <Card className="flex flex-row items-center gap-3 border-green-600/40 bg-green-500/10 p-4">
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-green-600" />
                        <p className="text-sm">
                            <span className="font-medium">Punch list signed off</span> — customer approved the completed walkthrough on{' '}
                            {punchSignedOffAt}.
                        </p>
                    </Card>
                ) : (
                    punchReady &&
                    isAdmin && (
                        <Card className="flex flex-row flex-wrap items-center justify-between gap-3 border-green-600/40 p-4">
                            <p className="text-sm">
                                <span className="font-medium">All {punchItems.length} punch items are done.</span> Walk the customer through and
                                record their sign-off.
                            </p>
                            <Button size="sm" className="gap-1.5" onClick={signOff}>
                                <CheckCircle2 className="h-4 w-4" />
                                Record sign-off
                            </Button>
                        </Card>
                    )
                )}

                {filtered.length === 0 ? (
                    <Card className="text-muted-foreground p-10 text-center text-sm">
                        {view === 'punch'
                            ? 'No punch-list items yet. Capture them during the final walkthrough with New task.'
                            : 'No tasks yet. Anything that needs doing but isn’t a scheduled job goes here.'}
                    </Card>
                ) : (
                    groups.map(
                        (group) =>
                            group.items.length > 0 && (
                                <section key={group.status} className="flex flex-col gap-2">
                                    <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        {STATUS_LABEL[group.status]}
                                        <span className="ml-2 font-normal normal-case">{group.items.length}</span>
                                    </h2>
                                    <div className="flex flex-col gap-2">
                                        {group.items.map((task) => (
                                            <Card key={task.id} className="flex flex-col gap-3 p-4">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-muted-foreground font-mono text-xs">#{task.number}</span>
                                                            <span
                                                                className={`font-medium ${task.status === 'done' ? 'text-muted-foreground line-through' : ''}`}
                                                            >
                                                                {task.title}
                                                            </span>
                                                            {task.is_punch && (
                                                                <Badge variant="secondary" className="gap-1">
                                                                    <ClipboardCheck className="h-3 w-3" />
                                                                    Punch
                                                                </Badge>
                                                            )}
                                                            <Badge variant={PRIORITY_VARIANT[task.priority]}>{task.priority}</Badge>
                                                        </div>
                                                        <p className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-3 text-xs">
                                                            {task.location && (
                                                                <span className="inline-flex items-center gap-1">
                                                                    <MapPin className="h-3 w-3" />
                                                                    {task.location}
                                                                </span>
                                                            )}
                                                            {task.category && <span>{task.category}</span>}
                                                            {task.assignee && <span>→ {task.assignee}</span>}
                                                            {task.due_date && <span>due {task.due_date}</span>}
                                                            {task.status === 'done' && task.completed_by && (
                                                                <span>
                                                                    done by {task.completed_by} {task.completed_at}
                                                                </span>
                                                            )}
                                                        </p>
                                                        {task.description && <p className="text-muted-foreground mt-1 text-sm">{task.description}</p>}
                                                    </div>
                                                    <div className="flex items-center gap-1.5">
                                                        {task.can_work && (
                                                            <Select value={task.status} onValueChange={(v) => setStatus(task, v)}>
                                                                <SelectTrigger className="h-8 w-32 text-xs">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {statuses.map((s) => (
                                                                        <SelectItem key={s} value={s}>
                                                                            {STATUS_LABEL[s]}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        )}
                                                        {task.can_work && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="gap-1"
                                                                onClick={() => pickPhotos(task.id, task.status === 'done' ? 'after' : 'before')}
                                                                title={task.status === 'done' ? 'Add after photo' : 'Add before photo'}
                                                            >
                                                                <Camera className="h-3.5 w-3.5" />
                                                                {task.status === 'done' ? 'After' : 'Before'}
                                                            </Button>
                                                        )}
                                                        {task.can_edit && (
                                                            <Button size="sm" variant="outline" onClick={() => openEdit(task)}>
                                                                <Pencil className="h-3.5 w-3.5" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>

                                                {task.checklist.length > 0 && (
                                                    <div className="flex flex-col gap-1">
                                                        {task.checklist.map((item) => (
                                                            <label
                                                                key={item.id}
                                                                className={`flex items-center gap-2 text-sm ${task.can_work ? '' : 'pointer-events-none'}`}
                                                            >
                                                                <Checkbox checked={item.done} onCheckedChange={() => toggleItem(task, item)} />
                                                                <span className={item.done ? 'text-muted-foreground line-through' : ''}>
                                                                    {item.label}
                                                                </span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                )}

                                                {task.photos.length > 0 && (
                                                    <div className="flex flex-wrap gap-2">
                                                        {task.photos.map((photo) => (
                                                            <div key={photo.id} className="group relative">
                                                                <a href={photo.full_url} target="_blank" rel="noreferrer">
                                                                    <img
                                                                        src={photo.thumb_url}
                                                                        alt={photo.stage}
                                                                        className="h-20 w-20 rounded-md border object-cover"
                                                                    />
                                                                </a>
                                                                <span className="bg-background/85 absolute bottom-1 left-1 rounded px-1 font-mono text-[10px] uppercase">
                                                                    {photo.stage}
                                                                </span>
                                                                {task.can_work && (
                                                                    <button
                                                                        type="button"
                                                                        className="bg-background/85 text-destructive absolute top-1 right-1 hidden rounded p-0.5 group-hover:block"
                                                                        onClick={() => removePhoto(photo)}
                                                                        aria-label="Delete photo"
                                                                    >
                                                                        <X className="h-3 w-3" />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </Card>
                                        ))}
                                    </div>
                                </section>
                            ),
                    )
                )}
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Edit task #${editing.number}` : 'New task'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="t-title">What needs doing?</Label>
                            <Input
                                id="t-title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="e.g. Door rubs in master bath"
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="t-location">Location</Label>
                                <Input
                                    id="t-location"
                                    value={form.data.location}
                                    onChange={(e) => form.setData('location', e.target.value)}
                                    placeholder="Master bath"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="t-category">Category</Label>
                                <Input
                                    id="t-category"
                                    list="task-categories"
                                    value={form.data.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                    placeholder="doors"
                                />
                                <datalist id="task-categories">
                                    {categories.map((c) => (
                                        <option key={c} value={c} />
                                    ))}
                                </datalist>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="t-priority">Priority</Label>
                                <Select value={form.data.priority} onValueChange={(v) => form.setData('priority', v)}>
                                    <SelectTrigger id="t-priority">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((p) => (
                                            <SelectItem key={p} value={p}>
                                                {p}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="t-assignee">Assign to</Label>
                                <Select value={form.data.assigned_to_user_id} onValueChange={(v) => form.setData('assigned_to_user_id', v)}>
                                    <SelectTrigger id="t-assignee">
                                        <SelectValue placeholder="Nobody yet" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Nobody yet</SelectItem>
                                        {users.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.assigned_to_user_id} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="t-due">Due date</Label>
                                <Input id="t-due" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="t-desc">Details</Label>
                            <Textarea id="t-desc" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label>Checklist</Label>
                            {form.data.checklist.map((item, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <Checkbox
                                        checked={item.done}
                                        onCheckedChange={(v) =>
                                            form.setData(
                                                'checklist',
                                                form.data.checklist.map((c, i) => (i === index ? { ...c, done: v === true } : c)),
                                            )
                                        }
                                    />
                                    <Input
                                        value={item.label}
                                        placeholder="Sub-step"
                                        onChange={(e) =>
                                            form.setData(
                                                'checklist',
                                                form.data.checklist.map((c, i) => (i === index ? { ...c, label: e.target.value } : c)),
                                            )
                                        }
                                    />
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-destructive"
                                        onClick={() =>
                                            form.setData(
                                                'checklist',
                                                form.data.checklist.filter((_, i) => i !== index),
                                            )
                                        }
                                        aria-label="Remove step"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                onClick={() => form.setData('checklist', [...form.data.checklist, { label: '', done: false }])}
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Add step
                            </Button>
                        </div>

                        <label className="flex items-start gap-2.5 rounded-md border p-3 text-sm">
                            <Checkbox className="mt-0.5" checked={form.data.is_punch} onCheckedChange={(v) => form.setData('is_punch', v === true)} />
                            <span>
                                <span className="font-medium">Punch-list item</span>
                                <span className="text-muted-foreground block text-xs">
                                    Part of the final walkthrough — the customer sign-off waits on it.
                                </span>
                            </span>
                        </label>

                        <DialogFooter className="flex items-center justify-between sm:justify-between">
                            {editing && editing.can_edit ? (
                                <Button type="button" variant="outline" className="text-destructive gap-2" onClick={() => removeTask(editing)}>
                                    <Trash2 className="h-4 w-4" />
                                    Remove
                                </Button>
                            ) : (
                                <span />
                            )}
                            <div className="flex gap-2">
                                <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {editing ? 'Save' : 'Add task'}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
