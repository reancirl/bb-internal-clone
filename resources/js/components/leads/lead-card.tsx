import { Lead } from '@/types/crm';
import { PriorityBadge } from './priority-badge';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { cn } from '@/lib/utils';
import { Mail, Phone, MapPin, DollarSign, Calendar, User } from 'lucide-react';
import { format, isPast, parseISO } from 'date-fns';
import { Link } from '@inertiajs/react';

interface LeadCardProps {
  lead: Lead;
  users: { id: number; name: string }[];
  /** When provided, clicking the card calls this instead of navigating to the detail page. */
  onClick?: () => void;
}

export function LeadCard({ lead, users, onClick }: LeadCardProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: lead.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  const assignedUser = users.find((u) => u.id === lead.assigned_to_user_id);
  const isOverdue =
    lead.next_follow_up_date && isPast(parseISO(lead.next_follow_up_date));

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      className={cn(
        'group bg-card text-card-foreground hover:border-primary/50 hover:bg-accent/50 relative cursor-move rounded-lg border p-3 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md',
        isDragging && 'opacity-50 shadow-lg',
        isOverdue && 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
      )}
    >
      {onClick ? (
        <button
          type="button"
          aria-label={`Edit ${lead.first_name} ${lead.last_name}`}
          className="absolute inset-0 z-10 cursor-pointer"
          onClick={(e) => {
            e.stopPropagation();
            onClick();
          }}
        />
      ) : (
        <Link
          href={`/admin/leads/${lead.id}`}
          className="absolute inset-0 z-10"
          onClick={(e) => e.stopPropagation()}
        />
      )}

      <div className="space-y-2">
        {/* Header */}
        <div className="flex items-start justify-between gap-2">
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-sm truncate">
              {lead.first_name} {lead.last_name}
            </h3>
            <PriorityBadge priority={lead.priority} className="mt-1" />
          </div>
        </div>

        {/* Contact Info */}
        <div className="text-muted-foreground space-y-1 text-xs">
          <div className="flex items-center gap-1.5 truncate">
            <Mail className="h-3 w-3 flex-shrink-0" />
            <span className="truncate">{lead.email}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Phone className="h-3 w-3 flex-shrink-0" />
            <span>{lead.phone}</span>
          </div>
          <div className="flex items-center gap-1.5 truncate">
            <MapPin className="h-3 w-3 flex-shrink-0" />
            <span className="truncate">{lead.build_location}</span>
          </div>
        </div>

        {/* Value */}
        {lead.estimated_value_cents && (
          <div className="flex items-center gap-1.5 text-sm font-bold text-green-700 dark:text-green-400">
            <DollarSign className="h-4 w-4" />
            ${(lead.estimated_value_cents / 100).toLocaleString()}
          </div>
        )}

        {/* Follow-up */}
        {lead.next_follow_up_date && (
          <div
            className={cn(
              'flex items-center gap-1.5 text-xs',
              isOverdue ? 'font-bold text-red-600 dark:text-red-400' : 'text-muted-foreground',
            )}
          >
            <Calendar className="h-3 w-3" />
            {isOverdue ? 'Overdue: ' : 'Follow-up: '}
            {format(parseISO(lead.next_follow_up_date), 'MMM d')}
          </div>
        )}

        {/* Assigned User */}
        {assignedUser && (
          <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
            <User className="h-3 w-3" />
            {assignedUser.name}
          </div>
        )}
      </div>
    </div>
  );
}
