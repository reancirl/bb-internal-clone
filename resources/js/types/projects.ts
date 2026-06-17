export type ProjectStatus = 'lead' | 'active' | 'complete';

export interface ProjectListItem {
    id: number;
    name: string;
    client_name: string | null;
    status: ProjectStatus;
    lines_count: number;
    ordered_count: number;
    on_site_count: number;
}

// Dimension columns arrive from Laravel as decimal strings.
export interface ProjectDetail {
    id: number;
    name: string;
    client_name: string | null;
    address: string | null;
    status: ProjectStatus;
    [dimension: string]: string | number | null;
}

export interface DimensionField {
    key: string;
    label: string;
    value: string;
}

export interface PriceItemOption {
    id: number;
    name: string;
    unit: string | null;
    category: string | null;
    supplier_id: number | null;
}

export interface TakeoffLineRow {
    id: number;
    price_item_id: number | null;
    category: string | null;
    item: string;
    formula: string | null;
    unit: string | null;
    waste_pct: string;
    base_qty: number | null;
    computed_qty: number | null;
    formula_error: string | null;
    unit_price: number | null;
    line_cost: number | null;
    supplier_id: number | null;
    supplier_name: string | null;
    ordered: boolean;
    on_site: boolean;
    notes: string | null;
}

export interface CategoryTotal {
    category: string;
    total: number;
}

export interface ProjectCostSummary {
    categories: CategoryTotal[];
    grand_total: number;
    priced_count: number;
    unpriced_count: number;
}

export function formatQty(value: number | null): string {
    if (value === null) return '—';
    return value.toLocaleString('en-US', { maximumFractionDigits: 2 });
}

export function formatMoney(value: number | null): string {
    if (value === null) return '—';
    return value.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

export const STATUS_VARIANT: Record<ProjectStatus, 'secondary' | 'default' | 'outline'> = {
    lead: 'outline',
    active: 'default',
    complete: 'secondary',
};
