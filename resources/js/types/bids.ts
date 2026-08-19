// Shared types for the Sub Bidding feature.
// Mirrors the payload produced by BidRequestController.

export type BidRequestStatus = 'draft' | 'open' | 'awarded' | 'canceled';
export type BidResponseStatus = 'invited' | 'received' | 'declined';

/** Badge classes per bid request status — single source for index + show. */
export const BID_STATUS_STYLES: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    open: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    awarded: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    canceled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
