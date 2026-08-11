<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 20, 30], true)) {
            $perPage = 10;
        }

        return Inertia::render('leads/index', [
            'leads' => Lead::orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
        ]);
    }

    public function pipeline(): Response
    {
        return Inertia::render('admin/leads/index', [
            'leads' => Lead::orderByDesc('created_at')->get(),
            'users' => $this->assignableUsers(),
        ]);
    }

    public function analytics(): Response
    {
        return Inertia::render('admin/leads/analytics', [
            'leads' => Lead::orderByDesc('created_at')->get(),
            'users' => $this->assignableUsers(),
        ]);
    }

    public function show(Lead $lead): Response
    {
        return Inertia::render('admin/leads/show', [
            'lead' => $lead,
            'activities' => $lead->activities()->with('user:id,name')->get()
                ->map(fn (LeadActivity $a) => $this->serializeActivity($a)),
            'users' => $this->assignableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'build_location' => ['nullable', 'string', 'max:255'],
            'project_details' => ['nullable', 'string'],
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'status' => ['nullable', Rule::in(Lead::STATUSES)],
            'priority' => ['nullable', Rule::in(Lead::PRIORITIES)],
            'estimated_value_cents' => ['nullable', 'integer', 'min:0'],
            'next_follow_up_date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $lead = Lead::create([
            ...$data,
            'source' => $data['source'] ?? 'other',
            'status' => $data['status'] ?? Lead::STATUS_NEW,
            'priority' => $data['priority'] ?? 'medium',
            'submitted_at' => now(),
        ]);

        $lead->activities()->create([
            'user_id' => $request->user()->id,
            'activity_type' => 'note',
            'title' => 'Lead created',
            'description' => 'Lead added to the '.str_replace('_', ' ', $lead->status).' pipeline.',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Lead created.');
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'build_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_details' => ['sometimes', 'nullable', 'string'],
            'source' => ['sometimes', Rule::in(Lead::SOURCES)],
            'status' => ['sometimes', Rule::in(Lead::STATUSES)],
            'priority' => ['sometimes', Rule::in(Lead::PRIORITIES)],
            'estimated_value_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'next_follow_up_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'lost_reason' => ['sometimes', 'nullable', 'string'],
        ]);

        $previousStatus = $lead->status;
        $lead->fill($data);

        if (array_key_exists('status', $data) && $data['status'] !== $previousStatus) {
            $lead->won_at = $data['status'] === Lead::STATUS_WON ? now() : null;
            $lead->lost_at = $data['status'] === Lead::STATUS_LOST ? now() : null;
            if ($data['status'] !== Lead::STATUS_LOST) {
                $lead->lost_reason = null;
            }

            $lead->activities()->create([
                'user_id' => $request->user()->id,
                'activity_type' => 'note',
                'title' => 'Status changed to '.str_replace('_', ' ', $data['status']),
                'description' => 'Lead moved from '.str_replace('_', ' ', $previousStatus).' to '.str_replace('_', ' ', $data['status']).'.',
                'completed_at' => now(),
            ]);
        }

        $lead->save();

        $message = $lead->wasChanged('status')
            ? 'Lead moved to '.ucwords(str_replace('_', ' ', $lead->status)).'.'
            : 'Lead updated.';

        return back()->with('success', $message);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted.');
    }

    public function storeActivity(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'activity_type' => ['required', Rule::in(LeadActivity::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $lead->activities()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Activity logged.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        if ($lead->converted_project_id !== null) {
            return redirect()->route('projects.show', $lead->converted_project_id);
        }

        $project = Project::create([
            'name' => trim($lead->first_name.' '.$lead->last_name).' — '.($lead->build_location ?: 'New build'),
            'client_name' => trim($lead->first_name.' '.$lead->last_name),
            'address' => $lead->build_location ?? '',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $lead->update(['converted_project_id' => $project->id]);

        $lead->activities()->create([
            'user_id' => $request->user()->id,
            'activity_type' => 'note',
            'title' => 'Converted to project',
            'description' => 'Project "'.$project->name.'" created from this lead.',
            'completed_at' => now(),
        ]);

        return redirect()->route('projects.show', $project)->with('success', 'Project created from lead.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    private function assignableUsers()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActivity(LeadActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'lead_id' => $activity->lead_id,
            'activity_type' => $activity->activity_type,
            'title' => $activity->title,
            'description' => $activity->description,
            'scheduled_at' => $activity->scheduled_at?->toISOString(),
            'completed_at' => $activity->completed_at?->toISOString(),
            'created_by' => [
                'id' => $activity->user->id ?? 0,
                'name' => $activity->user->name ?? 'System',
            ],
            'created_at' => $activity->created_at->toISOString(),
        ];
    }
}
