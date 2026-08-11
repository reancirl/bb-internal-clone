import { useState } from 'react';
import { LeadActivity } from '@/types/crm';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Phone, Mail, Calendar, FileText, MessageSquare } from 'lucide-react';

interface QuickActionModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (activity: Omit<LeadActivity, 'id' | 'lead_id' | 'created_by' | 'created_at'>) => void;
  leadName?: string;
}

const activityTypes = [
  { value: 'call', label: 'Phone Call', icon: Phone },
  { value: 'email', label: 'Email', icon: Mail },
  { value: 'meeting', label: 'Meeting', icon: Calendar },
  { value: 'note', label: 'Note', icon: FileText },
  { value: 'sms', label: 'SMS', icon: MessageSquare },
] as const;

export function QuickActionModal({
  open,
  onOpenChange,
  onSave,
  leadName,
}: QuickActionModalProps) {
  const [activityType, setActivityType] = useState<LeadActivity['activity_type']>('call');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');

  const handleSave = () => {
    if (!title.trim()) return;

    onSave({
      activity_type: activityType,
      title: title.trim(),
      description: description.trim(),
      scheduled_at: null,
      completed_at: new Date().toISOString(),
    });

    // Reset form
    setTitle('');
    setDescription('');
    setActivityType('call');
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[525px]">
        <DialogHeader>
          <DialogTitle>Log Activity</DialogTitle>
          <DialogDescription>
            {leadName ? `Add an activity for ${leadName}` : 'Add a new activity'}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="activity-type">Activity Type</Label>
            <Select
              value={activityType}
              onValueChange={(value) => setActivityType(value as LeadActivity['activity_type'])}
            >
              <SelectTrigger id="activity-type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {activityTypes.map((type) => {
                  const Icon = type.icon;
                  return (
                    <SelectItem key={type.value} value={type.value}>
                      <div className="flex items-center gap-2">
                        <Icon className="h-4 w-4" />
                        {type.label}
                      </div>
                    </SelectItem>
                  );
                })}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="title">Title *</Label>
            <Input
              id="title"
              placeholder="e.g., Spoke with customer about timeline"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              autoFocus
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description (optional)</Label>
            <Textarea
              id="description"
              placeholder="Add more details about this activity..."
              rows={4}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={!title.trim()}>
            Save Activity
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
