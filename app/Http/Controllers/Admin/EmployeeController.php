<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $showInactive = $request->boolean('show_inactive');

        $employees = User::query()
            ->when($showInactive, fn ($q) => $q->withTrashed())
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'active' => $u->deleted_at === null,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => ['show_inactive' => $showInactive],
            'roles' => User::ROLES,
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', Password::defaults()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Employee added.');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        // Don't allow demoting the last remaining admin.
        if ($employee->isAdmin() && $data['role'] !== User::ROLE_ADMIN && $this->isLastAdmin($employee)) {
            return back()->with('error', 'You cannot remove the last administrator.');
        }

        $employee->update($data);

        return back()->with('success', 'Employee updated.');
    }

    public function resetPassword(Request $request, User $employee): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $employee->update(['password' => $data['password']]);

        return back()->with('success', 'Password reset.');
    }

    /**
     * Deactivate (soft-delete) an employee so they can no longer sign in.
     */
    public function destroy(Request $request, User $employee): RedirectResponse
    {
        if ($employee->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($employee->isAdmin() && $this->isLastAdmin($employee)) {
            return back()->with('error', 'You cannot deactivate the last administrator.');
        }

        // Revoke API tokens as well (SEC-002). Sanctum resolves a bearer token
        // before the soft-delete scope applies, so a deactivated employee's
        // phone would keep working — and reactivating them would silently
        // restore every old token.
        $employee->tokens()->delete();
        $employee->delete();

        return back()->with('success', 'Employee deactivated.');
    }

    /**
     * Reactivate a previously deactivated employee. Route uses withTrashed().
     */
    public function restore(User $employee): RedirectResponse
    {
        $employee->restore();

        return back()->with('success', 'Employee reactivated.');
    }

    private function isLastAdmin(User $user): bool
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereKeyNot($user->id)
            ->count() === 0;
    }
}
