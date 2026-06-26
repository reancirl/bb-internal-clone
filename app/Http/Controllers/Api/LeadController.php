<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:50',
            'buildLocation' => 'nullable|string|max:255',
            'projectDetails' => 'nullable|string',
            'createdAt' => 'required|date',
        ]);

        $lead = Lead::create([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'build_location' => $validated['buildLocation'] ?? null,
            'project_details' => $validated['projectDetails'] ?? null,
            'source' => 'website',
            'submitted_at' => $validated['createdAt'],
        ]);

        return response()->json([
            'success' => true,
            'id' => $lead->id,
        ], 201);
    }
}
