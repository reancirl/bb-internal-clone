import { LeadActivity } from '@/types/crm';
import { ActivityIcon } from './activity-icon';
import { FileText, Clock } from 'lucide-react';
import { formatDistanceToNow, parseISO } from 'date-fns';

interface ActivityTimelineProps {
  activities: LeadActivity[];
}

export function ActivityTimeline({ activities }: ActivityTimelineProps) {
  if (activities.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
        <FileText className="h-12 w-12 mb-3 text-muted-foreground/40" />
        <p className="text-sm font-medium">No activities yet</p>
        <p className="text-xs">Log your first activity to start tracking this lead</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {activities.map((activity, index) => {
        const isCompleted = !!activity.completed_at;
        const isScheduled = !!activity.scheduled_at && !activity.completed_at;

        return (
          <div key={activity.id} className="relative flex gap-3">
            {/* Timeline line */}
            {index < activities.length - 1 && (
              <div className="absolute left-5 top-10 h-full w-0.5 bg-border" />
            )}

            {/* Icon */}
            <ActivityIcon type={activity.activity_type} />

            {/* Content */}
            <div className="flex-1 space-y-1 pt-1">
              <div className="flex items-start justify-between gap-2">
                <h4 className="text-sm font-medium text-foreground">{activity.title}</h4>
                <span className="text-xs text-muted-foreground whitespace-nowrap">
                  {formatDistanceToNow(parseISO(activity.created_at), { addSuffix: true })}
                </span>
              </div>

              {activity.description && (
                <p className="text-sm text-muted-foreground">{activity.description}</p>
              )}

              <div className="flex items-center gap-3 text-xs text-muted-foreground">
                <span>by {activity.created_by.name}</span>

                {isScheduled && (
                  <span className="flex items-center gap-1 text-orange-600 dark:text-orange-400">
                    <Clock className="h-3 w-3" />
                    Scheduled
                  </span>
                )}

                {isCompleted && (
                  <span className="text-green-600 dark:text-green-400">Completed</span>
                )}
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
