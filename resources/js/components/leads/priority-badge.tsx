import { Lead } from '@/types/crm';
import { cn } from '@/lib/utils';
import { AlertCircle, MinusCircle, ArrowUp } from 'lucide-react';

interface PriorityBadgeProps {
  priority: Lead['priority'];
  className?: string;
  showIcon?: boolean;
}

const priorityConfig = {
  low: {
    label: 'Low',
    color: 'bg-muted text-muted-foreground border-border',
    icon: MinusCircle,
  },
  medium: {
    label: 'Medium',
    color: 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-900',
    icon: AlertCircle,
  },
  high: {
    label: 'High',
    color: 'bg-red-100 text-red-700 border-red-300 dark:bg-red-950/60 dark:text-red-300 dark:border-red-900',
    icon: ArrowUp,
  },
};

export function PriorityBadge({ priority, className, showIcon = true }: PriorityBadgeProps) {
  const config = priorityConfig[priority];
  const Icon = config.icon;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium',
        config.color,
        className,
      )}
    >
      {showIcon && <Icon className="h-3 w-3" />}
      {config.label}
    </span>
  );
}
