export interface PriceCategoryOption {
    id: number;
    code: string;
    name: string;
}

export interface VendorOption {
    id: number;
    name: string;
}

// Decimal columns arrive from Laravel as strings (e.g. "24.50") or null.
export interface PriceItemRow {
    id: number;
    name: string;
    unit: string | null;
    fast_price: string | null;
    material_cost: string | null;
    bb_install_rate: string | null;
    sub_install_rate: string | null;
    price_category_id: number;
    category_code: string;
    category_name: string;
    preferred_vendor_id: number | null;
    vendor_name: string | null;
    notes: string | null;
}

export interface LaborRate {
    id: number;
    class_name: string;
    base_rate: string | null;
    burden_rate: string | null;
    bill_rate: string | null;
    total: string | null;
    notes: string | null;
}

export interface EquipmentRate {
    id: number;
    name: string;
    day_rate: string | null;
    week_rate: string | null;
    month_rate: string | null;
    notes: string | null;
}

export function formatUsd(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}
