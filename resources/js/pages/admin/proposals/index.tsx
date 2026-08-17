import { Pagination } from '@/components/buffalobuilt/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatCents } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/directory';
import { PROPOSAL_STATUS_STYLES } from '@/types/proposals';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import { useState } from 'react';

interface ProposalRow {
    id: number;
    number: string;
    title: string;
    status: string;
    total_cents: number;
    valid_until: string | null;
    project_id: number;
    project_name: string | null;
    client_name: string | null;
    created_at: string;
}

interface ProjectOption {
    id: number;
    name: string;
    client_name: string | null;
}

interface Props {
    proposals: Paginated<ProposalRow>;
    filters: { status: string | null; per_page: number };
    statuses: string[];
    projects: ProjectOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proposals', href: '/admin/proposals' },
];

export default function ProposalsIndex({ proposals, filters, statuses, projects }: Props) {
    const [projectId, setProjectId] = useState('');
    const [creating, setCreating] = useState(false);

    const createProposal = () => {
        if (!projectId) return;
        setCreating(true);
        router.post(`/admin/projects/${projectId}/proposals`, {}, { onFinish: () => setCreating(false) });
    };

    const filterStatus = (status: string | null) => {
        router.get(
            '/admin/proposals',
            { ...(status ? { status } : {}), per_page: filters.per_page },
            { preserveState: true, preserveScroll: true, replace: true, only: ['proposals', 'filters'] },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proposals" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Proposals</h1>
                        <p className="text-muted-foreground text-sm">Customer quotes snapshotted from each project's takeoff estimate.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={projectId} onValueChange={setProjectId}>
                            <SelectTrigger className="w-56">
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
                        <Button onClick={createProposal} disabled={!projectId || creating}>
                            <Plus className="h-4 w-4" />
                            New Proposal
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                    <Button
                        variant={filters.status === null ? 'default' : 'outline'}
                        size="sm"
                        className="h-8 rounded-full px-3.5"
                        onClick={() => filterStatus(null)}
                    >
                        All
                    </Button>
                    {statuses.map((s) => (
                        <Button
                            key={s}
                            variant={filters.status === s ? 'default' : 'outline'}
                            size="sm"
                            className="h-8 rounded-full px-3.5 capitalize"
                            onClick={() => filterStatus(s)}
                        >
                            {s}
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            All Proposals
                        </CardTitle>
                        <CardDescription>
                            {proposals.total} proposal{proposals.total === 1 ? '' : 's'}
                            {filters.status ? ` with status "${filters.status}"` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {proposals.data.length === 0 ? (
                            <p className="text-muted-foreground px-6 pb-6 text-sm">
                                No proposals yet. Pick a project above and click New Proposal to snapshot its estimate.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[44rem] text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b text-xs">
                                            <th className="px-4 py-2 text-left font-medium">Number</th>
                                            <th className="px-4 py-2 text-left font-medium">Project</th>
                                            <th className="px-4 py-2 text-left font-medium">Status</th>
                                            <th className="px-4 py-2 text-right font-medium">Total</th>
                                            <th className="px-4 py-2 text-left font-medium">Valid Until</th>
                                            <th className="px-4 py-2 text-left font-medium">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {proposals.data.map((p) => (
                                            <tr key={p.id} className="hover:bg-muted/40 border-b last:border-0">
                                                <td className="px-4 py-2.5">
                                                    <Link href={`/admin/proposals/${p.id}`} className="font-medium hover:underline">
                                                        {p.number}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div>{p.project_name}</div>
                                                    {p.client_name && <div className="text-muted-foreground text-xs">{p.client_name}</div>}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <Badge className={cn('capitalize', PROPOSAL_STATUS_STYLES[p.status])}>{p.status}</Badge>
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{formatCents(p.total_cents)}</td>
                                                <td className="text-muted-foreground px-4 py-2.5">{p.valid_until ?? '—'}</td>
                                                <td className="text-muted-foreground px-4 py-2.5">{p.created_at}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Pagination
                    paginator={proposals}
                    routeName="admin.proposals.index"
                    params={{ status: filters.status, per_page: filters.per_page }}
                    propKey="proposals"
                />
            </div>
        </AppLayout>
    );
}
