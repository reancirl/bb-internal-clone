import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCents } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { PROPOSAL_STATUS_STYLES } from '@/types/proposals';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Download, FileText, Send, Trash2, X } from 'lucide-react';
import { Fragment } from 'react';

interface ProposalLine {
    id: number;
    category: string | null;
    item: string;
    qty: string | null;
    unit: string | null;
    unit_price_cents: number | null;
    total_cents: number | null;
}

interface Proposal {
    id: number;
    number: string;
    title: string;
    status: string;
    total_cents: number;
    payment_terms: string | null;
    notes: string | null;
    valid_until: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    rejected_at: string | null;
    created_at: string;
    created_by: string | null;
    project: { id: number; name: string; client_name: string | null; address: string | null } | null;
}

interface Props {
    proposal: Proposal;
    lines: ProposalLine[];
    allowedTransitions: string[];
}

const TRANSITION_META: Record<string, { label: string; icon: React.ReactNode; variant: 'default' | 'destructive' | 'outline' }> = {
    sent: { label: 'Mark Sent', icon: <Send className="h-4 w-4" />, variant: 'default' },
    accepted: { label: 'Mark Accepted', icon: <Check className="h-4 w-4" />, variant: 'default' },
    rejected: { label: 'Mark Rejected', icon: <X className="h-4 w-4" />, variant: 'destructive' },
};

export default function ProposalShow({ proposal, lines, allowedTransitions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Proposals', href: '/admin/proposals' },
        { title: proposal.number, href: `/admin/proposals/${proposal.id}` },
    ];

    const isDraft = proposal.status === 'draft';
    const [confirm, confirmDialog] = useConfirm();

    const form = useForm({
        title: proposal.title,
        payment_terms: proposal.payment_terms ?? '',
        notes: proposal.notes ?? '',
        valid_until: proposal.valid_until ?? '',
    });

    const saveDetails = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/proposals/${proposal.id}`, { preserveScroll: true });
    };

    const transition = (status: string) => {
        router.post(`/admin/proposals/${proposal.id}/transition`, { status }, { preserveScroll: true });
    };

    const destroy = async () => {
        const confirmed = await confirm({
            title: `Delete ${proposal.number}?`,
            description: 'This draft proposal and all its line items will be permanently deleted.',
            confirmLabel: 'Delete proposal',
            destructive: true,
        });
        if (confirmed) {
            router.delete(`/admin/proposals/${proposal.id}`);
        }
    };

    const unpricedCount = lines.filter((l) => l.total_cents === null).length;

    let currentCategory: string | null | false = false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={proposal.number} />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                <Link href="/admin/proposals">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <h1 className="text-foreground text-2xl font-semibold">{proposal.number}</h1>
                            <Badge className={cn('capitalize', PROPOSAL_STATUS_STYLES[proposal.status])}>{proposal.status}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 ml-10 text-sm">
                            {proposal.project?.name}
                            {proposal.project?.client_name ? ` — ${proposal.project.client_name}` : ''}
                            {proposal.created_by ? ` · created by ${proposal.created_by}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a href={`/admin/proposals/${proposal.id}/pdf`}>
                                <Download className="h-4 w-4" />
                                Download PDF
                            </a>
                        </Button>
                        {allowedTransitions.map((s) => {
                            const meta = TRANSITION_META[s];
                            if (!meta) return null;
                            return (
                                <Button key={s} variant={meta.variant} size="sm" onClick={() => transition(s)}>
                                    {meta.icon}
                                    {meta.label}
                                </Button>
                            );
                        })}
                        {isDraft && (
                            <Button variant="outline" size="sm" onClick={destroy} title="Delete draft">
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-4 w-4" />
                                Line Items
                            </CardTitle>
                            <CardDescription>
                                Snapshotted from the takeoff estimate on {new Date(proposal.created_at).toLocaleDateString()}.
                                {unpricedCount > 0 && (
                                    <span className="text-amber-600 dark:text-amber-400">
                                        {' '}
                                        {unpricedCount} line{unpricedCount === 1 ? '' : 's'} without pricing (shown as TBD).
                                    </span>
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="max-h-[65vh] overflow-auto">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead className="bg-card sticky top-0 z-10">
                                        <tr className="text-muted-foreground border-b text-xs">
                                            <th className="px-4 py-2 text-left font-medium">Item</th>
                                            <th className="px-4 py-2 text-right font-medium">Qty</th>
                                            <th className="px-4 py-2 text-left font-medium">Unit</th>
                                            <th className="px-4 py-2 text-right font-medium">Unit Price</th>
                                            <th className="px-4 py-2 text-right font-medium">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lines.map((l) => {
                                            const showCategory = l.category !== currentCategory;
                                            currentCategory = l.category;
                                            return (
                                                <Fragment key={l.id}>
                                                    {showCategory && (
                                                        <tr className="bg-muted/50">
                                                            <td colSpan={5} className="px-4 py-1.5 text-xs font-semibold tracking-wide uppercase">
                                                                {l.category ?? 'General'}
                                                            </td>
                                                        </tr>
                                                    )}
                                                    <tr className="border-b last:border-0">
                                                        <td className="px-4 py-2">{l.item}</td>
                                                        <td className="px-4 py-2 text-right tabular-nums">{l.qty ?? '—'}</td>
                                                        <td className="text-muted-foreground px-4 py-2">{l.unit ?? '—'}</td>
                                                        <td className="px-4 py-2 text-right tabular-nums">
                                                            {l.unit_price_cents !== null ? formatCents(l.unit_price_cents) : '—'}
                                                        </td>
                                                        <td className="px-4 py-2 text-right font-medium tabular-nums">
                                                            {l.total_cents !== null ? (
                                                                formatCents(l.total_cents)
                                                            ) : (
                                                                <span className="text-amber-600 dark:text-amber-400">TBD</span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                </Fragment>
                                            );
                                        })}
                                        <tr className="bg-muted sticky bottom-0 z-10 border-t-2">
                                            <td colSpan={4} className="px-4 py-2.5 text-right font-medium">
                                                Proposal Total
                                            </td>
                                            <td className="px-4 py-2.5 text-right text-base font-semibold tabular-nums">
                                                {formatCents(proposal.total_cents)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                            <CardDescription>
                                {isDraft ? 'Editable while the proposal is a draft.' : 'Locked — proposal is no longer a draft.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveDetails} className="flex flex-col gap-3">
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="title" className="text-muted-foreground text-xs font-medium">
                                        Title
                                    </label>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                    {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="valid_until" className="text-muted-foreground text-xs font-medium">
                                        Valid until
                                    </label>
                                    <Input
                                        id="valid_until"
                                        type="date"
                                        value={form.data.valid_until}
                                        onChange={(e) => form.setData('valid_until', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="payment_terms" className="text-muted-foreground text-xs font-medium">
                                        Payment terms
                                    </label>
                                    <Textarea
                                        id="payment_terms"
                                        rows={4}
                                        placeholder="e.g. 30% deposit on signing, 40% at framing, 30% on completion."
                                        value={form.data.payment_terms}
                                        onChange={(e) => form.setData('payment_terms', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="notes" className="text-muted-foreground text-xs font-medium">
                                        Notes (shown on the PDF)
                                    </label>
                                    <Textarea
                                        id="notes"
                                        rows={3}
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                </div>
                                {isDraft && (
                                    <Button type="submit" size="sm" disabled={form.processing}>
                                        Save details
                                    </Button>
                                )}
                            </form>

                            <div className="text-muted-foreground mt-4 space-y-1 border-t pt-3 text-xs">
                                {proposal.sent_at && <div>Sent {new Date(proposal.sent_at).toLocaleString()}</div>}
                                {proposal.accepted_at && <div>Accepted {new Date(proposal.accepted_at).toLocaleString()}</div>}
                                {proposal.rejected_at && <div>Rejected {new Date(proposal.rejected_at).toLocaleString()}</div>}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
