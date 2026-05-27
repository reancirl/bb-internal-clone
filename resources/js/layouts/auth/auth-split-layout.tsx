import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const features = [
    'Projects, dimensions & customer info in one place',
    'Material orders with supplier + on-site tracking',
    'Bid vs Actual budget variance + CSV export',
    'Crew clock in / out tied to project jobs',
];

const year = new Date().getFullYear();

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background relative grid min-h-dvh grid-cols-1 lg:grid-cols-2">
            {/* Left — brand panel */}
            <div className="bg-primary text-primary-foreground relative hidden flex-col justify-between p-10 lg:flex">
                <div>
                    <Link href={route('home')} className="inline-flex h-14 items-center" aria-label="BuffaloBuilt Internal">
                        <AppLogoIcon className="h-full w-auto" />
                    </Link>
                </div>

                <div className="max-w-md space-y-6">
                    <p className="text-primary-foreground/60 text-xs font-semibold tracking-[0.2em] uppercase">BB Internal</p>
                    <h2 className="text-4xl leading-tight font-semibold">Construction operations, organized.</h2>
                    <p className="text-primary-foreground/80 text-base leading-relaxed">
                        Replace the workbook. Track projects, customer decisions, material orders, budgets and crew hours in one place — so
                        nothing slips between sheets.
                    </p>
                    <ul className="space-y-2 text-sm">
                        {features.map((f) => (
                            <li key={f} className="text-primary-foreground/85 flex items-start gap-3">
                                <span className="bg-primary-foreground/70 mt-2 h-1.5 w-1.5 shrink-0 rounded-full" />
                                <span>{f}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="text-primary-foreground/60 text-xs">© {year} BuffaloBuilt LLC · Residential & Commercial General Contracting</p>
            </div>

            {/* Right — form */}
            <div className="flex flex-col justify-between px-6 py-8 sm:px-12 lg:px-16 lg:py-12">
                <div className="flex justify-center lg:hidden">
                    <Link
                        href={route('home')}
                        className="bg-primary inline-flex h-12 items-center rounded-md px-4"
                        aria-label="BuffaloBuilt Internal"
                    >
                        <AppLogoIcon className="h-9 w-auto" />
                    </Link>
                </div>

                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-10">
                    <div className="space-y-1.5">
                        {title && <h1 className="text-foreground text-3xl font-semibold tracking-tight">{title}</h1>}
                        {description && <p className="text-muted-foreground text-sm">{description}</p>}
                    </div>
                    {children}
                </div>

                <p className="text-muted-foreground text-center text-xs">
                    © {year} BuffaloBuilt LLC · BB Internal
                </p>
            </div>
        </div>
    );
}
