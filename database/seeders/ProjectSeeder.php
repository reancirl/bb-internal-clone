<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Support\TakeoffTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo project using the dimensions from the Material Master List
 * takeoff calculator (page 20–23), plus a starter set of takeoff lines whose
 * formulas reference those dimensions.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::create([
            'name' => 'Staebler Residence',
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
        ]);

        $sort = 1;
        foreach (TakeoffTemplate::lines() as $line) {
            $project->takeoffLines()->create([...$line, 'sort' => $sort++]);
        }
    }
}
