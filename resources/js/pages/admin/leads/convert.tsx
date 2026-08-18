import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatCents } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageProps {
    lead: {
        id: number;
        client_name: string;
        email: string | null;
        phone: string | null;
        build_location: string | null;
        project_details: string | null;
        estimated_value_cents: number | null;
    };
    suggestedName: string;
    dimensionLabels: Record<string, string>;
    statuses: string[];
    takeoffLineCount: number;
}

type ConvertForm = {
    name: string;
    client_name: string;
    address: string;
    status: string;
    start_date: string;
    contract_price: string; // dollars in the form, converted to cents on submit
    generate_takeoff: boolean;
    dimensions: Record<string, string>;
};

const STEPS = [
    { title: 'Client & project', blurb: 'Confirm what carries over from the lead.' },
    { title: 'Dimensions', blurb: 'Drives every takeoff quantity. All optional — fill in what you know.' },
    { title: 'Contract & schedule', blurb: 'Contract price and the date work begins.' },
    { title: 'Review', blurb: 'Check it over, then create the project.' },
];

export default function ConvertLead({ lead, suggestedName, dimensionLabels, statuses, takeoffLineCount }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Leads', href: '/admin/leads' },
        { title: lead.client_name, href: `/admin/leads/${lead.id}` },
        { title: 'Convert', href: `/admin/leads/${lead.id}/convert` },
    ];

    const [step, setStep] = useState(0);

    const form = useForm<ConvertForm>({
        name: suggestedName,
        client_name: lead.client_name,
        address: lead.build_location ?? '',
        status: 'active',
        start_date: '',
        contract_price: lead.estimated_value_cents ? (lead.estimated_value_cents / 100).toFixed(2) : '',
        generate_takeoff: true,
        dimensions: Object.fromEntries(Object.keys(dimensionLabels).map((k) => [k, ''])),
    });

    const setDimension = (key: string, value: string) => {
        form.setData('dimensions', { ...form.data.dimensions, [key]: value });
    };

    const filledDimensions = Object.entries(form.data.dimensions).filter(([, v]) => v !== '');
    const priceCents = form.data.contract_price ? Math.round(parseFloat(form.data.contract_price) * 100) : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            name: data.name,
            client_name: data.client_name || null,
            address: data.address || null,
            status: data.status,
            start_date: data.start_date || null,
            contract_price_cents: data.contract_price ? Math.round(parseFloat(data.contract_price) * 100) : null,
            generate_takeoff: data.generate_takeoff,
            dimensions: Object.fromEntries(Object.entries(data.dimensions).filter(([, v]) => v !== '')),
        }));
        form.post(route('admin.leads.convert', lead.id));
    };

    // Step 1 owns the only required field, so a bad name must not be skipped past.
    const canAdvance = step !== 0 || form.data.name.trim() !== '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Convert ${lead.client_name}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-semibold">Convert lead to project</h1>
                    <p className="text-muted-foreground text-sm">
                        {lead.client_name}
                        {lead.build_location ? ` · ${lead.build_location}` : ''}
                    </p>
                </div>

                <ol className="flex flex-wrap gap-2">
                    {STEPS.map((s, i) => (
                        <li key={s.title} className="flex-1">
                            <button
                                type="button"
                                onClick={() => (i < step || canAdvance ? setStep(i) : undefined)}
                                className={`flex w-full items-center gap-2 rounded-md border px-3 py-2 text-left text-xs transition-colors ${
                                    i === step
                                        ? 'border-primary bg-primary/5 text-foreground'
                                        : i < step
                                          ? 'text-muted-foreground hover:bg-muted/40'
                                          : 'text-muted-foreground/60'
                                }`}
                            >
                                <span
                                    className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold ${
                                        i < step ? 'bg-primary text-primary-foreground' : 'bg-muted'
                                    }`}
                                >
                                    {i < step ? <Check className="h-3 w-3" /> : i + 1}
                                </span>
                                <span className="truncate font-medium">{s.title}</span>
                            </button>
                        </li>
                    ))}
                </ol>

                <form onSubmit={submit}>
                    <Card className="flex flex-col gap-5 p-6">
                        <div>
                            <h2 className="font-semibold">{STEPS[step].title}</h2>
                            <p className="text-muted-foreground text-sm">{STEPS[step].blurb}</p>
                        </div>

                        {step === 0 && (
                            <div className="flex flex-col gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="c-name">Project name</Label>
                                    <Input id="c-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="c-client">Client</Label>
                                        <Input
                                            id="c-client"
                                            value={form.data.client_name}
                                            onChange={(e) => form.setData('client_name', e.target.value)}
                                        />
                                        <InputError message={form.errors.client_name} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="c-status">Status</Label>
                                        <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                            <SelectTrigger id="c-status">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statuses.map((s) => (
                                                    <SelectItem key={s} value={s}>
                                                        {s}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="c-address">Build address</Label>
                                    <Input id="c-address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                                    <InputError message={form.errors.address} />
                                </div>
                                {(lead.email || lead.phone) && (
                                    <p className="text-muted-foreground text-xs">
                                        Lead contact: {[lead.email, lead.phone].filter(Boolean).join(' · ')}
                                    </p>
                                )}
                                {lead.project_details && (
                                    <div className="bg-muted/40 rounded-md p-3">
                                        <p className="text-muted-foreground mb-1 text-xs font-medium uppercase">What they asked for</p>
                                        <p className="text-sm">{lead.project_details}</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {step === 1 && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {Object.entries(dimensionLabels).map(([key, label]) => (
                                    <div key={key} className="space-y-1.5">
                                        <Label htmlFor={`d-${key}`} className="text-xs">
                                            {label}
                                        </Label>
                                        <Input
                                            id={`d-${key}`}
                                            inputMode="decimal"
                                            placeholder="0"
                                            value={form.data.dimensions[key]}
                                            onChange={(e) => setDimension(key, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {step === 2 && (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="c-price">Contract price ($)</Label>
                                        <Input
                                            id="c-price"
                                            inputMode="decimal"
                                            placeholder="0.00"
                                            value={form.data.contract_price}
                                            onChange={(e) => form.setData('contract_price', e.target.value)}
                                        />
                                        {lead.estimated_value_cents !== null && (
                                            <p className="text-muted-foreground text-xs">Lead estimate: {formatCents(lead.estimated_value_cents)}</p>
                                        )}
                                        <InputError message={form.errors.contract_price_cents} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="c-start">Start date</Label>
                                        <Input
                                            id="c-start"
                                            type="date"
                                            value={form.data.start_date}
                                            onChange={(e) => form.setData('start_date', e.target.value)}
                                        />
                                        <InputError message={form.errors.start_date} />
                                    </div>
                                </div>
                                <label className="flex items-start gap-2.5 rounded-md border p-3 text-sm">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={form.data.generate_takeoff}
                                        onCheckedChange={(v) => form.setData('generate_takeoff', v === true)}
                                    />
                                    <span>
                                        <span className="font-medium">Start from the standard takeoff</span>
                                        <span className="text-muted-foreground block text-xs">
                                            Adds the {takeoffLineCount}-line template so quantities calculate from the dimensions above.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        )}

                        {step === 3 && (
                            <dl className="divide-border divide-y text-sm">
                                {[
                                    ['Project', form.data.name],
                                    ['Client', form.data.client_name || '—'],
                                    ['Address', form.data.address || '—'],
                                    ['Status', form.data.status],
                                    ['Contract price', priceCents !== null && !isNaN(priceCents) ? formatCents(priceCents) : 'Not set'],
                                    ['Start date', form.data.start_date || 'Not set'],
                                    ['Dimensions', filledDimensions.length > 0 ? `${filledDimensions.length} of 15 entered` : 'None entered'],
                                    ['Takeoff', form.data.generate_takeoff ? `${takeoffLineCount} template lines` : 'Empty — add lines manually'],
                                ].map(([label, value]) => (
                                    <div key={label} className="flex justify-between gap-4 py-2">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd className="text-right font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        <div className="flex items-center justify-between gap-2 border-t pt-4">
                            {step === 0 ? (
                                <Button type="button" variant="outline" asChild>
                                    <Link href={`/admin/leads/${lead.id}`}>Cancel</Link>
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" className="gap-1.5" onClick={() => setStep(step - 1)}>
                                    <ArrowLeft className="h-4 w-4" />
                                    Back
                                </Button>
                            )}

                            {step < STEPS.length - 1 ? (
                                <Button type="button" className="gap-1.5" disabled={!canAdvance} onClick={() => setStep(step + 1)}>
                                    Next
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            ) : (
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Creating…' : 'Create project'}
                                </Button>
                            )}
                        </div>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
