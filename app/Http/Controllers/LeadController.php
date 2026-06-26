<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
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
}
