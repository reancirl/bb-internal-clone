<?php

namespace App\Http\Controllers;

use App\Models\TimeCard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeCardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $viewingUserId = (int) ($request->integer('employee_id') ?: $user->id);
        if ($viewingUserId !== $user->id && ! $user->isAdmin()) {
            $viewingUserId = $user->id;
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : Carbon::now()->startOfWeek();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : Carbon::now()->endOfWeek();

        $cards = TimeCard::query()
            ->where('user_id', $viewingUserId)
            ->whereBetween('clock_in_at', [$from, $to])
            ->orderByDesc('clock_in_at')
            ->get()
            ->map(fn (TimeCard $c) => [
                'id' => $c->id,
                'clock_in_at' => $c->clock_in_at?->toIso8601String(),
                'clock_out_at' => $c->clock_out_at?->toIso8601String(),
                'duration_minutes' => $c->durationMinutes(),
                'notes' => $c->notes,
            ]);

        return Inertia::render('time-card/index', [
            'cards' => $cards,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'employee_id' => $viewingUserId,
            ],
            'employees' => $user->isAdmin()
                ? User::query()->orderBy('name')->get(['id', 'name', 'role'])
                : null,
            'isAdmin' => $user->isAdmin(),
            'viewingUserId' => $viewingUserId,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        $hasOpen = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->exists();

        if ($hasOpen) {
            return back()->with('error', 'You are already clocked in.');
        }

        TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Clocked in.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        $card = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if (! $card) {
            return back()->with('error', 'No open time card to close.');
        }

        $extraNotes = $data['notes'] ?? null;
        $card->update([
            'clock_out_at' => now(),
            'notes' => $extraNotes !== null && $extraNotes !== ''
                ? trim(($card->notes ? $card->notes."\n" : '').$extraNotes)
                : $card->notes,
        ]);

        return back()->with('success', 'Clocked out.');
    }
}
