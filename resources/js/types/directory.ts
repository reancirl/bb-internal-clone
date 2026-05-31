export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export const PER_PAGE_OPTIONS = [10, 20, 30] as const;

export interface TradePartner {
    id: number;
    name: string;
    location: string | null;
    phone: string | null;
    email: string | null;
    used_before: boolean;
    negotiated_price: string | null;
    referral_source: string | null;
    notes: string | null;
    do_not_use: boolean;
    trades: string[];
}

export type VendorType = 'store' | 'supplier';

export interface Vendor {
    id: number;
    name: string;
    type: VendorType;
    location: string | null;
    phone: string | null;
    email: string | null;
    url: string | null;
    notes: string | null;
}
