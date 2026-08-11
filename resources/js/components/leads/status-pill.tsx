import { Lead } from '@/types/crm';
import { cn } from '@/lib/utils';

interface StatusPillProps {
  status: Lead['status'];
  className?: string;
}

const statusConfig = {
  new: { label: 'New', color: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-900' },
  contacted: { label: 'Contacted', color: 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-900' },
  qualified: { label: 'Qualified', color: 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-950/60 dark:text-yellow-300 dark:border-yellow-900' },
  meeting_scheduled: { label: 'Meeting Scheduled', color: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-950/60 dark:text-orange-300 dark:border-orange-900' },
  proposal_sent: { label: 'Proposal Sent', color: 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-900' },
  won: { label: 'Won', color: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-950/60 dark:text-green-300 dark:border-green-900' },
  lost: { label: 'Lost', color: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border-red-900' },
};

export function StatusPill({ status, className }: StatusPillProps) {
  const config = statusConfig[status];

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
        config.color,
        className,
      )}
    >
      {config.label}
    </span>
  );
}
