// Shared types for the CRM Pipeline feature. These mirror the Lead and
// LeadActivity models as serialized by LeadController.

export type LeadFunnel = 'general' | 'cornerstone';

export const LEAD_FUNNELS: { value: LeadFunnel; label: string }[] = [
  { value: 'general', label: 'Sales pipeline' },
  { value: 'cornerstone', label: 'Cornerstone / spec homes' },
];

export interface Lead {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  build_location: string | null;
  project_details: string | null;
  status:
    | 'new'
    | 'contacted'
    | 'qualified'
    | 'meeting_scheduled'
    | 'proposal_sent'
    | 'won'
    | 'lost';
  priority: 'low' | 'medium' | 'high';
  source: string;
  funnel: LeadFunnel;
  estimated_value_cents: number | null;
  next_follow_up_date: string | null;
  assigned_to_user_id: number | null;
  lost_reason: string | null;
  won_at: string | null;
  lost_at: string | null;
  converted_project_id: number | null;
  created_at: string;
  updated_at: string;
}

export interface NewLead {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  build_location?: string | null;
  project_details?: string | null;
  status?: Lead['status'];
  priority?: Lead['priority'];
  source?: string;
  funnel?: LeadFunnel;
  estimated_value_cents?: number | null;
  next_follow_up_date?: string | null;
  assigned_to_user_id?: number | null;
}

export interface LeadActivity {
  id: number;
  lead_id: number;
  activity_type: 'call' | 'email' | 'meeting' | 'note' | 'sms';
  title: string;
  description: string | null;
  scheduled_at: string | null;
  completed_at: string | null;
  created_by: {
    id: number;
    name: string;
  };
  created_at: string;
}

export interface CrmUser {
  id: number;
  name: string;
}
