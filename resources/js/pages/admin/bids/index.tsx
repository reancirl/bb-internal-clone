import { Pagination } from '@/components/buffalobuilt/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { BID_STATUS_STYLES } from '@/types/bids';
import { type Paginated } from '@/types/directory';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Gavel, Plus } from 'lucide-react';
import { useState } from 'react';

interface BidRow {
    id: number;
    title: string;
    trade: string | null;
    status: string;
    due_date: string | null;
    overdue: boolean;
    project_id: number;
    project_name: string | null;
    client_name: string | null;
    responses_count: number;
    received_count: number;
    created_at: string;
}

interface ProjectOption {
    id: number;
    name: string;
    client_name: string | null;
}

interface Filters {
    status: string | null;
    trade: string | null;
    per_page: number;
}

interface Props {
    bids: Paginated<BidRow>;
    filters: Filters;
    statuses: string[];
    trades: string[];
    projects: ProjectOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sub Bids', href: '/admin/bids' },
];

const ALL = '__all__';

const NONE = '__none__';

export default function BidsIndex({ bids, filters, statuses, trades, projects }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    const form = useForm({
        project_id: '',
        title: '',
        trade: '',
        due_date: '',
        scope_description: '',
    });

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.data.project_id) return;
        // Empty strings become null server-side via ConvertEmptyStringsToNull.
        form.post(`/admin/projects/${form.data.project_id}/bids`, { onSuccess: () => setDialogOpen(false) });
    };

    const applyFilters = (next: Partial<Filters>) => {
        router.get('/admin/bids', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true, only: ['bids', 'filters'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sub Bids" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Sub Bids</h1>
                        <p className="text-muted-foreground text-sm">Request quotes from trade partners, compare them, and award the work.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        New Bid Request
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                    <Button
                        variant={filters.status === null ? 'default' : 'outline'}
                        size="sm"
                        className="h-8 rounded-full px-3.5"
                        onClick={() => applyFilters({ status: null })}
                    >
                        All
                    </Button>
                    {statuses.map((s) => (
                        <Button
                            key={s}
                            variant={filters.status === s ? 'default' : 'outline'}
                            size="sm"
                            className="h-8 rounded-full px-3.5 capitalize"
                            onClick={() => applyFilters({ status: s })}
                        >
                            {s}
                        </Button>
                    ))}
                    <Select value={filters.trade ?? ALL} onValueChange={(v) => applyFilters({ trade: v === ALL ? null : v })}>
                        <SelectTrigger className="ml-auto h-8 w-52">
                            <SelectValue placeholder="All trades" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All trades</SelectItem>
                            {trades.map((t) => (
                                <SelectItem key={t} value={t}>
                                    {t}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Gavel className="h-4 w-4" />
                            All Bid Requests
                        </CardTitle>
                        <CardDescription>
                            {bids.total} request{bids.total === 1 ? '' : 's'}
                            {filters.status ? ` with status "${filters.status}"` : ''}
                            {filters.trade ? ` for ${filters.trade}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {bids.data.length === 0 ? (
                            <p className="text-muted-foreground px-6 pb-6 text-sm">
                                No bid requests yet. Click New Bid Request to start collecting quotes for a project scope.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[48rem] text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b text-xs">
                                            <th className="px-4 py-2 text-left font-medium">Title</th>
                                            <th className="px-4 py-2 text-left font-medium">Project</th>
                                            <th className="px-4 py-2 text-left font-medium">Trade</th>
                                            <th className="px-4 py-2 text-right font-medium">Quotes</th>
                                            <th className="px-4 py-2 text-left font-medium">Status</th>
                                            <th className="px-4 py-2 text-left font-medium">Due</th>
                                            <th className="px-4 py-2 text-left font-medium">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bids.data.map((b) => (
                                            <tr key={b.id} className="hover:bg-muted/40 border-b last:border-0">
                                                <td className="px-4 py-2.5">
                                                    <Link href={`/admin/bids/${b.id}`} className="font-medium hover:underline">
                                                        {b.title}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div>{b.project_name}</div>
                                                    {b.client_name && <div className="text-muted-foreground text-xs">{b.client_name}</div>}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2.5">{b.trade ?? '—'}</td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {b.received_count}/{b.responses_count}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <Badge className={cn('capitalize', BID_STATUS_STYLES[b.status])}>{b.status}</Badge>
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-4 py-2.5',
                                                        b.overdue ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {b.due_date ?? '—'}
                                                    {b.overdue && ' · overdue'}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2.5">{b.created_at}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Pagination
                    paginator={bids}
                    routeName="admin.bids.index"
                    params={{ status: filters.status, trade: filters.trade, per_page: filters.per_page }}
                    propKey="bids"
                />
            </div>

            {/* New bid request dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New bid request</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="f-project">Project</Label>
                            <Select value={form.data.project_id} onValueChange={(v) => form.setData('project_id', v)}>
                                <SelectTrigger id="f-project">
                                    <SelectValue placeholder="Pick a project…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {projects.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
                                            {p.client_name ? ` — ${p.client_name}` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="f-title">Title</Label>
                            <Input
                                id="f-title"
                                placeholder="e.g. Excavation & grading"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="f-trade">Trade</Label>
                                <Select value={form.data.trade || NONE} onValueChange={(v) => form.setData('trade', v === NONE ? '' : v)}>
                                    <SelectTrigger id="f-trade">
                                        <SelectValue placeholder="Optional" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>No trade</SelectItem>
                                        {trades.map((t) => (
                                            <SelectItem key={t} value={t}>
                                                {t}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.trade} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="f-due">Quotes needed by</Label>
                                <Input id="f-due" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                                <InputError message={form.errors.due_date} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="f-scope">Scope description</Label>
                            <Textarea
                                id="f-scope"
                                rows={4}
                                placeholder="What the subs are pricing — plans, inclusions, exclusions."
                                value={form.data.scope_description}
                                onChange={(e) => form.setData('scope_description', e.target.value)}
                            />
                            <InputError message={form.errors.scope_description} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={!form.data.project_id || form.processing}>
                                Create draft
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
