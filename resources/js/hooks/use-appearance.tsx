import { useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyTheme = (appearance: Appearance) => {
    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', isDark);
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

let transitionTimeout: number | undefined;

const runThemeTransition = (apply: () => void) => {
    const root = document.documentElement;
    root.classList.add('theme-transitioning');
    apply();

    if (transitionTimeout !== undefined) {
        window.clearTimeout(transitionTimeout);
    }
    transitionTimeout = window.setTimeout(() => {
        root.classList.remove('theme-transitioning');
    }, 350);
};

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    const updateAppearance = (mode: Appearance, animate = true) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        if (animate) {
            runThemeTransition(() => applyTheme(mode));
        } else {
            applyTheme(mode);
        }
    };

    useEffect(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
        updateAppearance(savedAppearance || 'system', false);

        return () => mediaQuery.removeEventListener('change', handleSystemThemeChange);
    }, []);

    return { appearance, updateAppearance };
}
