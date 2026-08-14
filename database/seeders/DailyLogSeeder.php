<?php

namespace Database\Seeders;

use App\Models\DailyLog;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DailyLogSeeder extends Seeder
{
    /**
     * Demo field logs. Random demo data — seed only when the table is empty,
     * same rule as demo time cards and leads.
     */
    public function run(): void
    {
        if (DailyLog::count() > 0) {
            return;
        }

        $project = Project::first();
        if ($project === null) {
            return;
        }

        $foreman = User::where('email', 'crew@buffalobuilt.test')->first();
        $admin = User::where('email', 'admin@buffalobuilt.test')->first();

        $entries = [
            [3, 'Poured footings on the north wall. Inspection passed at 9am, pour finished by 2pm. Forms stripped tomorrow.', 'Sunny', 82, 'Wyatt, Matt, Joe + Total Concrete crew (3)', null, $foreman],
            [2, 'Stripped forms, backfilled around the foundation. Started laying out the mono-slab plumbing rough-in with the sub.', 'Partly cloudy', 76, 'Wyatt, Eli + plumbing sub (2)', 'Plumbing sub short-handed — rough-in will take one extra day.', $foreman],
            [1, 'Slab prep complete: vapor barrier down, rebar tied, in-floor heat loops pressure-tested at 60psi, holding.', 'Overcast', 68, 'Wyatt, Matt, Joe, Eli', null, $foreman],
            [0, 'Slab poured and power-troweled. Kept one man on site through the afternoon for curing checks. Framing package delivery confirmed for Monday.', 'Sunny', 84, 'Full crew + Total Concrete (4)', 'Afternoon wind picked up — covered the east edge to slow the cure.', $admin],
        ];

        foreach ($entries as [$daysAgo, $notes, $weather, $temp, $crew, $issues, $author]) {
            DailyLog::create([
                'project_id' => $project->id,
                'user_id' => $author?->id,
                'log_date' => Carbon::today()->subDays($daysAgo)->toDateString(),
                'notes' => $notes,
                'weather' => $weather,
                'temperature_f' => $temp,
                'crew_present' => $crew,
                'issues' => $issues,
            ]);
        }

        $this->command->info('Seeded '.count($entries).' demo daily logs.');
    }
}
