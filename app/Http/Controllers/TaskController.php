<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $user = $request->user();

        $tasks = $project->tasks()
            ->with(['assignee:id,name', 'createdBy:id,name', 'completedBy:id,name', 'checklistItems', 'photos'])
            ->orderByDesc('is_punch')
            ->orderBy('number')
            ->get()
            ->map(fn (ProjectTask $task) => $this->toRow($task, $user));

        $punch = $tasks->where('is_punch', true);

        return Inertia::render('projects/tasks', [
            'project' => $project->only(['id', 'name', 'client_name']),
            'punchSignedOffAt' => $project->punch_signed_off_at?->toDateString(),
            'punchReady' => $punch->isNotEmpty() && $punch->every(fn ($t) => $t['status'] === ProjectTask::STATUS_DONE),
            'tasks' => $tasks->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'statuses' => ProjectTask::STATUSES,
            'priorities' => ProjectTask::PRIORITIES,
            'categories' => ProjectTask::CATEGORIES,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Anyone can capture a task — walkthroughs are done by whoever is walking
     * the house, office or foreman alike.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validateData($request);

        $task = retry(3, fn () => DB::transaction(fn () => $project->tasks()->create([
            ...collect($data)->except('checklist')->all(),
            'number' => ProjectTask::nextNumber($project->id),
            'created_by_user_id' => $request->user()->id,
        ])), 100);

        $this->syncChecklist($task, $data['checklist'] ?? []);
        $this->notifyAssignee($task, null, $request->user());

        return back()->with('success', "Task #{$task->number} added.");
    }

    public function update(Request $request, ProjectTask $task): RedirectResponse
    {
        abort_unless($task->editableBy($request->user()), 403);

        $data = $this->validateData($request);
        $previousAssignee = $task->assigned_to_user_id;

        $task->update(collect($data)->except('checklist')->all());
        $this->syncChecklist($task, $data['checklist'] ?? []);
        $this->notifyAssignee($task->refresh(), $previousAssignee, $request->user());

        return back()->with('success', "Task #{$task->number} updated.");
    }

    /**
     * Status quick-change, open to the assignee so the person doing the fix
     * can close it from the field without edit rights.
     */
    public function updateStatus(Request $request, ProjectTask $task): RedirectResponse
    {
        abort_unless($task->workableBy($request->user()), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectTask::STATUSES)],
        ]);

        $done = $data['status'] === ProjectTask::STATUS_DONE;
        $task->update([
            'status' => $data['status'],
            'completed_at' => $done ? now() : null,
            'completed_by_user_id' => $done ? $request->user()->id : null,
        ]);

        return back()->with('success', $done ? "Task #{$task->number} done." : 'Status updated.');
    }

    public function toggleChecklistItem(Request $request, ProjectTask $task, int $item): RedirectResponse
    {
        abort_unless($task->workableBy($request->user()), 403);

        $row = $task->checklistItems()->findOrFail($item);
        $row->update(['done' => ! $row->done]);

        return back();
    }

    public function destroy(Request $request, ProjectTask $task): RedirectResponse
    {
        abort_unless($task->editableBy($request->user()), 403);

        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    /**
     * Record customer sign-off once every punch item is done. Admin only —
     * this is the closeout milestone, not a field action.
     */
    public function signOffPunchList(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $punch = $project->tasks()->where('is_punch', true);
        if (! $punch->exists()) {
            return back()->with('error', 'There are no punch-list items to sign off.');
        }
        if ($punch->clone()->where('status', '!=', ProjectTask::STATUS_DONE)->exists()) {
            return back()->with('error', 'All punch-list items must be done before sign-off.');
        }

        $project->update(['punch_signed_off_at' => now()]);

        return back()->with('success', 'Punch list signed off. Nice work.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', Rule::in(ProjectTask::PRIORITIES)],
            'is_punch' => ['boolean'],
            'assigned_to_user_id' => ['nullable', Rule::exists('users', 'id')],
            'due_date' => ['nullable', 'date'],
            'checklist' => ['array', 'max:20'],
            'checklist.*.label' => ['required', 'string', 'max:255'],
            'checklist.*.done' => ['boolean'],
        ]);
    }

    /**
     * Replace the checklist with the submitted rows (same pattern as PO items).
     *
     * @param  list<array{label: string, done?: bool}>  $rows
     */
    private function syncChecklist(ProjectTask $task, array $rows): void
    {
        $task->checklistItems()->delete();
        $sort = 1;
        foreach ($rows as $row) {
            $task->checklistItems()->create([
                'label' => $row['label'],
                'done' => $row['done'] ?? false,
                'sort' => $sort++,
            ]);
        }
    }

    private function notifyAssignee(ProjectTask $task, ?int $previousAssigneeId, User $actor): void
    {
        $assigneeId = $task->assigned_to_user_id;
        if ($assigneeId === null || $assigneeId === $previousAssigneeId || $assigneeId === $actor->id) {
            return;
        }

        $task->load('project:id,name');
        Notify::users(User::query()->whereKey($assigneeId)->get(), new TaskAssigned($task));
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(ProjectTask $task, User $user): array
    {
        return [
            'id' => $task->id,
            'number' => $task->number,
            'title' => $task->title,
            'description' => $task->description,
            'location' => $task->location,
            'category' => $task->category,
            'priority' => $task->priority,
            'is_punch' => $task->is_punch,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assignee' => $task->assignee?->name,
            'due_date' => $task->due_date?->toDateString(),
            'status' => $task->status,
            'completed_at' => $task->completed_at?->toDateString(),
            'completed_by' => $task->completedBy?->name,
            'created_by' => $task->createdBy?->name,
            'can_edit' => $task->editableBy($user),
            'can_work' => $task->workableBy($user),
            'checklist' => $task->checklistItems->map(fn ($i) => [
                'id' => $i->id,
                'label' => $i->label,
                'done' => $i->done,
            ])->values(),
            'photos' => $task->photos->map(fn ($p) => [
                'id' => $p->id,
                'stage' => $p->stage,
                'thumb_url' => route('tasks.photos.thumb', $p),
                'full_url' => route('tasks.photos.show', $p),
            ])->values(),
        ];
    }
}
