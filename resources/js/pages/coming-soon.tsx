import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';

interface ComingSoonProps {
    title: string;
    milestone?: string;
}

export default function ComingSoon({ title, milestone }: ComingSoonProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-12 text-center">
                <div className="bg-muted text-muted-foreground flex h-14 w-14 items-center justify-center rounded-full">
                    <Construction className="h-7 w-7" />
                </div>
                <h1 className="text-foreground text-2xl font-semibold">{title}</h1>
                <p className="text-muted-foreground max-w-md text-sm">
                    This module is on the roadmap{milestone ? ` for ${milestone}` : ''}. See <span className="font-medium">docs/PLAN.md</span> for
                    what ships when.
                </p>
            </div>
        </AppLayout>
    );
}
