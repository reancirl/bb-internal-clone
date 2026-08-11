import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { NewLead, type CrmUser, type Lead } from '@/types/crm';
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
import { ExternalLink, Trash2 } from 'lucide-react';

interface LeadFormModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (lead: NewLead) => void;
  users: CrmUser[];
  saving?: boolean;
  /** When set, the modal edits this lead instead of creating a new one. */
  lead?: Lead | null;
  /** Edit mode only: called after the inline confirmation step. */
  onDelete?: () => void;
}

const leadSources = ['website', 'referral', 'social_media', 'email_campaign', 'trade_show', 'other'];

const emptyForm = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  build_location: '',
  project_details: '',
  priority: 'medium' as Lead['priority'],
  source: 'website',
  estimated_value_dollars: '',
  next_follow_up_date: '',
  assigned_to_user_id: '',
};

function formFromLead(lead: Lead): typeof emptyForm {
  return {
    first_name: lead.first_name,
    last_name: lead.last_name,
    email: lead.email,
    phone: lead.phone,
    build_location: lead.build_location ?? '',
    project_details: lead.project_details ?? '',
    priority: lead.priority,
    source: lead.source || 'website',
    estimated_value_dollars:
      lead.estimated_value_cents !== null ? (lead.estimated_value_cents / 100).toString() : '',
    next_follow_up_date: lead.next_follow_up_date ?? '',
    assigned_to_user_id: lead.assigned_to_user_id?.toString() ?? '',
  };
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="text-destructive text-xs">{message}</p>;
}

export function LeadFormModal({
  open,
  onOpenChange,
  onSave,
  users,
  saving = false,
  lead = null,
  onDelete,
}: LeadFormModalProps) {
  const { errors } = usePage().props;
  const isEdit = lead !== null;
  const [form, setForm] = useState(emptyForm);
  const [confirmingDelete, setConfirmingDelete] = useState(false);

  const set = (key: keyof typeof form, value: string) => setForm((prev) => ({ ...prev, [key]: value }));

  // Populate from the lead being edited (or blank for create) each time the
  // dialog opens; reset when it closes so stale values never flash.
  useEffect(() => {
    setForm(open && lead ? formFromLead(lead) : emptyForm);
    setConfirmingDelete(false);
  }, [open, lead]);

  const handleSave = () => {
    if (!form.first_name.trim() || !form.last_name.trim() || !form.email.trim() || !form.phone.trim()) return;

    onSave({
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      email: form.email.trim(),
      phone: form.phone.trim(),
      // null (not undefined) so edits can clear a field — Inertia drops
      // undefined keys and the server would keep the old value.
      build_location: form.build_location.trim() || null,
      project_details: form.project_details.trim() || null,
      priority: form.priority,
      source: form.source || 'website',
      estimated_value_cents: form.estimated_value_dollars
        ? Math.round(parseFloat(form.estimated_value_dollars) * 100)
        : null,
      next_follow_up_date: form.next_follow_up_date || null,
      assigned_to_user_id:
        form.assigned_to_user_id && form.assigned_to_user_id !== 'none'
          ? parseInt(form.assigned_to_user_id)
          : null,
    });
    // The parent closes the dialog on success; on validation errors it stays
    // open with the typed values and the field errors below.
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Lead' : 'New Lead'}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? `Update ${lead.first_name} ${lead.last_name}'s details.`
              : 'Add a lead to the sales pipeline.'}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="first_name">First Name *</Label>
              <Input
                id="first_name"
                placeholder="John"
                value={form.first_name}
                onChange={(e) => set('first_name', e.target.value)}
                autoFocus
              />
              <FieldError message={errors.first_name} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="last_name">Last Name *</Label>
              <Input
                id="last_name"
                placeholder="Doe"
                value={form.last_name}
                onChange={(e) => set('last_name', e.target.value)}
              />
              <FieldError message={errors.last_name} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="email">Email *</Label>
              <Input
                id="email"
                type="email"
                placeholder="john.doe@example.com"
                value={form.email}
                onChange={(e) => set('email', e.target.value)}
              />
              <FieldError message={errors.email} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="phone">Phone *</Label>
              <Input
                id="phone"
                placeholder="(555) 123-4567"
                value={form.phone}
                onChange={(e) => set('phone', e.target.value)}
              />
              <FieldError message={errors.phone} />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="build_location">Build Location</Label>
            <Input
              id="build_location"
              placeholder="123 Main St, Buffalo, NY 14201"
              value={form.build_location}
              onChange={(e) => set('build_location', e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="project_details">Project Details</Label>
            <Textarea
              id="project_details"
              placeholder="Describe the project scope..."
              rows={3}
              value={form.project_details}
              onChange={(e) => set('project_details', e.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="estimated_value">Estimated Value ($)</Label>
              <Input
                id="estimated_value"
                type="number"
                min="0"
                step="100"
                placeholder="50000"
                value={form.estimated_value_dollars}
                onChange={(e) => set('estimated_value_dollars', e.target.value)}
              />
              <FieldError message={errors.estimated_value_cents} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="priority">Priority</Label>
              <Select value={form.priority} onValueChange={(v) => set('priority', v)}>
                <SelectTrigger id="priority">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="high">High</SelectItem>
                  <SelectItem value="medium">Medium</SelectItem>
                  <SelectItem value="low">Low</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="source">Lead Source</Label>
              <Select value={form.source} onValueChange={(v) => set('source', v)}>
                <SelectTrigger id="source">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {leadSources.map((source) => (
                    <SelectItem key={source} value={source}>
                      {source.replace('_', ' ').replace(/^\w/, (c) => c.toUpperCase())}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="assigned_to">Assigned To</Label>
              <Select
                value={form.assigned_to_user_id}
                onValueChange={(v) => set('assigned_to_user_id', v)}
              >
                <SelectTrigger id="assigned_to">
                  <SelectValue placeholder="Unassigned" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Unassigned</SelectItem>
                  {users.map((user) => (
                    <SelectItem key={user.id} value={user.id.toString()}>
                      {user.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="next_follow_up">Next Follow-up</Label>
            <Input
              id="next_follow_up"
              type="date"
              value={form.next_follow_up_date}
              onChange={(e) => set('next_follow_up_date', e.target.value)}
            />
            <FieldError message={errors.next_follow_up_date} />
          </div>
        </div>

        <DialogFooter className={isEdit ? 'sm:justify-between' : undefined}>
          {isEdit && !confirmingDelete && (
            <div className="flex gap-1">
              <Button variant="ghost" asChild>
                <Link href={`/admin/leads/${lead.id}`}>
                  <ExternalLink className="mr-2 h-4 w-4" />
                  Full details
                </Link>
              </Button>
              {onDelete && (
                <Button
                  variant="ghost"
                  className="text-destructive hover:text-destructive"
                  onClick={() => setConfirmingDelete(true)}
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  Delete
                </Button>
              )}
            </div>
          )}

          {isEdit && confirmingDelete ? (
            <div className="flex w-full items-center justify-between gap-2">
              <span className="text-destructive text-sm font-medium">
                Delete this lead and its activity history?
              </span>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setConfirmingDelete(false)}>
                  Keep
                </Button>
                <Button variant="destructive" onClick={onDelete} disabled={saving}>
                  {saving ? 'Deleting…' : 'Yes, delete'}
                </Button>
              </div>
            </div>
          ) : (
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button
                onClick={handleSave}
                disabled={
                  saving ||
                  !form.first_name.trim() ||
                  !form.last_name.trim() ||
                  !form.email.trim() ||
                  !form.phone.trim()
                }
              >
                {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Lead'}
              </Button>
            </div>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
