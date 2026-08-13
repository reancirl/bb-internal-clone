<?php

namespace Database\Seeders;

use App\Models\BudgetSection;
use Illuminate\Database\Seeder;

class BudgetCatalogSeeder extends Seeder
{
    /**
     * Budget catalog extracted verbatim from the "Marys REVAMP" sheet of the
     * master workbook (docs/04-xlsx-mapping.md §6). Idempotent.
     */
    public function run(): void
    {
        $catalog = [
            ['SOFT COSTS', [
                'Design Fees', 'Engineering', 'Planning and Specifications', 'Permits', 'Surveying',
                'Insurance/Bonds', 'Temporary Fencing', 'Equipment Rental', 'Temporary Utilities',
                'Job Trailer Rental', 'Storage', 'Dump Fees', 'Portable Toilets', 'Demolition',
                'Pressure Washing', 'Cleaning', 'Travel', 'Overhead Costs',
                '15% Adder for General Labor', '20% Adder for General Labor',
            ]],
            ['SITEWORK COSTS', [
                'Excavation', 'Utility-Electrical', 'Utility-Natural Gas/Propane', 'Utility-Water tap/Well',
                'Utility-Sewer/Septic', 'Utility-Communications', 'Paving', 'Striping', 'Hauling',
                'Fill Material', 'Driveway', 'Dirt work', 'Snow Removal', 'Tree removal', 'Mowing',
                'Site Concrete',
            ]],
            ['BUILDING COSTS', [
                'Foundation', 'Structural Steel', 'Lumber Package (NO ROOF OR FLOOR TRUSSES)',
                'Truss Package', 'Floor Package', 'Framing', 'Basement Framing Adder',
                'Deck framing materials', 'Decking/Deck', 'Concrete Patio', 'Garage Framing Labor',
                'Egress Window Work', 'Exterior Windows', 'Exterior Trim', 'Insulation and Air Sealing',
                'Roofing', 'Siding', 'Garage Doors', 'Exterior Doors', 'Drywall/Ceilings', 'Painting',
                'Rough in Electrical', 'Finish Electrical', 'Rough in Plumbing', 'Rough In Gas',
                'Finish Plumbing', 'Lighting', 'Custom Lighting', 'Stair Railing and Stair Finishings',
                'Stair Finishings', 'HVAC', 'Fire Sprinklers', 'Soffit and Facia',
                'Master Closet/Bath Shelving Systems', 'Other closet Shelving/Laundry Shelving/Pantry',
                'Gutters and Downspouts', 'Backup Generator', 'NG Fireplace installed', 'Trim Labor',
                'Stone', 'Flooring Labor', 'Garage Heaters',
            ]],
            ['ALLOWANCES', [
                'Interior Doors', 'Interior Trim',
                'Interior and Exterior Decorative Light Fixtures & Fans',
                'Millwork/Cabinetry/Custom Vanity', 'Flooring', 'Appliance Allowance',
                'Security System', 'Deck Railing', 'Tub or Shower Allowance',
                'Decorative Trusses/Beams', 'Accent Walls', 'Countertops',
                'Exterior Doors and Hardware',
            ]],
            // Ad-hoc per project — the sheet keeps this section as blank rows.
            ['CHANGE ORDERS', []],
        ];

        foreach ($catalog as $sort => [$name, $items]) {
            $section = BudgetSection::firstOrCreate(['name' => $name], ['sort_order' => $sort]);

            foreach ($items as $itemSort => $label) {
                $section->lineDefinitions()->firstOrCreate(
                    ['name' => $label],
                    ['sort_order' => $itemSort],
                );
            }
        }

        $this->command->info('Budget catalog: '.BudgetSection::count().' sections, '.\App\Models\BudgetLineDefinition::count().' line definitions.');
    }
}
