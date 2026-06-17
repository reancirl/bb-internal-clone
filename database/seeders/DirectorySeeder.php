<?php

namespace Database\Seeders;

use App\Models\TradePartner;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Seeds a representative slice of the Material Master List directory:
 * preferred stores/suppliers + subcontractors (with real Wyoming contacts
 * pulled from the trade-partner sheet).
 */
class DirectorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedVendors();
        $this->seedTradePartners();
    }

    private function seedVendors(): void
    {
        $vendors = [
            ['name' => 'Home Depot', 'type' => 'store', 'location' => 'Sheridan', 'notes' => 'Primary store for lumber, fasteners, fixtures.'],
            ['name' => 'Menards', 'type' => 'store', 'location' => 'Gillette', 'notes' => 'Doors, windows, trim, drywall.'],
            ['name' => 'Bloedorn Lumber', 'type' => 'store', 'location' => 'Sheridan', 'notes' => 'EZ-forms, EIFS materials, prefinished trim.'],
            ['name' => 'Croell', 'type' => 'supplier', 'location' => 'Sheridan', 'notes' => 'Concrete / ready-mix.'],
            ['name' => 'Truss Pro Desk', 'type' => 'supplier', 'location' => null, 'notes' => 'Framing material packages.'],
            ['name' => 'Diamond Truss', 'type' => 'supplier', 'location' => null, 'notes' => 'Trusses (anything under 30\').'],
            ['name' => 'Protech', 'type' => 'supplier', 'location' => null, 'notes' => 'Standing seam metal roofing/siding. Randall Huckaba.'],
            ['name' => 'Sundance Steel', 'type' => 'supplier', 'location' => null, 'notes' => 'Metal roofing — color dependent.'],
            ['name' => 'Alside', 'type' => 'supplier', 'location' => 'Casper', 'notes' => 'EIFS / siding (T-2000 base, foam, mesh).'],
            ['name' => "MacArthur's", 'type' => 'supplier', 'location' => null, 'notes' => 'Spray foam sets.'],
            ['name' => 'Sheridan Floor To Ceiling', 'type' => 'store', 'location' => 'Sheridan', 'phone' => '(307) 675-1030', 'notes' => 'Flooring, carpet, tile.'],
            ['name' => 'Frontier Iron Works', 'type' => 'supplier', 'location' => null, 'notes' => 'Stair railing, powder coat.'],
            ['name' => 'Amazon', 'type' => 'store', 'location' => null, 'notes' => 'Air switches, mini-splits, misc.'],
        ];

        foreach ($vendors as $v) {
            Vendor::firstOrCreate(['name' => $v['name']], $v);
        }
    }

    private function seedTradePartners(): void
    {
        // [name, [trades], location, phone, email, used_before, do_not_use, referral, negotiated_price, notes]
        $partners = [
            ['Total Concrete Works LLC', ['Concrete'], 'Sheridan', null, null, true, false, null, null, 'Luis — stem walls; $1100 winter charge.'],
            ['Danny Myers', ['Concrete'], 'Sheridan', null, null, true, false, null, '$3/SF power trowel', 'Concrete slab finishing.'],
            ['JNL Pumping', ['Concrete'], null, '(307) 684-7272', null, true, false, null, '$1,500/day', 'Concrete pump.'],
            ['Quality Interiors', ['Countertops'], 'Sheridan', null, null, true, false, null, null, 'Countertops.'],
            ['Simply Stone LLC', ['Countertops'], 'Sheridan', null, null, true, false, null, null, 'Countertops / templating.'],
            ['Buffalo Stone and Tile', ['Cabinet Installer', 'Flooring', 'Tile Installer'], 'Buffalo', '(307) 278-0943', 'buffalostoneandtile@gmail.com', false, false, 'online', null, null],
            ['Cosner Construction Co', ['Cabinet Installer', 'Finish Carpentry'], 'Sheridan', '(307) 672-3507', null, false, false, 'online', null, null],
            ['SG Drywall', ['Drywall/Painting'], 'Sheridan', '(307) 675-1030', null, true, false, 'Jerry recommended', '$2/SF smooth', 'Sabino.'],
            ['Mountain Valley Electric, LLC', ['Electrician'], 'Buffalo', '(307) 684-2288', null, true, false, null, '$9/SF', 'Brent Eggers.'],
            ['Cowboy State Plumbing', ['Plumber'], 'Sheridan', '16055936462', null, true, false, null, '$5/SF rough-in', 'Tyler Emme.'],
            ['TS Mechanical, Inc.', ['HVAC', 'Plumber'], 'Sheridan', '(307) 655-5773', null, true, false, null, null, null],
            ['Comfort Systems & AC Inc', ['HVAC'], 'Gillette', '(307) 682-4050', null, true, false, 'online', null, null],
            ['Shields Plumbing Heating AC & Electric', ['Electrician', 'HVAC', 'Plumber'], 'Gillette', '(307) 685-8112', null, true, false, 'online', null, null],
            ['Wagner Ranch Services LLC', ['Excavation'], 'Sheridan', '307-752-2937', null, true, false, 'online', '$150/hr side dump', 'Side dumps for hauling.'],
            ['Simon Contractors', ['Paving', 'Excavation'], 'Sheridan', '(307) 672-5199', null, true, false, 'online', '$50-65/SY', 'Simon is the best.'],
            ['Vertical Door Solutions', ['Garage Door Install'], 'Buffalo', '(307) 278-0181', null, true, false, null, null, 'Garage doors.'],
            ['Dave Loden Construction', ['Framing/General Construction', 'Roofing'], 'Buffalo', '(307) 684-5838', 'ronfarmhouse@outlook.com', true, false, 'online', null, 'Membrane roof.'],
            ['Rock Creek Roofing', ['Roofing', 'Gutters'], 'Buffalo', '(307) 620-0862', null, true, false, 'online', '5" $9/LFT', 'Gutters & downspouts.'],
            ['Eliborio Sanchez', ['Siding', 'Painting'], 'Sheridan', null, null, true, false, null, '$30/hr', 'EIFS install/demo; exterior paint.'],
            ['Brian Schick', ['Spray Foam', 'Insulation'], 'Sheridan', null, null, true, false, null, '$800/set install', 'Spray foam — Ryan Schick / blow-in.'],
            ['Seasonal Services', ['Landscaping'], 'Sheridan', null, null, true, false, null, null, 'Caleb — landscaping.'],
            ['Tree Mechanics', ['Excavation'], 'Sheridan', null, null, true, false, null, null, 'Tree removal.'],
            ['BigHorn Survey', ['Engineer'], 'Sheridan', null, null, true, false, null, null, 'Survey for development.'],
            ['Frontier Iron Works', ['Masonry'], null, null, null, true, false, null, '$190/LF railing', 'Stair railing, powder coat.'],
            ['Floord', ['Flooring'], 'Sheridan', '(307) 278-0300', 'ron@floord.org', true, true, 'online', null, 'Do not use.'],
            ['KXK Construction', ['Concrete', 'Siding'], 'Sheridan', '(307) 751-1392', null, true, false, null, null, null],
        ];

        foreach ($partners as [$name, $trades, $location, $phone, $email, $used, $dnu, $referral, $price, $notes]) {
            $partner = TradePartner::firstOrCreate(
                ['name' => $name],
                [
                    'location' => $location,
                    'phone' => $phone,
                    'email' => $email,
                    'used_before' => $used,
                    'negotiated_price' => $price,
                    'referral_source' => $referral,
                    'notes' => $notes,
                    'do_not_use' => $dnu,
                ],
            );

            foreach (array_unique($trades) as $trade) {
                $partner->trades()->firstOrCreate(['trade' => $trade]);
            }
        }
    }
}
