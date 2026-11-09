import { type MouseEvent, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const isDarkForAppearance = (appearance: Appearance) =>
    appearance === 'dark' || (appearance === 'system' && prefersDark());

const applyTheme = (appearance: Appearance) => {
    document.documentElement.classList.toggle('dark', isDarkForAppearance(appearance));
};

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    const currentAppearance = localStorage.getItem('appearance') as Appearance;
    applyTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    const savedAppearance = (localStorage.getItem('appearance') as Appearance) || 'system';

    applyTheme(savedAppearance);

    // Add the event listener for system theme changes...
    mediaQuery.addEventListener('change', handleSystemThemeChange);
}

type ViewTransitionDoc = Document & {
    startViewTransition?: (callback: () => void) => {
        ready: Promise<void>;
    };
};

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    const updateAppearance = (mode: Appearance, event?: MouseEvent<HTMLElement>) => {
        const wasDark = document.documentElement.classList.contains('dark');
        const willBeDark = isDarkForAppearance(mode);

        setAppearance(mode);
        localStorage.setItem('appearance', mode);

        // If the effective light/dark visual state isn't changing, or no click event was passed, apply immediately
        const doc = document as ViewTransitionDoc;
        if (wasDark === willBeDark || !event || typeof doc.startViewTransition !== 'function') {
            applyTheme(mode);
            return;
        }

        const x = event.clientX;
        const y = event.clientY;
        const endRadius = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y),
        );

        const transition = doc.startViewTransition(() => {
            applyTheme(mode);
        });

        transition.ready.then(() => {
            document.documentElement.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${endRadius}px at ${x}px ${y}px)`,
                    ],
                },
                {
                    duration: 650,
                    easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                    pseudoElement: '::view-transition-new(root)',
                },
            );
        });
    };

    useEffect(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
        const initial = savedAppearance || 'system';
        setAppearance(initial);
        applyTheme(initial);

        return () => mediaQuery.removeEventListener('change', handleSystemThemeChange);
    }, []);

    return { appearance, updateAppearance };
}
