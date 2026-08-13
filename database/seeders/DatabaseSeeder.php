<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectJob;
use App\Models\TimeCard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent — safe to run on a fresh database OR an existing one
     * (staging / production) without `migrate:fresh`. Reference data uses
     * firstOrCreate so re-running never duplicates rows or overwrites edits;
     * the random demo time cards seed only on the very first run.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@buffalobuilt.test'],
            ['name' => 'Admin', 'password' => 'password', 'role' => User::ROLE_ADMIN, 'email_verified_at' => now()],
        );

        $foreman = User::firstOrCreate(
            ['email' => 'crew@buffalobuilt.test'],
            ['name' => 'Wyatt Carter', 'password' => 'password', 'role' => User::ROLE_CREW, 'email_verified_at' => now()],
        );

        $crew = collect([
            ['name' => 'Matt Hensley', 'email' => 'matt@buffalobuilt.test'],
            ['name' => 'Joe Brody', 'email' => 'joe@buffalobuilt.test'],
            ['name' => 'Eliborio Vargas', 'email' => 'eli@buffalobuilt.test'],
            ['name' => 'Ryan Schick', 'email' => 'ryan@buffalobuilt.test'],
        ])->map(fn ($u) => User::firstOrCreate(
            ['email' => $u['email']],
            ['name' => $u['name'], 'password' => 'password', 'role' => User::ROLE_CREW, 'email_verified_at' => now()],
        ));

        // Website integration user — used by the marketing site to POST leads via the API.
        // Token is scoped to 'leads:create' so it cannot access any other /api/* routes.
        $websiteUser = User::updateOrCreate(
            ['email' => 'website-integration@buffalobuiltusa.com'],
            ['name' => 'Website Integration', 'password' => Hash::make(Str::random(40)), 'role' => User::ROLE_CREW],
        );
        $websiteUser->tokens()->where('name', 'website')->delete();
        $this->command->info('Website integration token: '.$websiteUser->createToken('website', ['leads:create'])->plainTextToken);

        // Reference data (catalogs, directory, demo project) — all idempotent.
        $this->call(DirectorySeeder::class);
        $this->call(PriceBookSeeder::class);
        $this->call(ProjectSeeder::class);
        $this->call(LeadSeeder::class);
        $this->call(DecisionCatalogSeeder::class);
        $this->call(BudgetCatalogSeeder::class);

        // Sample time cards are random demo data, not reference data, so seed
        // them only once — otherwise every re-seed would pile on duplicates.
        if (TimeCard::count() === 0) {
            $this->seedDemoTimeCards($crew->concat([$foreman]), $foreman);
        }

        // Demo jobs on the demo project so the Calendar isn't empty. Seed once.
        if (ProjectJob::count() === 0) {
            $this->seedDemoJobs($crew->concat([$foreman]));
        }

        $this->command->info('Seeded users:');
        $this->command->info('  '.$admin->email.' (admin) · password: password');
        $this->command->info('  '.$foreman->email.' (crew foreman, currently clocked in) · password: password');
        foreach ($crew as $c) {
            $this->command->info('  '.$c->email.' (crew) · password: password');
        }
    }

    /**
     * @param  Collection<int, User>  $workers
     */
    private function seedDemoTimeCards(Collection $workers, User $foreman): void
    {
        $shiftNotes = [
            'Foundation pour at Staebler site',
            'Framing crew, west elevation',
            'Roofing — Diamond Truss delivery',
            'Drywall hanging, kitchen + 2 bedrooms',
            'Material run to Bloedorn',
            'EIFS exterior, accent wall',
            'Punch list cleanup, Mary\'s revamp',
            'Window install, master bedroom',
            null, null, null,
        ];

        foreach ($workers as $worker) {
            for ($daysBack = 12; $daysBack >= 1; $daysBack--) {
                $day = Carbon::today()->subDays($daysBack);
                if ($day->isWeekend() && random_int(1, 10) <= 7) {
                    continue;
                }
                if (random_int(1, 20) === 1) {
                    continue;
                }

                $clockIn = $day->copy()->setTime(random_int(6, 8), [0, 15, 30, 45][random_int(0, 3)]);
                $clockOut = $clockIn->copy()->addHours(random_int(7, 10))->addMinutes([0, 15, 30, 45][random_int(0, 3)]);

                TimeCard::create([
                    'user_id' => $worker->id,
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'notes' => $shiftNotes[random_int(0, count($shiftNotes) - 1)],
                ]);
            }
        }

        // One currently-open shift on the foreman — demonstrates the live "Clocked in · Elapsed h:mm:ss" state.
        TimeCard::create([
            'user_id' => $foreman->id,
            'clock_in_at' => Carbon::now()->subMinutes(random_int(20, 180)),
            'clock_out_at' => null,
            'notes' => 'On-site at Staebler — slab work',
        ]);
    }

    /**
     * @param  Collection<int, User>  $workers
     */
    private function seedDemoJobs(Collection $workers): void
    {
        $project = Project::first();
        if ($project === null) {
            return;
        }

        $jobs = [
            ['title' => 'Foundation pour', 'days' => 0, 'status' => ProjectJob::STATUS_IN_PROGRESS],
            ['title' => 'Framing — west elevation', 'days' => 2, 'status' => ProjectJob::STATUS_SCHEDULED],
            ['title' => 'Roofing — Diamond Truss delivery', 'days' => 5, 'status' => ProjectJob::STATUS_SCHEDULED],
        ];

        foreach ($jobs as $jobData) {
            $job = $project->jobs()->create([
                'title' => $jobData['title'],
                'scheduled_date' => Carbon::today()->addDays($jobData['days'])->toDateString(),
                'status' => $jobData['status'],
            ]);
            $job->crew()->sync($workers->pluck('id')->all());
        }
    }
}
