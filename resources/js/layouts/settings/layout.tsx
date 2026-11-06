import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const baseNavItems: NavItem[] = [
    {
        title: 'Profile',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Password',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Appearance',
        url: '/settings/appearance',
        icon: null,
    },
];

export default function SettingsLayout({
    children,
    wide = false,
}: {
    children: React.ReactNode;
    wide?: boolean;
}) {
    const page = usePage<SharedData>();
    const isAdmin = page.props.auth?.user?.role === 'admin';
    const currentPath = page.url.split('?')[0];

    const sidebarNavItems: NavItem[] = isAdmin
        ? [
              ...baseNavItems,
              {
                  title: 'Crew & Accounts',
                  url: '/admin/employees',
                  icon: null,
              },
          ]
        : baseNavItems;

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description={
                    isAdmin
                        ? 'Manage your profile, security, appearance, and crew accounts'
                        : 'Manage your profile and account settings'
                }
            />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className={cn('flex-1', wide ? 'max-w-5xl' : 'md:max-w-2xl')}>
                    <section className={cn('space-y-12', wide ? 'max-w-5xl' : 'max-w-xl')}>{children}</section>
                </div>
            </div>
        </div>
    );
}
