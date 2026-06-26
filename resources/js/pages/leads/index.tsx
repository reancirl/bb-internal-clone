import { Pagination } from '@/components/buffalobuilt/pagination';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/directory';
import { Head } from '@inertiajs/react';
import { Mail, Phone, MapPin } from 'lucide-react';
import { useState } from 'react';

interface Lead {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    build_location: string | null;
    project_details: string | null;
    source: string;
    submitted_at: string;
}

interface PageProps {
    leads: Paginated<Lead>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Leads', href: '/leads' },
];

export default function LeadsIndex({ leads }: PageProps) {
    const [selected, setSelected] = useState<Lead | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leads" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Leads</h1>
                        <p className="text-muted-foreground text-sm">Submissions from the website contact form.</p>
                    </div>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-muted-foreground text-left text-xs font-semibold tracking-wider uppercase">
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Email</th>
                                    <th className="px-6 py-3">Phone</th>
                                    <th className="px-6 py-3">Build Location</th>
                                    <th className="px-6 py-3">Submitted</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {leads.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-6 py-10 text-center">
                                            No leads yet.
                                        </td>
                                    </tr>
                                ) : (
                                    leads.data.map((lead) => (
                                        <tr
                                            key={lead.id}
                                            className="hover:bg-muted/50 cursor-pointer"
                                            onClick={() => setSelected(lead)}
                                        >
                                            <td className="px-6 py-3 font-medium">
                                                {lead.first_name} {lead.last_name}
                                            </td>
                                            <td className="px-6 py-3">{lead.email}</td>
                                            <td className="px-6 py-3">{lead.phone}</td>
                                            <td className="px-6 py-3">{lead.build_location ?? '—'}</td>
                                            <td className="px-6 py-3 text-muted-foreground">
                                                {new Date(lead.submitted_at).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination paginator={leads} routeName="leads.index" params={{}} propKey="leads" />
            </div>

            <Dialog open={!!selected} onOpenChange={(o) => !o && setSelected(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {selected?.first_name} {selected?.last_name}
                        </DialogTitle>
                    </DialogHeader>
                    {selected && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="text-muted-foreground space-y-1">
                                    <span className="text-xs font-medium uppercase tracking-wider">Email</span>
                                    <div className="text-foreground flex items-center gap-1.5 text-sm">
                                        <Mail className="h-3.5 w-3.5 shrink-0" />
                                        <a href={`mailto:${selected.email}`} className="hover:text-primary">
                                            {selected.email}
                                        </a>
                                    </div>
                                </div>
                                <div className="text-muted-foreground space-y-1">
                                    <span className="text-xs font-medium uppercase tracking-wider">Phone</span>
                                    <div className="text-foreground flex items-center gap-1.5 text-sm">
                                        <Phone className="h-3.5 w-3.5 shrink-0" />
                                        <a href={`tel:${selected.phone}`}>
                                            {selected.phone}
                                        </a>
                                    </div>
                                </div>
                                <div className="text-muted-foreground space-y-1">
                                    <span className="text-xs font-medium uppercase tracking-wider">Build Location</span>
                                    <div className="text-foreground flex items-center gap-1.5 text-sm">
                                        <MapPin className="h-3.5 w-3.5 shrink-0" />
                                        <span>{selected.build_location ?? 'Not specified'}</span>
                                    </div>
                                </div>
                                <div className="text-muted-foreground space-y-1">
                                    <span className="text-xs font-medium uppercase tracking-wider">Submitted</span>
                                    <div className="text-foreground text-sm">
                                        {new Date(selected.submitted_at).toLocaleDateString(undefined, {
                                            weekday: 'short',
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
                                        })}
                                    </div>
                                </div>
                            </div>

                            {selected.project_details && (
                                <div className="text-muted-foreground space-y-1.5">
                                    <span className="text-xs font-medium uppercase tracking-wider">Project Details</span>
                                    <p className="text-foreground whitespace-pre-wrap rounded-md border bg-muted/30 p-3 text-sm">
                                        {selected.project_details}
                                    </p>
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-xs">Source:</span>
                                <Badge variant="secondary" className="text-xs capitalize">
                                    {selected.source}
                                </Badge>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
