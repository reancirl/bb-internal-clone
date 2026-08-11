import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Lead, LeadActivity, CrmUser } from '@/types/crm';
import { StatusPill } from '@/components/leads/status-pill';
import { PriorityBadge } from '@/components/leads/priority-badge';
import { ActivityTimeline } from '@/components/leads/activity-timeline';
import { QuickActionModal } from '@/components/leads/quick-action-modal';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  ArrowLeft,
  Mail,
  Phone,
  MapPin,
  DollarSign,
  Calendar,
  User,
  Plus,
  PhoneCall,
  Send,
  CalendarCheck,
  FileText,
  ExternalLink,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'CRM Pipeline', href: '/admin/leads' },
];

interface LeadShowProps {
  lead: Lead;
  activities: LeadActivity[];
  users: CrmUser[];
}

export default function LeadShow({ lead, activities, users }: LeadShowProps) {
  const [modalOpen, setModalOpen] = useState(false);
  const [converting, setConverting] = useState(false);

  const assignedUser = users.find((u) => u.id === lead.assigned_to_user_id);

  const handleSaveActivity = (
    activity: Omit<LeadActivity, 'id' | 'lead_id' | 'created_by' | 'created_at'>,
  ) => {
    // Success toast comes from the server flash via <FlashToaster />.
    router.post(`/admin/leads/${lead.id}/activities`, activity, {
      preserveScroll: true,
      onError: () => toast.error('Could not log activity.'),
    });
  };

  const handleConvert = () => {
    setConverting(true);
    router.post(
      `/admin/leads/${lead.id}/convert`,
      {},
      {
        onError: () => {
          setConverting(false);
          toast.error('Could not convert lead to project.');
        },
      },
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`${lead.first_name} ${lead.last_name}`} />

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-4">
            <Link href="/admin/leads">
              <Button variant="outline" size="icon">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div>
              <h1 className="text-foreground text-2xl font-semibold">
                {lead.first_name} {lead.last_name}
              </h1>
              <div className="mt-2 flex items-center gap-2">
                <StatusPill status={lead.status} />
                <PriorityBadge priority={lead.priority} />
              </div>
            </div>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setModalOpen(true)}>
              <PhoneCall className="mr-2 h-4 w-4" />
              Log Call
            </Button>
            <Button variant="outline" onClick={() => setModalOpen(true)}>
              <Send className="mr-2 h-4 w-4" />
              Log Email
            </Button>
            <Button variant="outline" onClick={() => setModalOpen(true)}>
              <CalendarCheck className="mr-2 h-4 w-4" />
              Log Meeting
            </Button>
            <Button onClick={() => setModalOpen(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Note
            </Button>
          </div>
        </div>

        {/* Two Column Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column - Lead Info */}
          <div className="lg:col-span-1 space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Contact Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-start gap-3">
                  <Mail className="h-5 w-5 text-muted-foreground mt-0.5" />
                  <div>
                    <p className="text-sm font-medium">Email</p>
                    <a
                      href={`mailto:${lead.email}`}
                      className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    >
                      {lead.email}
                    </a>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <Phone className="h-5 w-5 text-muted-foreground mt-0.5" />
                  <div>
                    <p className="text-sm font-medium">Phone</p>
                    <a
                      href={`tel:${lead.phone}`}
                      className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    >
                      {lead.phone}
                    </a>
                  </div>
                </div>

                {lead.build_location && (
                  <div className="flex items-start gap-3">
                    <MapPin className="h-5 w-5 text-muted-foreground mt-0.5" />
                    <div>
                      <p className="text-sm font-medium">Location</p>
                      <p className="text-sm text-muted-foreground">{lead.build_location}</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Project Details</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {lead.project_details && (
                  <div>
                    <p className="text-sm font-medium mb-1">Description</p>
                    <p className="text-sm text-muted-foreground">{lead.project_details}</p>
                  </div>
                )}

                {lead.estimated_value_cents !== null && (
                  <div className="flex items-center gap-3">
                    <DollarSign className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <p className="text-sm font-medium">Estimated Value</p>
                      <p className="text-lg font-bold text-green-700 dark:text-green-400">
                        ${(lead.estimated_value_cents / 100).toLocaleString()}
                      </p>
                    </div>
                  </div>
                )}

                <div className="flex items-center gap-3">
                  <Calendar className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium">Lead Source</p>
                    <p className="text-sm text-muted-foreground capitalize">
                      {lead.source.replace('_', ' ')}
                    </p>
                  </div>
                </div>

                {assignedUser && (
                  <div className="flex items-center gap-3">
                    <User className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <p className="text-sm font-medium">Assigned To</p>
                      <p className="text-sm text-muted-foreground">{assignedUser.name}</p>
                    </div>
                  </div>
                )}

                {lead.next_follow_up_date && (
                  <div className="flex items-center gap-3">
                    <Calendar className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <p className="text-sm font-medium">Next Follow-up</p>
                      <p className="text-sm text-muted-foreground">
                        {format(parseISO(lead.next_follow_up_date), 'MMMM d, yyyy')}
                      </p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {lead.status === 'lost' && lead.lost_reason && (
              <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30">
                <CardHeader>
                  <CardTitle className="text-red-900 dark:text-red-200">Lost Reason</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-red-800 dark:text-red-300">{lead.lost_reason}</p>
                </CardContent>
              </Card>
            )}

            {lead.status === 'won' && lead.won_at && (
              <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30">
                <CardHeader>
                  <CardTitle className="text-green-900 dark:text-green-200">Won!</CardTitle>
                  <CardDescription className="text-green-700 dark:text-green-300">
                    {format(parseISO(lead.won_at), 'MMMM d, yyyy')}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {lead.converted_project_id ? (
                    <Button className="w-full" variant="outline" asChild>
                      <Link href={`/projects/${lead.converted_project_id}`}>
                        <ExternalLink className="mr-2 h-4 w-4" />
                        View Project
                      </Link>
                    </Button>
                  ) : (
                    <Button className="w-full" onClick={handleConvert} disabled={converting}>
                      <Plus className="mr-2 h-4 w-4" />
                      {converting ? 'Converting…' : 'Convert to Project'}
                    </Button>
                  )}
                </CardContent>
              </Card>
            )}
          </div>

          {/* Right Column - Activity Timeline */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>Activity Timeline</CardTitle>
                    <CardDescription>
                      {activities.length} {activities.length === 1 ? 'activity' : 'activities'}
                    </CardDescription>
                  </div>
                  <Button variant="outline" onClick={() => setModalOpen(true)}>
                    <FileText className="mr-2 h-4 w-4" />
                    Log Activity
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                <ActivityTimeline activities={activities} />
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {/* Quick Action Modal */}
      <QuickActionModal
        open={modalOpen}
        onOpenChange={setModalOpen}
        onSave={handleSaveActivity}
        leadName={`${lead.first_name} ${lead.last_name}`}
      />
    </AppLayout>
  );
}
