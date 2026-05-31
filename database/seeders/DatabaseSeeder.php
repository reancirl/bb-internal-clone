<?php

namespace Database\Seeders;

use App\Models\TimeCard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@buffalobuilt.test',
        ]);

        $foreman = User::factory()->create([
            'name' => 'Wyatt Carter',
            'email' => 'crew@buffalobuilt.test',
        ]);

        $crew = collect([
            ['name' => 'Matt Hensley', 'email' => 'matt@buffalobuilt.test'],
            ['name' => 'Joe Brody', 'email' => 'joe@buffalobuilt.test'],
            ['name' => 'Eliborio Vargas', 'email' => 'eli@buffalobuilt.test'],
            ['name' => 'Ryan Schick', 'email' => 'ryan@buffalobuilt.test'],
        ])->map(fn ($u) => User::factory()->create($u));

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

        $workers = $crew->concat([$foreman]);

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

        $this->call(DirectorySeeder::class);

        $this->command->info('Seeded users:');
        $this->command->info('  '.$admin->email.' (admin) · password: password');
        $this->command->info('  '.$foreman->email.' (crew foreman, currently clocked in) · password: password');
        foreach ($crew as $c) {
            $this->command->info('  '.$c->email.' (crew) · password: password');
        }
    }
}
