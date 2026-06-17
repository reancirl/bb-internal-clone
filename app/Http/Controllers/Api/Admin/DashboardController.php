<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'users_total' => User::count(),
                'admins_total' => User::where('role', User::ROLE_ADMIN)->count(),
                'crew_total' => User::where('role', User::ROLE_CREW)->count(),
            ],
        ]);
    }
}
