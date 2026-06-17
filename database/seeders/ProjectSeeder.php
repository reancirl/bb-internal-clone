<?php

namespace Database\Seeders;

use App\Models\PriceItem;
use App\Models\Project;
use App\Support\TakeoffTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo project using the dimensions from the Material Master List
 * takeoff calculator (page 20–23), plus a starter set of takeoff lines whose
 * formulas reference those dimensions. Idempotent (firstOrCreate).
 *
 * Lines whose unit matches a priced Price Book item are linked to it, so the
 * estimate roll-up shows real costs out of the box; the rest stay unpriced to
 * demonstrate the "quantity but no price" flag.
 */
class ProjectSeeder extends Seeder
{
    /**
     * Takeoff line item → Price Book item name (unit-compatible matches only).
     */
    private const PRICE_LINKS = [
        'Slab concrete (yards)' => 'Concrete Material',          // YD3 × $230
        'Underlayment' => 'Roofing Material - Shingles',         // SF × $2.80
        '1/2" drywall (walls)' => 'Drywall Material',            // sheet × $55
        '5/8" drywall (ceiling)' => 'Drywall Material',          // sheet × $55
        'Spray foam (walls)' => 'Spray Foam Insulation - Sub',   // SF × $1.70
    ];

    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'Staebler Residence'],
            [
                'client_name' => 'Staebler',
                'address' => 'Sheridan, WY',
                'status' => Project::STATUS_ACTIVE,
                'house_sqft' => 1103.42,
                'garage_sqft' => 218.89,
                'roof_sqft' => 1703,
                'valley_lft' => 56.5,
                'eve_lft' => 147.45,
                'rake_lft' => 50,
                'ext_wall_lft' => 166.5,
                'int_wall_lft' => 142.25,
                'ext_wall_sqft' => 1332,
                'int_wall_sqft' => 3608,
                'ceiling_sqft' => 1322.31,
                'wall_height' => 8,
                'footer_height' => 1.33,
                'footer_width' => 1.67,
                'slab_depth' => 0.33,
            ],
        );

        $priceItems = PriceItem::query()->get()->keyBy('name');

        $sort = 1;
        foreach (TakeoffTemplate::lines() as $line) {
            $attributes = [...$line, 'sort' => $sort++];

            $linkName = self::PRICE_LINKS[$line['item']] ?? null;
            if ($linkName !== null && $priceItems->has($linkName)) {
                $priceItem = $priceItems->get($linkName);
                $attributes['price_item_id'] = $priceItem->id;
                $attributes['supplier_id'] = $priceItem->preferred_vendor_id;
            }

            $project->takeoffLines()->firstOrCreate(['item' => $line['item']], $attributes);
        }
    }
}
