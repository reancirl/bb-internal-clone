<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectJob>
 */
class ProjectJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'predecessor_job_id' => null,
            'title' => fake()->randomElement(['Framing', 'Foundation pour', 'Roofing', 'Drywall', 'Punch list']),
            'scheduled_date' => Carbon::today()->toDateString(),
            'duration_days' => 1,
            'status' => ProjectJob::STATUS_SCHEDULED,
            'trade' => null,
            'notes' => null,
        ];
    }
}
