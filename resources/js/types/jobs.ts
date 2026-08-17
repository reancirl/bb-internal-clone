export type JobStatus = 'scheduled' | 'in_progress' | 'done' | 'canceled';

export interface JobCrew {
    id: number;
    name: string;
}

export interface JobRow {
    id: number;
    project_id: number;
    project_name: string | null;
    predecessor_job_id: number | null;
    title: string | null;
    scheduled_date: string; // YYYY-MM-DD
    duration_days: number;
    end_date: string; // YYYY-MM-DD, scheduled_date + duration_days - 1
    status: JobStatus;
    trade: string | null;
    notes: string | null;
    has_successors: boolean;
    crew: JobCrew[];
}

// Lean job list for the predecessor picker (all non-canceled jobs).
export interface JobOption {
    id: number;
    project_id: number;
    title: string | null;
    scheduled_date: string;
    end_date: string;
    status: JobStatus;
}

// One row of the shift-preview response for the confirm dialog.
export interface ShiftPreviewRow {
    id: number;
    title: string | null;
    old_date: string;
    new_date: string;
    status: JobStatus;
}

export interface ProjectOption {
    id: number;
    name: string;
}

export interface CrewOption {
    id: number;
    name: string;
    role: string;
}

export const JOB_STATUS_LABEL: Record<JobStatus, string> = {
    scheduled: 'Scheduled',
    in_progress: 'In progress',
    done: 'Done',
    canceled: 'Canceled',
};

export const JOB_STATUS_VARIANT: Record<JobStatus, 'outline' | 'default' | 'secondary' | 'destructive'> = {
    scheduled: 'outline',
    in_progress: 'default',
    done: 'secondary',
    canceled: 'destructive',
};

// Tailwind classes for the small status dot shown on calendar chips.
export const JOB_STATUS_DOT: Record<JobStatus, string> = {
    scheduled: 'bg-muted-foreground',
    in_progress: 'bg-blue-500',
    done: 'bg-green-500',
    canceled: 'bg-red-500',
};
