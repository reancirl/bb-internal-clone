import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCents } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { BID_STATUS_STYLES } from '@/types/bids';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Award, Ban, Gavel, Phone, Plus, Send, Trash2, Undo2, X } from 'lucide-react';
import { useState } from 'react';

interface Bid {
    id: number;
    title: string;
    trade: string | null;
    scope_description: string | null;
    due_date: string | null;
    status: string;
    awarded_response_id: number | null;
    budget_line_id: number | null;
    opened_at: string | null;
    awarded_at: string | null;
    canceled_at: string | null;
    created_at: string;
    created_by: string | null;
    project: { id: number; name: string; client_name: string | null } | null;
}

interface ResponseRow {
    id: number;
    trade_partner_id: number | null;
    name: string;
    phone: string | null;
    email: string | null;
    status: string;
    amount_cents: number | null;
    notes: string | null;
    received_at: string | null;
}

interface PartnerOption {
    id: number;
    name: string;
    phone: string | null;
    trades: string[];
}

interface Props {
    bid: Bid;
    responses: ResponseRow[];
    partners: PartnerOption[];
    trades: string[];
    allowedTransitions: string[];
}

const NONE = '__none__';

function centsToInput(cents: number | null): string {
    return cents === null ? '' : (cents / 100).toString();
}

function inputToCents(value: string): number | null {
    const trimmed = value.trim();
    if (trimmed === '') return null;
    const parsed = parseFloat(trimmed);
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

const STATUS_ORDER: Record<string, number> = { received: 0, invited: 1, declined: 2 };

export default function BidShow({ bid, responses, partners, trades, allowedTransitions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Sub Bids', href: '/admin/bids' },
        { title: bid.title, href: `/admin/bids/${bid.id}` },
    ];

    const isDraft = bid.status === 'draft';
    const isAwarded = bid.status === 'awarded';
    const quotesEditable = bid.status === 'draft' || bid.status === 'open';
    const [confirm, confirmDialog] = useConfirm();

    const form = useForm({
        title: bid.title,
        trade: bid.trade ?? '',
        due_date: bid.due_date ?? '',
        scope_description: bid.scope_description ?? '',
    });

    const saveDetails = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/bids/${bid.id}`, { preserveScroll: true });
    };

    const transition = async (status: string) => {
        if (status === 'canceled') {
            const ok = await confirm({
                title: 'Cancel this bid request?',
                description: 'Invited partners should be told the work is off. The request and its quotes are kept as history.',
                confirmLabel: 'Cancel request',
                destructive: true,
            });
            if (!ok) return;
        }
        router.post(`/admin/bids/${bid.id}/transition`, { status }, { preserveScroll: true });
    };

    const award = async (r: ResponseRow) => {
        if (r.amount_cents === null) return;
        const ok = await confirm({
            title: `Award to ${r.name}?`,
            description: `${bid.title} will be awarded for ${formatCents(r.amount_cents)}. This writes the committed amount to the project budget's SUB BIDS section.`,
            confirmLabel: `Award for ${formatCents(r.amount_cents)}`,
        });
        if (ok) {
            router.post(`/admin/bids/${bid.id}/transition`, { status: 'awarded', response_id: r.id }, { preserveScroll: true });
        }
    };

    const destroy = async () => {
        const ok = await confirm({
            title: 'Delete this draft bid request?',
            description: 'The request and its invitations will be permanently deleted.',
            confirmLabel: 'Delete draft',
            destructive: true,
        });
        if (ok) {
            router.delete(`/admin/bids/${bid.id}`);
        }
    };

    const received = responses.filter((r) => r.status === 'received' && r.amount_cents !== null);
    const amounts = received.map((r) => r.amount_cents as number);
    const low = amounts.length ? Math.min(...amounts) : null;
    const high = amounts.length ? Math.max(...amounts) : null;
    const avg = amounts.length ? Math.round(amounts.reduce((s, a) => s + a, 0) / amounts.length) : null;

    const sorted = [...responses].sort(
        (a, b) => (STATUS_ORDER[a.status] ?? 3) - (STATUS_ORDER[b.status] ?? 3) || (a.amount_cents ?? Infinity) - (b.amount_cents ?? Infinity),
    );
    const winner = responses.find((r) => r.id === bid.awarded_response_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={bid.title} />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                <Link href="/admin/bids">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <h1 className="text-foreground text-2xl font-semibold">{bid.title}</h1>
                            <Badge className={cn('capitalize', BID_STATUS_STYLES[bid.status])}>{bid.status}</Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 ml-10 text-sm">
                            {bid.project?.name}
                            {bid.project?.client_name ? ` — ${bid.project.client_name}` : ''}
                            {bid.trade ? ` · ${bid.trade}` : ''}
                            {bid.created_by ? ` · created by ${bid.created_by}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {allowedTransitions.includes('open') && (
                            <Button size="sm" onClick={() => transition('open')}>
                                <Send className="h-4 w-4" />
                                Open for Quotes
                            </Button>
                        )}
                        {allowedTransitions.includes('canceled') && (
                            <Button variant="outline" size="sm" onClick={() => transition('canceled')}>
                                <X className="h-4 w-4" />
                                Cancel Request
                            </Button>
                        )}
                        {isDraft && (
                            <Button variant="outline" size="sm" onClick={destroy} title="Delete draft">
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>

                {isAwarded && winner && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">
                        <span className="font-semibold">Awarded to {winner.name}</span>
                        {winner.amount_cents !== null && <> for {formatCents(winner.amount_cents)}</>}
                        {' — committed to the '}
                        <Link href={`/projects/${bid.project?.id}/budget`} className="font-medium underline">
                            project budget
                        </Link>
                        's SUB BIDS section.
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Gavel className="h-4 w-4" />
                                Quote Comparison
                            </CardTitle>
                            <CardDescription>
                                {received.length} of {responses.length} quote{responses.length === 1 ? '' : 's'} received.
                                {quotesEditable && ' Record amounts as they come in by phone or email.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {received.length > 0 && (
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="rounded-lg border p-3">
                                        <div className="text-muted-foreground text-xs font-medium uppercase">Low</div>
                                        <div className="text-xl font-semibold text-green-700 tabular-nums dark:text-green-400">
                                            {low !== null ? formatCents(low) : '—'}
                                        </div>
                                    </div>
                                    <div className="rounded-lg border p-3">
                                        <div className="text-muted-foreground text-xs font-medium uppercase">Average</div>
                                        <div className="text-xl font-semibold tabular-nums">{avg !== null ? formatCents(avg) : '—'}</div>
                                    </div>
                                    <div className="rounded-lg border p-3">
                                        <div className="text-muted-foreground text-xs font-medium uppercase">High</div>
                                        <div className="text-xl font-semibold tabular-nums">{high !== null ? formatCents(high) : '—'}</div>
                                    </div>
                                </div>
                            )}

                            {responses.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No partners invited yet. Use the picker below to build the bid list.</p>
                            ) : (
                                <div className="flex flex-col gap-1.5">
                                    {sorted.map((r) => (
                                        <QuoteRow
                                            key={r.id}
                                            response={r}
                                            editable={quotesEditable}
                                            isLowest={r.amount_cents !== null && r.amount_cents === low && r.status === 'received'}
                                            isWinner={r.id === bid.awarded_response_id}
                                            canAward={bid.status === 'open' && r.status === 'received' && r.amount_cents !== null}
                                            onAward={() => award(r)}
                                        />
                                    ))}
                                </div>
                            )}

                            {quotesEditable && <InvitePanel bid={bid} responses={responses} partners={partners} />}
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Scope</CardTitle>
                            <CardDescription>
                                {isDraft
                                    ? 'Editable while the request is a draft.'
                                    : 'Locked — subs are pricing this scope. Cancel and re-issue to change it.'}
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
                                    <label htmlFor="trade" className="text-muted-foreground text-xs font-medium">
                                        Trade
                                    </label>
                                    <Select
                                        value={form.data.trade || NONE}
                                        onValueChange={(v) => form.setData('trade', v === NONE ? '' : v)}
                                        disabled={!isDraft}
                                    >
                                        <SelectTrigger id="trade">
                                            <SelectValue placeholder="No trade" />
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
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="due_date" className="text-muted-foreground text-xs font-medium">
                                        Quotes needed by
                                    </label>
                                    <Input
                                        id="due_date"
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) => form.setData('due_date', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label htmlFor="scope_description" className="text-muted-foreground text-xs font-medium">
                                        Scope description
                                    </label>
                                    <Textarea
                                        id="scope_description"
                                        rows={6}
                                        placeholder="What the subs are pricing — plans, inclusions, exclusions."
                                        value={form.data.scope_description}
                                        onChange={(e) => form.setData('scope_description', e.target.value)}
                                        disabled={!isDraft}
                                    />
                                </div>
                                {isDraft && (
                                    <Button type="submit" size="sm" disabled={form.processing}>
                                        Save scope
                                    </Button>
                                )}
                            </form>

                            <div className="text-muted-foreground mt-4 space-y-1 border-t pt-3 text-xs">
                                {bid.opened_at && <div>Opened {new Date(bid.opened_at).toLocaleString()}</div>}
                                {bid.awarded_at && <div>Awarded {new Date(bid.awarded_at).toLocaleString()}</div>}
                                {bid.canceled_at && <div>Canceled {new Date(bid.canceled_at).toLocaleString()}</div>}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function QuoteRow({
    response: r,
    editable,
    isLowest,
    isWinner,
    canAward,
    onAward,
}: {
    response: ResponseRow;
    editable: boolean;
    isLowest: boolean;
    isWinner: boolean;
    canAward: boolean;
    onAward: () => void;
}) {
    const [amount, setAmount] = useState(centsToInput(r.amount_cents));
    const [notes, setNotes] = useState(r.notes ?? '');

    const saveAmount = () => {
        const cents = inputToCents(amount);
        if (cents !== r.amount_cents) {
            router.put(`/admin/bid-responses/${r.id}`, { amount_cents: cents }, { preserveScroll: true });
        }
    };

    const saveNotes = () => {
        if (notes !== (r.notes ?? '')) {
            router.put(`/admin/bid-responses/${r.id}`, { notes: notes || null }, { preserveScroll: true });
        }
    };

    const toggleDeclined = () => {
        router.put(`/admin/bid-responses/${r.id}`, { declined: r.status !== 'declined' }, { preserveScroll: true });
    };

    const remove = () => {
        router.delete(`/admin/bid-responses/${r.id}`, { preserveScroll: true });
    };

    const muted = r.status === 'declined' || (r.status === 'invited' && !editable);

    return (
        <div
            className={cn(
                'group flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border px-3 py-2',
                isWinner && 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/40',
                !isWinner && isLowest && 'border-green-200 dark:border-green-900/60',
            )}
        >
            <div className="min-w-0 flex-1">
                <div className={cn('flex items-center gap-2 text-sm font-medium', muted && 'text-muted-foreground')}>
                    <span className="truncate">{r.name}</span>
                    {isWinner && (
                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                            <Award className="h-3 w-3" />
                            Awarded
                        </Badge>
                    )}
                    {!isWinner && isLowest && <span className="text-xs font-semibold text-green-700 dark:text-green-400">lowest</span>}
                    {r.status === 'declined' && <span className="text-muted-foreground text-xs">declined</span>}
                    {r.status === 'invited' && <span className="text-muted-foreground text-xs">awaiting quote</span>}
                </div>
                {r.phone && (
                    <a href={`tel:${r.phone}`} className="text-muted-foreground hover:text-primary flex items-center gap-1 text-xs">
                        <Phone className="h-3 w-3" />
                        {r.phone}
                    </a>
                )}
            </div>

            {editable && r.status !== 'declined' ? (
                <Input
                    type="number"
                    min="0"
                    step="100"
                    placeholder="$ amount"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    onBlur={saveAmount}
                    className="h-8 w-28 px-2 text-right text-sm tabular-nums"
                />
            ) : (
                <span className={cn('text-sm font-semibold tabular-nums', muted && 'text-muted-foreground font-normal')}>
                    {r.amount_cents !== null ? formatCents(r.amount_cents) : '—'}
                </span>
            )}

            {editable && (
                <Input
                    placeholder="Notes — inclusions, lead time…"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    onBlur={saveNotes}
                    className="text-muted-foreground focus-visible:border-input h-8 w-44 border-transparent bg-transparent px-1.5 text-xs shadow-none"
                />
            )}
            {!editable && r.notes && <span className="text-muted-foreground max-w-44 truncate text-xs">{r.notes}</span>}

            {canAward && (
                <Button size="sm" className="h-7" onClick={onAward}>
                    <Award className="h-3.5 w-3.5" />
                    Award
                </Button>
            )}

            {editable && (
                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        onClick={toggleDeclined}
                        title={r.status === 'declined' ? 'Mark as bidding again' : 'Mark as not bidding'}
                    >
                        {r.status === 'declined' ? <Undo2 className="h-3.5 w-3.5" /> : <Ban className="h-3.5 w-3.5" />}
                    </Button>
                    <Button variant="ghost" size="icon" className="text-destructive h-7 w-7" onClick={remove} title="Remove invitation">
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                </div>
            )}
        </div>
    );
}

function InvitePanel({ bid, responses, partners }: { bid: Bid; responses: ResponseRow[]; partners: PartnerOption[] }) {
    const [partnerId, setPartnerId] = useState('');
    const [showAllTrades, setShowAllTrades] = useState(false);
    const [inviting, setInviting] = useState(false);

    const invitedIds = new Set(responses.map((r) => r.trade_partner_id).filter((id): id is number => id !== null));
    const available = partners.filter((p) => !invitedIds.has(p.id) && (showAllTrades || !bid.trade || p.trades.includes(bid.trade)));
    const selected = partners.find((p) => String(p.id) === partnerId);

    const invite = () => {
        if (!partnerId) return;
        setInviting(true);
        router.post(
            `/admin/bids/${bid.id}/responses`,
            { trade_partner_id: Number(partnerId) },
            { preserveScroll: true, onFinish: () => setInviting(false), onSuccess: () => setPartnerId('') },
        );
    };

    return (
        <div className="flex flex-col gap-2 border-t pt-4">
            <Label className="text-muted-foreground text-xs font-medium">
                Invite a trade partner
                {bid.trade && !showAllTrades ? ` (${bid.trade})` : ''}
            </Label>
            <div className="flex flex-wrap items-center gap-2">
                <Select value={partnerId} onValueChange={setPartnerId}>
                    <SelectTrigger className="w-64">
                        <SelectValue placeholder={available.length === 0 ? 'No partners left to invite' : 'Pick a partner…'} />
                    </SelectTrigger>
                    <SelectContent>
                        {available.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>
                                {p.name}
                                {p.phone ? ` — ${p.phone}` : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button size="sm" onClick={invite} disabled={!partnerId || inviting}>
                    <Plus className="h-4 w-4" />
                    Invite
                </Button>
                {bid.trade && (
                    <label className="text-muted-foreground flex items-center gap-2 text-xs">
                        <Checkbox checked={showAllTrades} onCheckedChange={(v) => setShowAllTrades(v === true)} />
                        Show all trades
                    </label>
                )}
            </div>
            {selected?.phone && (
                <a href={`tel:${selected.phone}`} className="text-muted-foreground hover:text-primary flex items-center gap-1 text-xs">
                    <Phone className="h-3 w-3" />
                    Call {selected.name}: {selected.phone}
                </a>
            )}
        </div>
    );
}
