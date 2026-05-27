import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatElapsed(startIso: string, now: number): string {
    const startMs = new Date(startIso).getTime();
    if (Number.isNaN(startMs)) return '';
    const totalSeconds = Math.max(0, Math.floor((now - startMs) / 1000));
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${h}:${pad(m)}:${pad(s)}`;
}

export function ClockInOutButton() {
    const { auth, openTimeCard } = usePage<SharedData>().props;
    const [now, setNow] = useState(() => Date.now());
    const clockInAt = openTimeCard?.clock_in_at ?? null;

    useEffect(() => {
        if (!clockInAt) return;
        setNow(Date.now());
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, [clockInAt]);

    if (!auth?.user) return null;

    const clockIn = () => {
        router.post('/time-card/clock-in', {}, { preserveScroll: true, preserveState: false });
    };

    const clockOut = () => {
        router.post('/time-card/clock-out', {}, { preserveScroll: true, preserveState: false });
    };

    if (clockInAt) {
        const elapsed = formatElapsed(clockInAt, now);
        return (
            <Button onClick={clockOut} variant="default" size="sm" className="gap-2">
                <LogOut className="h-4 w-4" />
                <span className="hidden sm:inline">Clock Out · </span>
                <span className="font-mono tabular-nums" aria-live="polite">
                    {elapsed}
                </span>
            </Button>
        );
    }

    return (
        <Button onClick={clockIn} variant="default" size="sm" className="gap-2">
            <LogIn className="h-4 w-4" />
            <span className="hidden sm:inline">Clock In</span>
        </Button>
    );
}
