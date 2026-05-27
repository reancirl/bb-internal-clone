<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);
    }
}
