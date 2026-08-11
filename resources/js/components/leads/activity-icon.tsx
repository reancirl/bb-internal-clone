import { Phone, Mail, Calendar, FileText, MessageSquare } from 'lucide-react';
import { type LeadActivity } from '@/types/crm';
import { cn } from '@/lib/utils';

const iconMap = {
  call: Phone,
  email: Mail,
  meeting: Calendar,
  note: FileText,
  sms: MessageSquare,
};

const colorMap = {
  call: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
  email: 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300',
  meeting: 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300',
  note: 'bg-muted text-muted-foreground',
  sms: 'bg-green-100 text-green-700 dark:bg-green-950/60 dark:text-green-300',
};

interface ActivityIconProps {
  type: LeadActivity['activity_type'];
  className?: string;
}

export function ActivityIcon({ type, className }: ActivityIconProps) {
  const Icon = iconMap[type];

  return (
    <div className={cn('flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full', colorMap[type], className)}>
      <Icon className="h-5 w-5" />
    </div>
  );
}
