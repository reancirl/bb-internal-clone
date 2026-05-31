import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PER_PAGE_OPTIONS, type Paginated } from '@/types/directory';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type QueryParams = Record<string, string | number | boolean | null>;

interface PaginationProps {
    /** Paginator meta (data array is ignored here). */
    paginator: Pick<Paginated<unknown>, 'current_page' | 'last_page' | 'per_page' | 'total' | 'from' | 'to'>;
    /** Ziggy route name to navigate within. */
    routeName: string;
    /** Current query params (filters) to preserve across navigation. */
    params: QueryParams;
    /** The Inertia prop to partially reload, so only the table data is re-fetched. */
    propKey: string;
}

export function Pagination({ paginator, routeName, params, propKey }: PaginationProps) {
    const { current_page, last_page, per_page, total, from, to } = paginator;

    // Ziggy appends non-URI params as a query string.
    const pageUrl = (page: number) => route(routeName, { ...params, page });

    const visitOptions = {
        only: [propKey],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    };

    const changePerPage = (value: string) => {
        router.get(route(routeName), { ...params, per_page: Number(value), page: 1 }, visitOptions);
    };

    return (
        <div className="flex flex-col items-center justify-between gap-3 px-1 sm:flex-row">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <span>Rows per page</span>
                <Select value={String(per_page)} onValueChange={changePerPage}>
                    <SelectTrigger className="h-8 w-[72px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PER_PAGE_OPTIONS.map((n) => (
                            <SelectItem key={n} value={String(n)}>
                                {n}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="flex items-center gap-4 text-sm">
                <span className="text-muted-foreground tabular-nums">
                    {total === 0 ? '0' : `${from}–${to}`} of {total}
                </span>
                <div className="flex gap-1">
                    {current_page <= 1 ? (
                        <Button variant="outline" size="sm" className="gap-1" disabled>
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" className="gap-1" asChild>
                            <Link href={pageUrl(current_page - 1)} prefetch {...visitOptions}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                    )}
                    {current_page >= last_page ? (
                        <Button variant="outline" size="sm" className="gap-1" disabled>
                            Next
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" className="gap-1" asChild>
                            <Link href={pageUrl(current_page + 1)} prefetch {...visitOptions}>
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
