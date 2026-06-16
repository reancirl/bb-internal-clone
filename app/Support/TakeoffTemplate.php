<?php

namespace App\Support;

/**
 * The standard set of takeoff lines a new project starts with, mirroring the
 * Material Master List takeoff calculator. Shared by the seeder and by new
 * project creation so a fresh project is usable after just entering dimensions.
 */
class TakeoffTemplate
{
    /**
     * @return list<array{category: string, item: string, formula: string, unit: string, waste_pct: int, notes: string|null}>
     */
    public static function lines(): array
    {
        return [
            ['category' => 'CONCRETE', 'item' => 'Slab concrete (yards)', 'formula' => 'house_sqft * slab_depth / 27', 'unit' => 'YD3', 'waste_pct' => 20, 'notes' => 'House SF × slab thickness / 27.'],
            ['category' => 'CONCRETE', 'item' => 'Anchor bolts', 'formula' => 'ext_wall_lft / 4', 'unit' => 'EA', 'waste_pct' => 20, 'notes' => 'Perimeter / 4 ft.'],
            ['category' => 'FRAMING', 'item' => '2x6 plates (6" wall)', 'formula' => 'ext_wall_lft * 2 / 16', 'unit' => 'EA', 'waste_pct' => 25, 'notes' => 'Perimeter ×2 / 16ft sticks.'],
            ['category' => 'FRAMING', 'item' => '2x6 studs (16" OC)', 'formula' => 'ext_wall_lft', 'unit' => 'EA', 'waste_pct' => 25, 'notes' => '~1 stud per LF.'],
            ['category' => 'FRAMING', 'item' => 'Wall sheathing 7/16 OSB', 'formula' => 'ext_wall_sqft / 32', 'unit' => 'EA', 'waste_pct' => 25, 'notes' => 'Wall SF / 32 (4x8 sheet).'],
            ['category' => 'FRAMING', 'item' => 'Roof sheathing 5/8 OSB', 'formula' => 'roof_sqft / 32', 'unit' => 'EA', 'waste_pct' => 25, 'notes' => 'Roof SF / 32.'],
            ['category' => 'FRAMING', 'item' => "House wrap (9')", 'formula' => 'ext_wall_lft', 'unit' => 'LFT', 'waste_pct' => 0, 'notes' => null],
            ['category' => 'ROOFING', 'item' => 'Underlayment', 'formula' => 'roof_sqft', 'unit' => 'SF', 'waste_pct' => 0, 'notes' => null],
            ['category' => 'ROOFING', 'item' => 'Ice & water', 'formula' => 'eve_lft + valley_lft', 'unit' => 'LF', 'waste_pct' => 0, 'notes' => 'Eave + valley.'],
            ['category' => 'ROOFING', 'item' => 'Valley metal', 'formula' => 'valley_lft', 'unit' => 'LF', 'waste_pct' => 0, 'notes' => null],
            ['category' => 'DRYWALL', 'item' => '1/2" drywall (walls)', 'formula' => 'int_wall_sqft / 48', 'unit' => 'sheet', 'waste_pct' => 0, 'notes' => 'Wall SF / 48 (4x12).'],
            ['category' => 'DRYWALL', 'item' => '5/8" drywall (ceiling)', 'formula' => 'ceiling_sqft / 48', 'unit' => 'sheet', 'waste_pct' => 0, 'notes' => 'Ceiling SF / 48.'],
            ['category' => 'INSULATION', 'item' => 'Spray foam (walls)', 'formula' => 'ext_wall_sqft', 'unit' => 'SF', 'waste_pct' => 0, 'notes' => null],
        ];
    }
}
