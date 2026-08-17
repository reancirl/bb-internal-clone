<?php

namespace App\Support;

use App\Models\ProjectJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes and applies downstream date shifts along predecessor chains.
 *
 * When a job's end date moves by N days, every not-yet-started successor
 * (and their successors, transitively) moves by the same N days, preserving
 * any gaps the scheduler left between jobs. Jobs that are in progress, done,
 * or canceled never move, and traversal stops beneath them — their own end
 * dates did not change, so their successors keep their dates too.
 */
class ScheduleShifter
{
    /**
     * Preview the downstream impact of moving $job so its end date changes
     * by $deltaDays. Returns one row per affected job.
     *
     * @return list<array{id: int, title: string|null, old_date: string, new_date: string, status: string}>
     */
    public function preview(ProjectJob $job, int $deltaDays): array
    {
        if ($deltaDays === 0) {
            return [];
        }

        $affected = [];
        $queue = [$job->id];
        $visited = [$job->id => true];

        while ($queue !== []) {
            $successors = ProjectJob::query()
                ->whereIn('predecessor_job_id', $queue)
                ->orderBy('scheduled_date')
                ->orderBy('id')
                ->get();
            $queue = [];

            foreach ($successors as $successor) {
                if (isset($visited[$successor->id])) {
                    continue; // guard against pre-existing cycles in data
                }
                $visited[$successor->id] = true;

                if (! $successor->isShiftable()) {
                    continue;
                }

                $affected[] = [
                    'id' => $successor->id,
                    'title' => $successor->title,
                    'old_date' => $successor->scheduled_date->toDateString(),
                    'new_date' => $successor->scheduled_date->copy()->addDays($deltaDays)->toDateString(),
                    'status' => $successor->status,
                ];
                $queue[] = $successor->id;
            }
        }

        return $affected;
    }

    /**
     * Apply the shifts from preview() in a single transaction.
     */
    public function apply(ProjectJob $job, int $deltaDays): int
    {
        $affected = $this->preview($job, $deltaDays);

        DB::transaction(function () use ($affected, $deltaDays) {
            foreach ($affected as $row) {
                ProjectJob::whereKey($row['id'])->update([
                    'scheduled_date' => Carbon::parse($row['old_date'])->addDays($deltaDays)->toDateString(),
                ]);
            }
        });

        return count($affected);
    }

    /**
     * Whether pointing $job at $predecessorId would create a dependency cycle.
     */
    public function wouldCreateCycle(ProjectJob $job, int $predecessorId): bool
    {
        $current = $predecessorId;
        $visited = [];

        while ($current !== null) {
            if ($current === $job->id) {
                return true;
            }
            if (isset($visited[$current])) {
                return true; // pre-existing cycle upstream; refuse to extend it
            }
            $visited[$current] = true;

            $current = ProjectJob::whereKey($current)->value('predecessor_job_id');
        }

        return false;
    }
}
