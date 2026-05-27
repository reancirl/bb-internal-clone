export interface TimeCardSummary {
    id: number;
    user_id?: number;
    clock_in_at: string | null;
    clock_out_at: string | null;
    duration_minutes: number | null;
    notes: string | null;
}

export interface EmployeeOption {
    id: number;
    name: string;
    role: 'admin' | 'crew';
}

export function formatDuration(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) return '—';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m}m`;
    return `${h}h ${m}m`;
}

export function formatDateTime(value: string | null): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
