// Shared types for the Project Budget (bid vs actual) feature.
// Mirrors the payload produced by BudgetController.

export interface BudgetSectionOption {
  id: number;
  name: string;
}

export interface BudgetLine {
  id: number;
  section_id: number;
  section_name: string;
  definition_id: number | null;
  name: string;
  notes: string | null;
  bid_sub_cents: number | null;
  actual_sub_cents: number | null;
  estimated_material_cents: number | null;
  actual_material_cents: number | null;
  estimated_labor_cents: number | null;
  actual_labor_cents: number | null;
  budgeted_cents: number;
  actual_cents: number;
  variance_cents: number;
}

export interface ChangeOrderEntry {
  id: number;
  number: number;
  title: string;
  description: string | null;
  price_cents: number | null;
  status: 'pending' | 'approved' | 'declined';
  decided_at: string | null;
  decided_by: string | null;
  decision_comment: string | null;
}

export type MoneyField =
  | 'bid_sub_cents'
  | 'actual_sub_cents'
  | 'estimated_material_cents'
  | 'actual_material_cents'
  | 'estimated_labor_cents'
  | 'actual_labor_cents';
