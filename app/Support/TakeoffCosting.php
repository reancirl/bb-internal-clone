<?php

namespace App\Support;

use App\Models\TakeoffLine;
use Illuminate\Support\Collection;

/**
 * Shared quantity + cost math for takeoff lines. Used by the project screen,
 * the takeoff CSV export, and proposal snapshots so they can never disagree.
 */
class TakeoffCosting
{
    public function __construct(
        private readonly FormulaEvaluator $evaluator = new FormulaEvaluator,
    ) {}

    /**
     * Evaluate one takeoff line: quantity (with waste) and extended cost from
     * the linked price book item.
     *
     * @param  array<string, float>  $dimensions
     * @return array{base: float|null, qty: float|null, error: string|null, unit_price: float|null, cost: float|null}
     */
    public function computeLine(TakeoffLine $line, array $dimensions): array
    {
        $base = null;
        $error = null;

        if ($line->formula !== null && $line->formula !== '') {
            try {
                $base = $this->evaluator->evaluate($line->formula, $dimensions);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        $waste = (float) $line->waste_pct;
        $qty = $base === null ? null : round($base * (1 + $waste / 100), 2);

        // Use the best unit price we have on file: the quoted "fast price",
        // falling back to material cost when no fast price is set.
        $rawPrice = $line->priceItem?->fast_price ?? $line->priceItem?->material_cost;
        $unitPrice = $rawPrice !== null ? (float) $rawPrice : null;
        $cost = ($qty === null || $unitPrice === null) ? null : round($qty * $unitPrice, 2);

        return [
            'base' => $base === null ? null : round($base, 2),
            'qty' => $qty,
            'error' => $error,
            'unit_price' => $unitPrice,
            'cost' => $cost,
        ];
    }

    /**
     * Roll up per-line costs into category subtotals + a grand total, and count
     * how many priced lines fed the total vs. lines that have a quantity but no
     * price (so the UI can flag incomplete pricing rather than silently undercount).
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{categories: list<array{category: string, total: float}>, grand_total: float, priced_count: int, unpriced_count: int}
     */
    public function costSummary(Collection $lines): array
    {
        $categories = $lines
            ->filter(fn ($l) => $l['line_cost'] !== null)
            ->groupBy(fn ($l) => $l['category'] ?: 'Uncategorized')
            ->map(fn ($group, $category) => [
                'category' => (string) $category,
                'total' => round($group->sum('line_cost'), 2),
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'grand_total' => round($lines->sum(fn ($l) => $l['line_cost'] ?? 0), 2),
            'priced_count' => $lines->filter(fn ($l) => $l['line_cost'] !== null)->count(),
            'unpriced_count' => $lines->filter(fn ($l) => $l['line_cost'] === null && $l['computed_qty'] !== null)->count(),
        ];
    }

    /**
     * Dollars-to-cents at the price-book boundary. The price book stores
     * decimal dollars; proposals and contracts store integer cents.
     */
    public static function toCents(?float $dollars): ?int
    {
        return $dollars === null ? null : (int) round($dollars * 100);
    }
}
