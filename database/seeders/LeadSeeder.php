<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Demo pipeline data. Leads are random demo data, not reference data,
     * so seed only when the table is empty — same rule as demo time cards.
     */
    public function run(): void
    {
        if (Lead::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@buffalobuilt.test')->first();
        $foreman = User::where('email', 'crew@buffalobuilt.test')->first();

        $leads = [
            ['first_name' => 'John', 'last_name' => 'Staebler', 'email' => 'john.staebler@example.com', 'phone' => '(307) 555-0101', 'build_location' => '412 Fort St, Buffalo, WY', 'project_details' => 'New custom home, 2,500 sqft ranch with attached 3-car garage.', 'status' => Lead::STATUS_NEW, 'priority' => 'high', 'source' => 'website', 'estimated_value_cents' => 45000000, 'next_follow_up_date' => Carbon::today()->addDays(2), 'assigned' => null],
            ['first_name' => 'Maria', 'last_name' => 'Gonzales', 'email' => 'maria.g@example.com', 'phone' => '(307) 555-0102', 'build_location' => 'Sheridan, WY', 'project_details' => 'Kitchen remodel — cabinets, counters, appliances.', 'status' => Lead::STATUS_NEW, 'priority' => 'medium', 'source' => 'referral', 'estimated_value_cents' => 6500000, 'next_follow_up_date' => Carbon::today()->addDay(), 'assigned' => null],
            ['first_name' => 'Tom', 'last_name' => 'Bradley', 'email' => 'tom.b@example.com', 'phone' => '(307) 555-0103', 'build_location' => 'Gillette, WY', 'project_details' => '30x40 shop with concrete slab and electrical.', 'status' => Lead::STATUS_CONTACTED, 'priority' => 'high', 'source' => 'website', 'estimated_value_cents' => 12000000, 'next_follow_up_date' => Carbon::today()->addDays(3), 'assigned' => 'admin'],
            ['first_name' => 'Susan', 'last_name' => 'Miller', 'email' => 'susan.m@example.com', 'phone' => '(307) 555-0104', 'build_location' => 'Big Horn, WY', 'project_details' => 'Master bath renovation with walk-in shower.', 'status' => Lead::STATUS_CONTACTED, 'priority' => 'low', 'source' => 'social_media', 'estimated_value_cents' => 3500000, 'next_follow_up_date' => Carbon::today()->subDay(), 'assigned' => 'admin'],
            ['first_name' => 'Dale', 'last_name' => 'Peterson', 'email' => 'dale.p@example.com', 'phone' => '(307) 555-0105', 'build_location' => 'Casper, WY', 'project_details' => 'Garage addition, 2-car detached with workshop space.', 'status' => Lead::STATUS_QUALIFIED, 'priority' => 'medium', 'source' => 'referral', 'estimated_value_cents' => 8500000, 'next_follow_up_date' => Carbon::today()->addDays(4), 'assigned' => 'foreman'],
            ['first_name' => 'Angela', 'last_name' => 'Wright', 'email' => 'angela.w@example.com', 'phone' => '(307) 555-0106', 'build_location' => 'Sheridan, WY', 'project_details' => 'Basement finish — bedroom, bath, and rec room.', 'status' => Lead::STATUS_QUALIFIED, 'priority' => 'high', 'source' => 'website', 'estimated_value_cents' => 7500000, 'next_follow_up_date' => Carbon::today()->addDays(2), 'assigned' => 'admin'],
            ['first_name' => 'Carl', 'last_name' => 'Hutchins', 'email' => 'carl.h@example.com', 'phone' => '(307) 555-0107', 'build_location' => 'Buffalo, WY', 'project_details' => 'Full roof replacement, architectural shingles.', 'status' => Lead::STATUS_MEETING_SCHEDULED, 'priority' => 'high', 'source' => 'trade_show', 'estimated_value_cents' => 4200000, 'next_follow_up_date' => Carbon::today()->addDays(5), 'assigned' => 'admin'],
            ['first_name' => 'Beth', 'last_name' => 'Norris', 'email' => 'beth.n@example.com', 'phone' => '(307) 555-0108', 'build_location' => 'Mills, WY', 'project_details' => 'Sunroom addition, approx 300 sqft.', 'status' => Lead::STATUS_PROPOSAL_SENT, 'priority' => 'medium', 'source' => 'website', 'estimated_value_cents' => 9800000, 'next_follow_up_date' => Carbon::today()->addDays(7), 'assigned' => 'admin'],
            ['first_name' => 'Gary', 'last_name' => 'Olsen', 'email' => 'gary.o@example.com', 'phone' => '(307) 555-0109', 'build_location' => 'Sheridan, WY', 'project_details' => 'Exterior siding + windows for 2,000 sqft home.', 'status' => Lead::STATUS_PROPOSAL_SENT, 'priority' => 'high', 'source' => 'referral', 'estimated_value_cents' => 15500000, 'next_follow_up_date' => Carbon::today()->addDays(6), 'assigned' => 'foreman'],
            ['first_name' => 'Victor', 'last_name' => 'Edwards', 'email' => 'victor.e@example.com', 'phone' => '(307) 555-0110', 'build_location' => 'Buffalo, WY', 'project_details' => 'Full home renovation — 3,000 sqft custom remodel.', 'status' => Lead::STATUS_WON, 'priority' => 'high', 'source' => 'referral', 'estimated_value_cents' => 85000000, 'next_follow_up_date' => null, 'assigned' => 'admin', 'won_at' => Carbon::now()->subDays(1)],
            ['first_name' => 'Frank', 'last_name' => 'Gray', 'email' => 'frank.g@example.com', 'phone' => '(307) 555-0111', 'build_location' => 'Gillette, WY', 'project_details' => 'Kitchen remodel — went with a cheaper competitor.', 'status' => Lead::STATUS_LOST, 'priority' => 'medium', 'source' => 'website', 'estimated_value_cents' => 3500000, 'next_follow_up_date' => null, 'assigned' => 'admin', 'lost_at' => Carbon::now()->subDays(3), 'lost_reason' => 'Went with competitor — price too high.'],
        ];

        foreach ($leads as $data) {
            $assigned = match ($data['assigned']) {
                'admin' => $admin?->id,
                'foreman' => $foreman?->id,
                default => null,
            };
            unset($data['assigned']);

            $lead = Lead::create([
                ...$data,
                'assigned_to_user_id' => $assigned,
                'submitted_at' => Carbon::now()->subDays(random_int(1, 20)),
            ]);

            $lead->activities()->create([
                'user_id' => $admin?->id,
                'activity_type' => 'note',
                'title' => 'Lead captured',
                'description' => 'Lead added to the pipeline from '.str_replace('_', ' ', $lead->source).'.',
                'completed_at' => $lead->submitted_at,
            ]);

            if (in_array($lead->status, [Lead::STATUS_CONTACTED, Lead::STATUS_QUALIFIED, Lead::STATUS_MEETING_SCHEDULED, Lead::STATUS_PROPOSAL_SENT, Lead::STATUS_WON], true)) {
                $lead->activities()->create([
                    'user_id' => $admin?->id,
                    'activity_type' => 'call',
                    'title' => 'Initial contact call',
                    'description' => 'Discussed project scope, budget expectations, and timeline.',
                    'completed_at' => $lead->submitted_at->copy()->addDay(),
                ]);
            }

            if ($lead->status === Lead::STATUS_MEETING_SCHEDULED) {
                $lead->activities()->create([
                    'user_id' => $admin?->id,
                    'activity_type' => 'meeting',
                    'title' => 'Site visit scheduled',
                    'description' => 'On-site consultation to take measurements and discuss options.',
                    'scheduled_at' => Carbon::now()->addDays(3),
                ]);
            }

            if ($lead->status === Lead::STATUS_PROPOSAL_SENT) {
                $lead->activities()->create([
                    'user_id' => $admin?->id,
                    'activity_type' => 'email',
                    'title' => 'Proposal sent',
                    'description' => 'Detailed proposal emailed, valid for 30 days.',
                    'completed_at' => Carbon::now()->subDays(2),
                ]);
            }
        }

        $this->command->info('Seeded '.count($leads).' demo CRM leads.');
    }
}
