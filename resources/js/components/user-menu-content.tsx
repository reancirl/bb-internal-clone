import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { LogOut, Monitor, Moon, Settings, Sun } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

const appearanceOptions: { value: Appearance; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'Auto', icon: Monitor },
];

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link className="block w-full" href={route('profile.edit')} as="button" prefetch onClick={cleanup}>
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <div className="px-2 py-1.5">
                <div className="text-muted-foreground mb-1.5 text-xs font-medium">Appearance</div>
                <div className="bg-muted relative grid grid-cols-3 rounded-md p-0.5">
                    {/* Sliding active pill indicator */}
                    <span
                        aria-hidden="true"
                        className={cn(
                            'bg-background pointer-events-none absolute inset-y-0.5 left-0.5 w-[calc((100%-0.25rem)/3)] rounded-sm shadow-xs transition-transform duration-300 ease-out',
                            appearance === 'light' && 'translate-x-0',
                            appearance === 'dark' && 'translate-x-full',
                            appearance === 'system' && 'translate-x-[200%]',
                        )}
                    />
                    {appearanceOptions.map(({ value, label, icon: Icon }) => {
                        const isActive = appearance === value;
                        return (
                            <button
                                key={value}
                                type="button"
                                onClick={() => updateAppearance(value)}
                                className={cn(
                                    'relative z-10 flex items-center justify-center gap-1 rounded-sm py-1 text-xs font-medium transition-colors duration-200',
                                    isActive
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Icon
                                    className={cn(
                                        'h-3.5 w-3.5 transition-transform duration-300',
                                        isActive && value === 'light' && 'scale-110 rotate-45',
                                        isActive && value === 'dark' && 'scale-110 -rotate-12',
                                        isActive && value === 'system' && 'scale-110',
                                    )}
                                />
                                <span>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link className="block w-full" method="post" href={route('logout')} as="button" onClick={cleanup}>
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
