<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Five demo projects mirroring the job mix Buildertrend showcases —
 * custom home, kitchen remodel, shop build, addition, spec home — in
 * BuffaloBuilt's Wyoming service area. Idempotent (firstOrCreate by name).
 */
class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'name' => 'Peterson Residence',
                'client_name' => 'Mark & Dana Peterson',
                'address' => '214 Coffeen Ave, Sheridan, WY',
                'status' => Project::STATUS_ACTIVE,
                'contract_price_cents' => 48500000, // $485,000 custom home
                'house_sqft' => 2450, 'garage_sqft' => 720, 'roof_sqft' => 3400,
                'valley_lft' => 62, 'eve_lft' => 188, 'rake_lft' => 64,
                'ext_wall_lft' => 214, 'int_wall_lft' => 236, 'ext_wall_sqft' => 1926,
                'int_wall_sqft' => 4248, 'ceiling_sqft' => 2450, 'wall_height' => 9,
                'footer_height' => 1.33, 'footer_width' => 1.67, 'slab_depth' => 0.33,
            ],
            [
                'name' => 'Miller Kitchen Remodel',
                'client_name' => 'Susan Miller',
                'address' => '48 N Main St, Buffalo, WY',
                'status' => Project::STATUS_ACTIVE,
                'contract_price_cents' => 6800000, // $68,000 remodel
                'house_sqft' => 280, 'garage_sqft' => 0, 'roof_sqft' => 0,
                'valley_lft' => 0, 'eve_lft' => 0, 'rake_lft' => 0,
                'ext_wall_lft' => 0, 'int_wall_lft' => 42, 'ext_wall_sqft' => 0,
                'int_wall_sqft' => 336, 'ceiling_sqft' => 280, 'wall_height' => 8,
                'footer_height' => 0, 'footer_width' => 0, 'slab_depth' => 0,
            ],
            [
                'name' => 'Hutchins Shop Build',
                'client_name' => 'Carl Hutchins',
                'address' => '1877 Force Rd, Gillette, WY',
                'status' => Project::STATUS_LEAD,
                'contract_price_cents' => 12000000, // $120,000 30x40 shop
                'house_sqft' => 0, 'garage_sqft' => 1200, 'roof_sqft' => 1420,
                'valley_lft' => 0, 'eve_lft' => 84, 'rake_lft' => 44,
                'ext_wall_lft' => 140, 'int_wall_lft' => 0, 'ext_wall_sqft' => 1680,
                'int_wall_sqft' => 0, 'ceiling_sqft' => 1200, 'wall_height' => 12,
                'footer_height' => 1.33, 'footer_width' => 1.67, 'slab_depth' => 0.5,
            ],
            [
                'name' => 'Vargas Home Addition',
                'client_name' => 'Elena Vargas',
                'address' => '512 S Wolcott St, Casper, WY',
                'status' => Project::STATUS_ACTIVE,
                'contract_price_cents' => 9200000, // $92,000 sunroom + bedroom addition
                'house_sqft' => 420, 'garage_sqft' => 0, 'roof_sqft' => 510,
                'valley_lft' => 18, 'eve_lft' => 52, 'rake_lft' => 22,
                'ext_wall_lft' => 62, 'int_wall_lft' => 28, 'ext_wall_sqft' => 558,
                'int_wall_sqft' => 504, 'ceiling_sqft' => 420, 'wall_height' => 9,
                'footer_height' => 1.33, 'footer_width' => 1.67, 'slab_depth' => 0.33,
            ],
            [
                'name' => 'Bradley Spec Home',
                'client_name' => 'BuffaloBuilt LLC (spec)',
                'address' => '9 Kruse Ln, Big Horn, WY',
                'status' => Project::STATUS_COMPLETE,
                'contract_price_cents' => 41000000, // $410,000 spec build, sold
                'house_sqft' => 1980, 'garage_sqft' => 580, 'roof_sqft' => 2760,
                'valley_lft' => 48, 'eve_lft' => 162, 'rake_lft' => 56,
                'ext_wall_lft' => 186, 'int_wall_lft' => 198, 'ext_wall_sqft' => 1488,
                'int_wall_sqft' => 3168, 'ceiling_sqft' => 1980, 'wall_height' => 8,
                'footer_height' => 1.33, 'footer_width' => 1.67, 'slab_depth' => 0.33,
            ],
        ];

        foreach ($projects as $data) {
            Project::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('Seeded '.count($projects).' demo projects.');
    }
}
