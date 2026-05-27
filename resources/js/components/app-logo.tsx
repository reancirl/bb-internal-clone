import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="bg-primary flex aspect-square h-9 shrink-0 items-center justify-center rounded-md">
                <AppLogoIcon className="h-7 w-auto" />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">BuffaloBuilt</span>
                <span className="text-muted-foreground truncate text-xs">Internal</span>
            </div>
        </>
    );
}
