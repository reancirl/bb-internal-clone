<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'users_total' => User::count(),
                'admins_total' => User::where('role', User::ROLE_ADMIN)->count(),
                'crew_total' => User::where('role', User::ROLE_CREW)->count(),
            ],
            'leads' => Lead::where('funnel', Lead::FUNNEL_GENERAL)->orderByDesc('created_at')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role']),
            'recentActivities' => LeadActivity::with(['lead:id,first_name,last_name,status', 'user:id,name'])
                ->whereHas('lead', fn ($query) => $query->where('funnel', Lead::FUNNEL_GENERAL))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(fn (LeadActivity $a) => [
                    'id' => $a->id,
                    'lead_id' => $a->lead_id,
                    'lead_name' => $a->lead ? trim($a->lead->first_name.' '.$a->lead->last_name) : 'Lead',
                    'activity_type' => $a->activity_type,
                    'title' => $a->title,
                    'actor_name' => $a->user->name ?? 'System',
                    'created_at' => $a->created_at?->toISOString(),
                ]),
        ]);
    }
}
