<?php

namespace App\Http\Middleware;

use App\Models\TimeCard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Unique per response so the client can distinguish two
                // consecutive flashes with identical text.
                'id' => fn () => $request->session()->has('success') || $request->session()->has('error')
                    ? (string) Str::uuid()
                    : null,
            ],
            'openTimeCard' => fn () => $this->openTimeCardFor($request),
        ]);
    }

    /**
     * @return array{id: int, clock_in_at: string, notes: string|null}|null
     */
    private function openTimeCardFor(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $card = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first(['id', 'clock_in_at', 'notes']);

        if ($card === null) {
            return null;
        }

        return [
            'id' => $card->id,
            'clock_in_at' => $card->clock_in_at->toIso8601String(),
            'notes' => $card->notes,
        ];
    }
}
