import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const year = new Date().getFullYear();

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background grid min-h-dvh grid-cols-1 lg:grid-cols-[1.05fr_1fr]">
            {/* ---------- Left: brand panel (lg and up) ---------- */}
            <aside className="bg-primary text-primary-foreground relative hidden overflow-hidden lg:flex lg:flex-col lg:items-center lg:justify-center lg:p-14">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 opacity-[0.13]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,.5) 1px, transparent 1px)',
                        backgroundSize: '72px 72px',
                        maskImage: 'radial-gradient(ellipse 75% 60% at 50% 45%, transparent 25%, #000 100%)',
                        WebkitMaskImage: 'radial-gradient(ellipse 75% 60% at 50% 45%, transparent 25%, #000 100%)',
                    }}
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute top-[38%] left-1/2 aspect-square w-[38rem] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-[0.14]"
                    style={{ background: 'radial-gradient(circle, #fff 0%, transparent 62%)' }}
                />

                <div className="relative flex w-full max-w-2xl flex-col items-center px-4 text-center">
                    <Link
                        href={route('home')}
                        className="focus-visible:ring-primary-foreground/40 block w-[min(78%,30rem)] rounded-sm transition-transform duration-300 ease-out hover:scale-[1.02] focus-visible:ring-2 focus-visible:ring-offset-8 focus-visible:ring-offset-transparent focus-visible:outline-hidden"
                        aria-label="BuffaloBuilt Internal"
                    >
                        <AppLogoIcon className="h-auto w-full drop-shadow-2xl" />
                    </Link>

                    <div aria-hidden className="my-10 h-px w-24 bg-gradient-to-r from-transparent via-white/25 to-transparent" />

                    <h2 className="text-[2rem] leading-[1.15] font-semibold tracking-[-0.02em] text-balance xl:text-[2.375rem]">
                        Every job, on the record.
                    </h2>
                    <p className="text-primary-foreground/60 mx-auto mt-4 max-w-md text-[0.9375rem] leading-relaxed text-pretty">
                        Projects, material orders, budgets and crew hours in one system — so nothing slips between spreadsheets.
                    </p>
                </div>

                <p className="text-primary-foreground/35 absolute bottom-10 text-xs tracking-wide">© {year} BuffaloBuilt LLC · Sheridan, Wyoming</p>
            </aside>

            {/* ---------- Right: form ----------
                Below lg there is no brand panel and no banner: just the navy mark
                on the page background, then the form. Nothing to decorate. */}
            <main className="flex flex-col items-center justify-center px-6 py-10 sm:px-8">
                <div className="flex w-full max-w-[23rem] flex-1 flex-col justify-center">
                    <Link
                        href={route('home')}
                        className="focus-visible:ring-ring mx-auto mb-9 block w-[9.5rem] rounded-sm focus-visible:ring-2 focus-visible:ring-offset-4 focus-visible:outline-hidden lg:hidden"
                        aria-label="BuffaloBuilt Internal"
                    >
                        <AppLogoIcon tone="dark" className="h-auto w-full dark:hidden" />
                        <AppLogoIcon className="hidden h-auto w-full dark:block" />
                    </Link>

                    <header className="mb-8 text-center lg:text-left">
                        {title && (
                            <h1 className="text-foreground text-[1.625rem] leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-[1.75rem]">
                                {title}
                            </h1>
                        )}
                        {description && <p className="text-muted-foreground mt-2.5 text-sm leading-relaxed text-pretty">{description}</p>}
                    </header>

                    {children}
                </div>

                <p className="text-muted-foreground/60 w-full max-w-[23rem] pt-10 text-center text-xs lg:text-left">© {year} BuffaloBuilt LLC</p>
            </main>
        </div>
    );
}
