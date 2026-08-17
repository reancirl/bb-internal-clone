/**
 * Format integer cents as USD. Distinct name from the dollars-based
 * `formatMoney` in types/projects.ts — the unit is encoded in the name so
 * an auto-import can never silently render values 100x off.
 */
export function formatCents(cents: number): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}
