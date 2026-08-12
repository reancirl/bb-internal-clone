<?php

namespace Database\Seeders;

use App\Models\DecisionCategory;
use Illuminate\Database\Seeder;

class DecisionCatalogSeeder extends Seeder
{
    /**
     * Decision catalog extracted verbatim from the "Customer Material
     * Decision List" sheet of the master workbook (docs/04-xlsx-mapping.md §4).
     * Idempotent — firstOrCreate throughout, safe to re-run.
     */
    public function run(): void
    {
        $catalog = [
            // ── Shared (whole build) ────────────────────────────────────────
            ['EXTERIOR WALLS', 'shared', null, [
                ['Siding Material', 'EIFS', 'Color and texture options in office'],
                ['Timber work', 'Timber accent on porch', 'Look at examples in office'],
                ['Soffit', 'Metal', 'Color options in office'],
                ['Fascia', 'Metal', 'Color options in office'],
            ]],
            ['ROOF', 'shared', null, [
                ['Roofing Material', 'Shingles', 'Color options in office'],
                ['Truss style', 'Cathedral', null],
            ]],
            ['WINDOWS', 'shared', null, [
                ['Sizes', null, 'Need decision'],
                ['Material', 'JELD-WEN or best quote', 'Choose color'],
            ]],
            ['EXTERIOR DOORS', 'shared', null, [
                ['Sizes', 'See plan for sizes', null],
                ['Material', 'JELD-WEN or best quote', null],
                ['Hardware', 'Stock handle with keypad', 'Examples in office'],
            ]],
            ['CONCRETE/FOUNDATION', 'shared', null, [
                ['Foundation', 'Mono-slab Concrete floor', null],
            ]],
            ['LANDSCAPING', 'shared', null, [
                ['Finish grade', 'Dirt finish grade', null],
                ['Concrete Approach', "30'x20'", null],
            ]],

            // ── Living-specific ─────────────────────────────────────────────
            ['FLOORING', 'living', null, [
                ['Material', null, 'Samples in office or Sheridan Floor To Ceiling or Home Depot'],
            ]],
            ['WALL FINISHES', 'living', null, [
                ['Wall Texture', 'Knockdown', 'Choose texture'],
                ['Paint Color', 'Semi-gloss White', 'Behr Ultra paint from Home Depot'],
            ]],
            ['CEILING', 'living', null, [
                ['Ceiling Texture', 'Knockdown', 'Choose texture'],
                ['Paint Color', 'Flat white', 'Behr Ultra paint from Home Depot'],
            ]],
            ['INSULATION', 'living', null, [
                ['Walls', 'R19 Batts', null],
                ['Ceiling', 'R49 Blow-in', null],
            ]],
            ['MILLWORK', 'living', null, [
                ['Baseboard', '1x6 stained wood', 'See style in office'],
                ['Window/Door Trim', '1x4 w/ 1x6 header', 'See style in office'],
                ['Built-in shelving', 'Rod and shelf in closets', null],
            ]],
            ['HVAC', 'living', null, [
                ['In floor heat', null, null],
                ['Cooling', 'Mini-Splits', null],
            ]],
            ['PLUMBING', 'living', null, [
                ['Water Heater', 'Tankless combo unit', null],
            ]],
            ['BATHROOMS', 'living', null, [
                ['Vanity', null, 'WAYFAIR.COM'],
                ['Vanity Faucets', null, 'MOEN'],
                ['Shower Fixtures', null, 'MOEN'],
                ['Shower', null, 'Choose color'],
                ['Toilets', null, null],
                ['Tile', null, 'Sheridan Floor to Ceiling or Home Depot'],
                ['Grout', null, 'Choose color'],
                ['Flooring', null, 'If different than main flooring'],
                ['Mirrors', null, 'Wayfair'],
                ['Special Lighting', null, null],
                ['Fan', 'Stock', 'Note if you want anything specific'],
                ['Hardware', '1 set in each bathroom', '1 towel bar, 1 TP holder, 1 towel hook, 1 hand towel bar'],
            ]],
            ['KITCHEN', 'living', null, [
                ['Cabinetry', 'Klearvue or U.S. Cab Depot', 'Samples in office - Plan pending'],
                ['Hardware', 'Basic 4" Handles', null],
                ['Countertops', 'Quartz or epoxy', 'Tier 1 or Tier 2'],
                ['Backsplash', 'Match countertops', null],
                ['Appliances', null, 'Standard sizes - Customer provides appliances'],
                ['Sink', null, null],
                ['Faucet', 'MOEN', 'Choose color'],
                ['Special Lighting', null, null],
                ['Vent hood', null, 'If no microwave'],
            ]],
            ['ELECTRICAL', 'living', null, [
                ['Lighting', 'Stock puck-lights throughout', 'Examples in office'],
                ['Outlets/switches', null, 'Choose style/color'],
                ['Outside lighting', '6 under overbuild', null],
                ['Outside outlets', '4 on front of house', null],
                ['Ceiling fans', null, 'Home Depot or Menards'],
            ]],

            // ── Garage-specific ─────────────────────────────────────────────
            ['GARAGE DOORS', 'garage', null, [
                ['Material', null, 'Color and style options'],
                ['Sizes', null, null],
            ]],
            ['FLOORING', 'garage', null, [
                ['Material', 'Bare concrete', 'Epoxy options cost more'],
            ]],
            ['WALL FINISHES', 'garage', null, [
                ['Wall Covering', 'Drywall', 'Drywall w/ tape on common wall; drywall w/ tape or plywood elsewhere'],
                ['Paint', 'Semi-gloss white', null],
            ]],
            ['CEILING', 'garage', null, [
                ['Covering', 'Drywall', 'Drywall w/ tape'],
                ['Paint', 'Semi-gloss white', null],
            ]],
            ['INSULATION', 'garage', null, [
                ['Walls', 'R19 batts', 'R19 + R8 with EIFS (R27)'],
                ['Ceiling', 'R60 blow in', null],
            ]],
            ['HVAC', 'garage', null, [
                ['In floor Heat', null, 'Example in office'],
                ['Cooling', 'None', null],
            ]],
            ['PLUMBING', 'garage', null, [
                ['Mop sink', null, null],
                ['Center floor drains', '2 drains', null],
            ]],
            ['ELECTRICAL', 'garage', null, [
                ['Lighting', '3 rows of shop lights', 'Examples in office'],
                ['Outlets/switches', null, 'Choose style/color'],
                ['Outside lighting', null, null],
                ['Outside outlets', null, null],
                ['Ceiling fans', null, 'Home Depot or Menards'],
            ]],

            // ── Anything else ───────────────────────────────────────────────
            ['ADDITIONAL ITEMS', 'shared', 'Anything not covered above — add freely during the walkthrough.', [
                ['Back-up generator', null, null],
                ['Propane tank', null, 'New tank if the existing one cannot serve both houses'],
            ]],
        ];

        foreach ($catalog as $sort => [$name, $scope, $notes, $items]) {
            $category = DecisionCategory::firstOrCreate(
                ['name' => $name, 'scope' => $scope],
                ['sort_order' => $sort, 'notes' => $notes],
            );

            foreach ($items as $itemSort => [$label, $recommended, $guidance]) {
                $category->items()->firstOrCreate(
                    ['label' => $label],
                    ['recommended' => $recommended, 'guidance' => $guidance, 'sort_order' => $itemSort],
                );
            }
        }

        $this->command->info('Decision catalog: '.DecisionCategory::count().' categories, '.\App\Models\DecisionItem::count().' items.');
    }
}
