import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Lead, NewLead, CrmUser } from '@/types/crm';
import { LeadCard } from '@/components/leads/lead-card';
import { LeadFormModal } from '@/components/leads/lead-form-modal';
import {
  DndContext,
  DragEndEvent,
  DragOverEvent,
  DragOverlay,
  DragStartEvent,
  PointerSensor,
  closestCorners,
  useDroppable,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Search, Plus, BarChart3 } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

const STATUSES: Lead['status'][] = [
  'new',
  'contacted',
  'qualified',
  'meeting_scheduled',
  'proposal_sent',
  'won',
  'lost',
];

const STATUS_LABELS = {
  new: 'New',
  contacted: 'Contacted',
  qualified: 'Qualified',
  meeting_scheduled: 'Meeting Scheduled',
  proposal_sent: 'Proposal Sent',
  won: 'Won',
  lost: 'Lost',
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'CRM Pipeline', href: '/admin/leads' },
];

interface LeadsIndexProps {
  leads: Lead[];
  users: CrmUser[];
}

export default function LeadsIndex({ leads: serverLeads, users }: LeadsIndexProps) {
  // Local copy so drag-and-drop updates instantly; re-synced whenever the
  // server sends fresh props (after a patch/post round-trip).
  const [leads, setLeads] = useState<Lead[]>(serverLeads);
  useEffect(() => setLeads(serverLeads), [serverLeads]);

  const [search, setSearch] = useState('');
  const [priorityFilter, setPriorityFilter] = useState<string>('all');
  const [assignedFilter, setAssignedFilter] = useState<string>('all');
  const [activeDragId, setActiveDragId] = useState<number | null>(null);
  const [dropTarget, setDropTarget] = useState<Lead['status'] | null>(null);
  const [newLeadOpen, setNewLeadOpen] = useState(false);
  const [editLead, setEditLead] = useState<Lead | null>(null);
  const [pendingLost, setPendingLost] = useState<Lead | null>(null);
  const [savingLead, setSavingLead] = useState(false);

  // Require 8px of movement before a drag starts, so plain clicks reach the
  // card (open the edit modal) instead of being captured by the drag sensor.
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
  );

  const clearFilters = () => {
    setSearch('');
    setPriorityFilter('all');
    setAssignedFilter('all');
  };

  const filteredLeads = leads.filter((lead) => {
    if (priorityFilter !== 'all' && lead.priority !== priorityFilter) return false;
    if (assignedFilter === 'unassigned' && lead.assigned_to_user_id !== null) return false;
    if (
      assignedFilter !== 'all' &&
      assignedFilter !== 'unassigned' &&
      lead.assigned_to_user_id !== parseInt(assignedFilter)
    )
      return false;
    if (search) {
      const searchLower = search.toLowerCase();
      return (
        lead.first_name.toLowerCase().includes(searchLower) ||
        lead.last_name.toLowerCase().includes(searchLower) ||
        lead.email.toLowerCase().includes(searchLower) ||
        lead.phone.includes(search)
      );
    }
    return true;
  });

  // Group by status
  const leadsByStatus = STATUSES.reduce(
    (acc, status) => {
      acc[status] = filteredLeads.filter((lead) => lead.status === status);
      return acc;
    },
    {} as Record<Lead['status'], Lead[]>,
  );

  // Success toasts come from the server flash via <FlashToaster />; only
  // failures are toasted manually here.
  const updateLeadStatus = (
    leadId: number,
    newStatus: Lead['status'],
    extra: Record<string, string | null> = {},
  ) => {
    const previous = leads;
    setLeads((prev) =>
      prev.map((l) => (l.id === leadId ? { ...l, status: newStatus } : l)),
    );

    router.patch(
      `/admin/leads/${leadId}`,
      { status: newStatus, ...extra },
      {
        preserveScroll: true,
        onError: () => {
          setLeads(previous);
          toast.error('Could not update lead status.');
        },
      },
    );
  };

  // Create when no lead is being edited, update otherwise. The modal closes
  // only once the server confirms — on validation errors it stays open with
  // the typed values and shows the field errors.
  const saveLead = (data: NewLead) => {
    const options = {
      preserveScroll: true,
      onStart: () => setSavingLead(true),
      onFinish: () => setSavingLead(false),
      onSuccess: () => {
        setNewLeadOpen(false);
        setEditLead(null);
      },
    };

    if (editLead) {
      router.patch(`/admin/leads/${editLead.id}`, data as Record<string, any>, options);
    } else {
      router.post('/admin/leads', data as Record<string, any>, options);
    }
  };

  const deleteLead = () => {
    if (!editLead) return;
    router.delete(`/admin/leads/${editLead.id}`, {
      preserveScroll: true,
      onStart: () => setSavingLead(true),
      onFinish: () => setSavingLead(false),
      onSuccess: () => setEditLead(null),
      onError: () => toast.error('Could not delete lead.'),
    });
  };

  // Dropping over a card yields the card's id; over a column yields the status.
  const resolveTargetStatus = (overId: string | number | null): Lead['status'] | null => {
    if (overId === null) return null;
    const overLead = leads.find((l) => l.id === overId);
    const status = (overLead ? overLead.status : overId) as Lead['status'];
    return STATUSES.includes(status) ? status : null;
  };

  const handleDragStart = (event: DragStartEvent) => {
    setActiveDragId(event.active.id as number);
  };

  const handleDragOver = (event: DragOverEvent) => {
    setDropTarget(resolveTargetStatus(event.over?.id ?? null));
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    setActiveDragId(null);
    setDropTarget(null);

    const newStatus = resolveTargetStatus(over?.id ?? null);
    if (!newStatus) return;

    const leadId = active.id as number;
    const lead = leads.find((l) => l.id === leadId);
    if (!lead) return;

    // Don't update if dropping in same status
    if (lead.status === newStatus) return;

    // Moving to Lost asks for a reason first; the card only moves on confirm.
    if (newStatus === 'lost') {
      setPendingLost(lead);
      return;
    }

    updateLeadStatus(leadId, newStatus);
  };

  const handleDragCancel = () => {
    setActiveDragId(null);
    setDropTarget(null);
  };

  const activeLead = leads.find((lead) => lead.id === activeDragId);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="CRM Pipeline" />

      <div className="flex flex-col gap-4 overflow-hidden p-4 md:h-[calc(100svh-80px)] md:max-h-[calc(100svh-80px)] md:shrink-0 md:p-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-foreground text-2xl font-semibold">CRM Pipeline</h1>
            <p className="text-muted-foreground text-sm">
              Kanban board for tracking leads through the sales pipeline.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" asChild>
              <Link href="/admin/leads/analytics" prefetch>
                <BarChart3 className="mr-2 h-4 w-4" />
                Analytics
              </Link>
            </Button>
            <Button onClick={() => setNewLeadOpen(true)}>
              <Plus className="mr-2 h-4 w-4" />
              New Lead
            </Button>
          </div>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3">
          <div className="relative flex-1 min-w-[200px] max-w-sm">
            <Search className="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
            <Input
              placeholder="Search leads..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>

          <Select value={priorityFilter} onValueChange={setPriorityFilter}>
            <SelectTrigger className="w-[150px]">
              <SelectValue placeholder="Priority" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Priorities</SelectItem>
              <SelectItem value="high">High</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="low">Low</SelectItem>
            </SelectContent>
          </Select>

          <Select value={assignedFilter} onValueChange={setAssignedFilter}>
            <SelectTrigger className="w-[180px]">
              <SelectValue placeholder="Assigned to" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Users</SelectItem>
              <SelectItem value="unassigned">Unassigned</SelectItem>
              {users.map((user) => (
                <SelectItem key={user.id} value={user.id.toString()}>
                  {user.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Kanban Board */}
        {filteredLeads.length === 0 ? (
          <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-8 text-center">
            <Search className="text-muted-foreground/40 h-10 w-10" />
            <div>
              <p className="text-foreground font-medium">
                {leads.length === 0 ? 'No leads yet' : 'No leads match your filters'}
              </p>
              <p className="text-muted-foreground text-sm">
                {leads.length === 0
                  ? 'Create your first lead to start the pipeline.'
                  : 'Try adjusting your search or filter criteria.'}
              </p>
            </div>
            {leads.length === 0 ? (
              <Button onClick={() => setNewLeadOpen(true)}>
                <Plus className="mr-2 h-4 w-4" />
                New Lead
              </Button>
            ) : (
              <Button variant="outline" onClick={clearFilters}>
                Clear filters
              </Button>
            )}
          </div>
        ) : (
          <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={handleDragStart}
            onDragOver={handleDragOver}
            onDragEnd={handleDragEnd}
            onDragCancel={handleDragCancel}
          >
            <div className="grid min-h-0 flex-1 grid-cols-1 gap-4 md:grid-cols-2 md:auto-rows-[minmax(0,1fr)] lg:grid-cols-4 xl:grid-cols-7">
              {STATUSES.map((status) => (
                <KanbanColumn
                  key={status}
                  status={status}
                  leads={leadsByStatus[status]}
                  users={users}
                  isDropTarget={activeDragId !== null && dropTarget === status}
                  onLeadClick={setEditLead}
                />
              ))}
            </div>

            <DragOverlay>
              {activeLead && <LeadCard lead={activeLead} users={users} />}
            </DragOverlay>
          </DndContext>
        )}

        {/* Create / Edit Lead Modal */}
        <LeadFormModal
          open={newLeadOpen || editLead !== null}
          onOpenChange={(open) => {
            if (!open) {
              setNewLeadOpen(false);
              setEditLead(null);
            }
          }}
          users={users}
          onSave={saveLead}
          saving={savingLead}
          lead={editLead}
          onDelete={deleteLead}
        />

        {/* Lost Reason Dialog */}
        <LostReasonDialog
          lead={pendingLost}
          onCancel={() => setPendingLost(null)}
          onConfirm={(reason) => {
            if (pendingLost) {
              updateLeadStatus(pendingLost.id, 'lost', { lost_reason: reason || null });
            }
            setPendingLost(null);
          }}
        />
      </div>
    </AppLayout>
  );
}

function LostReasonDialog({
  lead,
  onCancel,
  onConfirm,
}: {
  lead: Lead | null;
  onCancel: () => void;
  onConfirm: (reason: string) => void;
}) {
  const [reason, setReason] = useState('');

  // Fresh textarea every time the dialog opens for a new lead.
  useEffect(() => setReason(''), [lead]);

  return (
    <Dialog open={lead !== null} onOpenChange={(open) => !open && onCancel()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Mark lead as Lost</DialogTitle>
          <DialogDescription>
            {lead
              ? `Why was ${lead.first_name} ${lead.last_name}'s project lost? This helps spot patterns later.`
              : ''}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-2 py-2">
          <Label htmlFor="lost_reason">Reason (optional)</Label>
          <Textarea
            id="lost_reason"
            placeholder="e.g. Went with competitor — price too high"
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            autoFocus
          />
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={() => onConfirm(reason.trim())}>
            Mark as Lost
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function KanbanColumn({
  status,
  leads,
  users,
  isDropTarget,
  onLeadClick,
}: {
  status: Lead['status'];
  leads: Lead[];
  users: CrmUser[];
  isDropTarget: boolean;
  onLeadClick: (lead: Lead) => void;
}) {
  // Registers the whole column as a drop zone so leads can be dropped on
  // empty columns (cards only cover the space they occupy).
  const { setNodeRef } = useDroppable({ id: status });

  return (
    <div
      ref={setNodeRef}
      className={cn(
        'bg-muted/50 flex flex-col rounded-lg border p-3 transition-all md:h-full md:min-h-0 md:overflow-hidden',
        status === 'won' &&
          'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30',
        status === 'lost' &&
          'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30',
        isDropTarget && 'ring-primary/60 bg-accent border-primary/40 ring-2',
      )}
    >
      {/* Column Header */}
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-foreground text-sm font-semibold">{STATUS_LABELS[status]}</h3>
        <span className="bg-background text-muted-foreground flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium">
          {leads.length}
        </span>
      </div>

      {/* Droppable Area */}
      <SortableContext
        id={status}
        items={leads.map((l) => l.id)}
        strategy={verticalListSortingStrategy}
      >
        <div className="min-h-[200px] space-y-3 md:min-h-0 md:flex-1 md:overflow-y-auto">
          {leads.length === 0 && (
            <p
              className={cn(
                'rounded-md border border-dashed border-transparent p-2 text-xs transition-colors',
                isDropTarget
                  ? 'border-primary/40 text-primary'
                  : 'text-muted-foreground/70',
              )}
            >
              {isDropTarget ? 'Drop lead here' : 'No leads here'}
            </p>
          )}
          {leads.map((lead) => (
            <LeadCard key={lead.id} lead={lead} users={users} onClick={() => onLeadClick(lead)} />
          ))}
        </div>
      </SortableContext>
    </div>
  );
}
