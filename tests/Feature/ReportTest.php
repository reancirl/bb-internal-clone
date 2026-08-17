<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TakeoffLine;
use App\Models\TimeCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_cannot_access_reports(): void
    {
        $crew = User::factory()->create(['role' => User::ROLE_CREW]);

        $this->actingAs($crew)->get('/admin/reports/time-summary')->assertForbidden();
        $this->actingAs($crew)->get('/admin/reports/material-status')->assertForbidden();
        $this->actingAs($crew)->get('/admin/reports/time-summary/export')->assertForbidden();
        $this->actingAs($crew)->get('/admin/reports/material-status/export')->assertForbidden();
    }

    public function test_time_summary_aggregates_hours_per_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['name' => 'Joe Crew', 'role' => User::ROLE_CREW]);

        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => now()->startOfWeek()->addHours(8),
            'clock_out_at' => now()->startOfWeek()->addHours(12),
        ]);
        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => now()->startOfWeek()->addDay()->addHours(8),
            'clock_out_at' => now()->startOfWeek()->addDay()->addHours(10)->addMinutes(30),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/time-summary')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/time-summary')
                ->has('rows', 1)
                ->where('rows.0.name', 'Joe Crew')
                ->where('rows.0.cards_count', 2)
                ->where('rows.0.open_cards_count', 0)
                ->where('rows.0.total_minutes', 390));
    }

    public function test_time_summary_excludes_open_cards_from_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['role' => User::ROLE_CREW]);

        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => now()->startOfWeek()->addHours(8),
            'clock_out_at' => now()->startOfWeek()->addHours(9),
        ]);
        TimeCard::factory()->for($worker)->open()->create([
            'clock_in_at' => now()->startOfWeek()->addDay()->addHours(8),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/time-summary')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.cards_count', 2)
                ->where('rows.0.open_cards_count', 1)
                ->where('rows.0.total_minutes', 60));
    }

    public function test_time_summary_respects_date_range_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['role' => User::ROLE_CREW]);

        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => '2026-08-03 08:00:00',
            'clock_out_at' => '2026-08-03 12:00:00',
        ]);
        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => '2026-08-10 08:00:00',
            'clock_out_at' => '2026-08-10 12:00:00',
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/time-summary?from=2026-08-01&to=2026-08-07')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.cards_count', 1)
                ->where('rows.0.total_minutes', 240)
                ->where('filters.from', '2026-08-01')
                ->where('filters.to', '2026-08-07'));
    }

    public function test_time_summary_all_time_range_includes_everything(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['role' => User::ROLE_CREW]);

        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => '2026-01-05 08:00:00',
            'clock_out_at' => '2026-01-05 09:00:00',
        ]);
        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => now()->subYears(2),
            'clock_out_at' => now()->subYears(2)->addHours(2),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/time-summary?range=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.cards_count', 2)
                ->where('rows.0.total_minutes', 180)
                ->where('filters.from', null)
                ->where('filters.to', null));
    }

    public function test_time_summary_csv_export(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['name' => 'Joe Crew', 'role' => User::ROLE_CREW]);

        TimeCard::factory()->for($worker)->create([
            'clock_in_at' => now()->startOfWeek()->addHours(8),
            'clock_out_at' => now()->startOfWeek()->addHours(16),
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports/time-summary/export');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Joe Crew', $csv);
        $this->assertStringContainsString('8.00', $csv);
    }

    public function test_material_status_groups_lines_by_project_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Staebler Build']);

        TakeoffLine::factory()->for($project)->create(['category' => 'CONCRETE', 'ordered' => true, 'on_site' => true]);
        TakeoffLine::factory()->for($project)->create(['category' => 'CONCRETE', 'ordered' => true, 'on_site' => false]);
        TakeoffLine::factory()->for($project)->create(['category' => 'FRAMING', 'ordered' => false, 'on_site' => false]);

        $this->actingAs($admin)
            ->get('/admin/reports/material-status')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/material-status')
                ->has('projects', 1)
                ->where('projects.0.name', 'Staebler Build')
                ->has('projects.0.categories', 2)
                ->where('projects.0.categories.0.category', 'CONCRETE')
                ->where('projects.0.categories.0.total', 2)
                ->where('projects.0.categories.0.ordered', 2)
                ->where('projects.0.categories.0.on_site', 1)
                ->where('projects.0.categories.0.outstanding', 0)
                ->where('projects.0.categories.1.category', 'FRAMING')
                ->where('projects.0.categories.1.outstanding', 1)
                ->where('projects.0.totals.total', 3)
                ->where('projects.0.totals.ordered', 2)
                ->where('projects.0.totals.on_site', 1)
                ->where('projects.0.totals.outstanding', 1));
    }

    public function test_material_status_excludes_projects_without_lines(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Empty Project']);

        $this->actingAs($admin)
            ->get('/admin/reports/material-status')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects', 0));
    }

    public function test_material_status_csv_export(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Staebler Build', 'client_name' => 'James Staebler']);
        TakeoffLine::factory()->for($project)->create(['category' => 'CONCRETE', 'ordered' => true]);

        $response = $this->actingAs($admin)->get('/admin/reports/material-status/export');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Staebler Build', $csv);
        $this->assertStringContainsString('James Staebler', $csv);
        $this->assertStringContainsString('CONCRETE', $csv);
    }
}
