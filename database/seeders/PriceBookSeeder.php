<?php

namespace Database\Seeders;

use App\Models\EquipmentRate;
use App\Models\LaborRate;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Seeds the price book (categories 01–19 + representative items) and the
 * labor / equipment rate tables from the Material Master List.
 */
class PriceBookSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedItems();
        $this->seedLaborRates();
        $this->seedEquipmentRates();
    }

    private function seedCategories(): void
    {
        $categories = [
            '01' => 'Preliminary Works',
            '02' => 'Business Operations',
            '03' => 'Land & Site Improvement',
            '04' => 'Concrete',
            '05' => 'Framing',
            '06' => 'Roofing/Siding',
            '07' => 'Plumbing',
            '08' => 'Electrical',
            '09' => 'HVAC',
            '10' => 'Insulation & Drywall',
            '11' => 'Glass & Mirrors',
            '12' => 'Interior Finishings',
            '13' => 'Windows & Doors',
            '14' => 'Paint',
            '15' => 'Flooring & Tile',
            '16' => 'Appliances',
            '17' => 'Masonry',
            '18' => 'Exterior Works',
            '19' => 'Cleanup',
        ];

        $sort = 1;
        foreach ($categories as $code => $name) {
            PriceCategory::firstOrCreate(['code' => $code], ['name' => $name, 'sort' => $sort++]);
        }
    }

    private function seedItems(): void
    {
        $cat = PriceCategory::pluck('id', 'code');
        $vendor = Vendor::pluck('id', 'name');

        // [code, name, unit, fast, material, bb_install, sub_install, vendorName|null, notes]
        $items = [
            ['03', 'Dirt/Gravel - Material', 'TON', null, 25.00, null, null, null, '1.5" crushed rock $23/ton; road base $12/ton.'],
            ['04', 'Concrete Slab - Sub', 'SF', 12.00, null, null, 12.00, null, 'Flat $12/SF; power trowel +$3-4/SF. Danny Myers.'],
            ['04', 'Stem Walls - Sub', 'LF', null, null, null, 110.00, null, 'Mono $25/SF, 6\' wall $110/LF, footer $32/LF. Total Concrete (Luis).'],
            ['04', 'Concrete Material', 'YD3', null, 230.00, null, null, 'Croell', '$230/yd3.'],
            ['05', 'Framing Labor', 'SF', 12.00, null, 12.00, null, null, 'Package: frame, house wrap, doors/windows setting.'],
            ['05', 'Framing Material', 'SF', 13.50, 13.50, null, null, 'Truss Pro Desk', '$12–15/SF. Truss Pro Desk or Menards.'],
            ['06', 'Roofing Material - Shingles', 'SF', 2.80, 2.80, null, null, null, 'Underlayment, shingles, boots, nails.'],
            ['06', 'Standing Seam Metal Roof - Sub', 'SF', 4.00, null, null, 4.00, 'Protech', 'Caleb Walters. Color dependent.'],
            ['06', 'EIFS Siding', 'SF', 12.50, null, 3.50, 4.00, 'Alside', 'Manage $4/SF; demo $0.50/SF. Eliborio.'],
            ['07', 'Plumbing - Sub', 'SF', 13.50, null, null, 13.50, null, '$12–15/SF.'],
            ['08', 'Electrical - Sub', 'SF', 10.00, null, null, 10.00, 'Mountain Valley Electric', 'Rough-in + finish. Brent Eggers $9/SF.'],
            ['09', 'HVAC - Sub', 'SF', 19.00, null, null, 19.00, null, 'Forced air/venting. Radon mitigation $1,600.'],
            ['10', 'Spray Foam Insulation - Sub', 'SF', 1.70, null, null, 1.70, "MacArthur's", '1 set = 4,000 SF @ 1". Brian Schick installs.'],
            ['10', 'Drywall Material', 'sheet', null, 55.00, null, null, 'Home Depot', '$3.50/SF; $55/sheet. We provide sheets only.'],
            ['10', 'Drywall - Sub (Smooth)', 'SF', 2.00, null, null, 2.00, null, 'SG Drywall - Sabino.'],
            ['14', 'Paint - Sub', 'SF', 1.38, null, null, 1.38, null, '$1.38/SF (1 color, Harmony). Eliborio $30/hr.'],
            ['15', 'Tile Material', 'SF', 8.00, 8.00, null, null, 'Sheridan Floor To Ceiling', '$4–12/SF.'],
            ['18', 'Deck - TREX', 'SF', 48.00, 48.00, 28.00, null, null, 'Frame/finish labor. Rough sawn treated.'],
            ['18', 'Paving - Sub', 'SY', 57.50, null, null, 57.50, null, '$50–65/SY. Simon is the best.'],
            ['19', 'Dump Fees', 'TON', 75.00, null, null, null, null, 'Trees 8" or less in diameter are free.'],
        ];

        foreach ($items as [$code, $name, $unit, $fast, $material, $bb, $sub, $vendorName, $notes]) {
            PriceItem::firstOrCreate(
                ['price_category_id' => $cat[$code], 'name' => $name],
                [
                    'unit' => $unit,
                    'fast_price' => $fast,
                    'material_cost' => $material,
                    'bb_install_rate' => $bb,
                    'sub_install_rate' => $sub,
                    'preferred_vendor_id' => $vendorName ? ($vendor[$vendorName] ?? null) : null,
                    'notes' => $notes,
                ],
            );
        }
    }

    private function seedLaborRates(): void
    {
        // [class, base, w/burden, min bill rate, total]
        $rates = [
            ['Dirt Crew', 24.50, 32.26, 55.52, 62.71],
            ['Frame Crew', 23.50, 30.94, 53.26, 60.16],
            ['Finish Crew', 22.50, 29.63, 50.99, 57.60],
            ['Labor Class', 22.50, 29.63, 50.99, 57.60],
            ['Lead Class', 35.00, 46.08, 79.32, 89.59],
            ['SPM Class', 34.86, 45.89, 134.29, 151.68],
            ['Office/Shop Crew', 25.16, 33.13, 161.55, 182.48],
        ];

        foreach ($rates as [$class, $base, $burden, $bill, $total]) {
            LaborRate::firstOrCreate(
                ['class_name' => $class],
                [
                    'base_rate' => $base,
                    'burden_rate' => $burden,
                    'bill_rate' => $bill,
                    'total' => $total,
                ],
            );
        }
    }

    private function seedEquipmentRates(): void
    {
        // [name, day, week, month, notes]
        $equipment = [
            ['Telehandler / Offroad man lift', 232.00, 1452.00, 4356.00, null],
            ['Excavator (Case 210)', 232.00, 2529.00, 7587.00, null],
            ['Skidsteer', 303.00, 1210.00, 3630.00, null],
            ['Scissor lift', 121.00, 484.00, 1452.00, null],
            ['Mini excavator', 333.00, 1331.00, 4792.00, null],
            ['Mini skid', 182.00, 726.00, 2178.00, null],
            ['Trencher (ride on)', 394.00, 1573.00, 4719.00, null],
            ['Dump Truck', null, null, null, '$80/hr.'],
            ['Side Dump', null, null, null, '$150/hr. Wagner Ranch.'],
            ['Crane', null, null, null, '4 hr min $980; $245/hr after; $85 daily fuel surcharge.'],
        ];

        foreach ($equipment as [$name, $day, $week, $month, $notes]) {
            EquipmentRate::firstOrCreate(
                ['name' => $name],
                [
                    'day_rate' => $day,
                    'week_rate' => $week,
                    'month_rate' => $month,
                    'notes' => $notes,
                ],
            );
        }
    }
}
