export type ProposalStatus = 'draft' | 'sent' | 'accepted' | 'rejected';

/** Badge classes per proposal status — single source for index + show. */
export const PROPOSAL_STATUS_STYLES: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    accepted: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
