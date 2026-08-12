// Shared types for the Customer Selections feature (Buildertrend-style).
// Mirrors the payload shape produced by SelectionController@index.

export interface SelectionChoice {
  id: number;
  label: string;
  description: string | null;
  price_cents: number | null;
  vendor_id: number | null;
  vendor_name: string | null;
}

export interface ProjectSelection {
  id: number;
  category: {
    id: number;
    name: string;
    scope: 'living' | 'garage' | 'shared';
  };
  item: {
    id: number;
    label: string;
    recommended: string | null;
    guidance: string | null;
  };
  allowance_cents: number | null;
  deadline_date: string | null;
  notes: string | null;
  approved_choice_id: number | null;
  approved_at: string | null;
  approved_by: string | null;
  approval_comment: string | null;
  variance_cents: number | null;
  choices: SelectionChoice[];
}

export interface VendorOption {
  id: number;
  name: string;
}
