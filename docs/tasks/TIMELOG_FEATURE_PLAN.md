# Implementation Plan: Time Log / Clock-In Feature

> Copy/paste this whole file into Claude inside the target project. It's self-contained — Claude can follow it end-to-end.

## Target stack assumption

This plan assumes the target project uses **Laravel 11+ with Inertia.js + React + TypeScript + Tailwind**, with shadcn/ui-style components (`Button`, `Card`, `Input`, `Select` from `@/components/ui/...`) and Lucide icons. If anything in the stack differs, adapt the imports but keep the data flow identical.

## What this feature does

- Every authenticated user can **clock in** and **clock out**, recording timestamps as a "time card."
- Only **one open card per user at any time** (a card with `clock_out_at = null`).
- A persistent **header widget** shows a live ticking stopwatch while clocked in, and a "Clock In" button otherwise. Available on every page.
- A dedicated **Time Card page** shows the user's history with date-range filters, total hours, and an optional notes field.
- **Admins** can view any user's time cards by passing a `user_id` filter; non-admins are always restricted to their own.

## Data model

One new table: `time_cards`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → users.id | `cascadeOnDelete` |
| `clock_in_at` | timestamp | Required |
| `clock_out_at` | nullable timestamp | `null` means open |
| `notes` | nullable text | Optional shift notes |
| `created_at`, `updated_at` | timestamps | |

Index: `(user_id, clock_in_at)`.

## Prerequisites in target project

Before starting, verify the target project has:
- A `User` model with auth set up.
- An `isAdmin()` method on `User` (or equivalent role check). If not, either add a `role` column with `admin`/`user` values + `isAdmin()` method, or replace the admin check with whatever role system exists.
- `HandleInertiaRequests` middleware in `app/Http/Middleware/` (standard Inertia install).
- A shared app layout that renders a header (the clock widget lives in the header).
- shadcn/ui `Button`, `Card`, `Input`, `Select` components. If missing, install them or substitute with whatever the project uses.
- `lucide-react` for icons (or swap the icons for whatever icon set is in use).
- A toast/flash mechanism. The reference project uses `Inertia::flash('toast', ...)`. If the target uses something different (e.g. `session()->flash()` + a toaster like `sonner`), adapt the controller flash calls.

## Implementation steps

### Step 1 — Migration

Create the migration:

```bash
php artisan make:migration create_time_cards_table
```

Replace the contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_cards');
    }
};
```

Run it:

```bash
php artisan migrate
```

### Step 2 — Model

Create `app/Models/TimeCard.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'clock_in_at',
    'clock_out_at',
    'notes',
])]
class TimeCard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->clock_out_at === null;
    }

    protected function durationMinutes(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->clock_in_at === null || $this->clock_out_at === null) {
                return null;
            }

            return max(0, (int) $this->clock_in_at->diffInMinutes($this->clock_out_at));
        });
    }
}
```

> Note: `#[Fillable]` is the PHP 8 attribute form (Laravel 11+). If your project uses Laravel 10 or earlier, replace with `protected $fillable = [...]`.

Add a `timeCards()` relationship to `User`:

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<TimeCard, $this>
 */
public function timeCards(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(TimeCard::class);
}
```

### Step 3 — Form Request

Create `app/Http/Requests/TimeCardClockRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TimeCardClockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

### Step 4 — Controller

Create `app/Http/Controllers/TimeCardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeCardClockRequest;
use App\Models\TimeCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeCardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $viewingUserId = $request->integer('user_id') ?: $user?->id;

        // Admins can view any user's card; non-admins are restricted to their own.
        if (! $user?->isAdmin()) {
            $viewingUserId = $user?->id;
        }

        $from = $request->date('from')?->toDateString()
            ?? CarbonImmutable::now()->startOfWeek()->toDateString();
        $to = $request->date('to')?->toDateString()
            ?? CarbonImmutable::now()->endOfWeek()->toDateString();

        $cards = TimeCard::query()
            ->where('user_id', $viewingUserId)
            ->whereDate('clock_in_at', '>=', $from)
            ->whereDate('clock_in_at', '<=', $to)
            ->orderByDesc('clock_in_at')
            ->get();

        $cards->each(fn ($card) => $card->append('duration_minutes'));

        $openCard = TimeCard::query()
            ->where('user_id', $user?->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();
        $openCard?->append('duration_minutes');

        $employees = $user?->isAdmin()
            ? User::query()->orderBy('name')->get(['id', 'name', 'role'])
            : collect();

        return Inertia::render('time-card/index', [
            'cards' => $cards,
            'openCard' => $openCard,
            'filters' => ['from' => $from, 'to' => $to, 'user_id' => $viewingUserId],
            'employees' => $employees,
            'isAdmin' => (bool) $user?->isAdmin(),
            'viewingUserId' => $viewingUserId,
        ]);
    }

    public function clockIn(TimeCardClockRequest $request): RedirectResponse
    {
        $user = $request->user();

        $open = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->exists();

        if ($open) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You are already clocked in.']);

            return back();
        }

        TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now(),
            'notes' => $request->validated()['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Clocked in.']);

        return back();
    }

    public function clockOut(TimeCardClockRequest $request): RedirectResponse
    {
        $user = $request->user();

        $card = TimeCard::query()
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if (! $card) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No open clock-in to close.']);

            return back();
        }

        $notes = $request->validated()['notes'] ?? null;
        $card->update([
            'clock_out_at' => now(),
            'notes' => $notes !== null && $notes !== ''
                ? trim(($card->notes ? $card->notes."\n" : '').$notes)
                : $card->notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Clocked out.']);

        return back();
    }
}
```

> If `Inertia::flash` isn't available in the target project's Inertia version, replace with `session()->flash('toast', [...])` and read it from `HandleInertiaRequests::share` as a shared `flash` prop.

### Step 5 — Routes

In `routes/web.php`, inside an `auth`-protected group:

```php
use App\Http\Controllers\TimeCardController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes ...

    Route::get('time-card', [TimeCardController::class, 'index'])->name('time-card.index');
    Route::post('time-card/clock-in', [TimeCardController::class, 'clockIn'])->name('time-card.clock-in');
    Route::post('time-card/clock-out', [TimeCardController::class, 'clockOut'])->name('time-card.clock-out');
});
```

### Step 6 — Share `openTimeCard` globally via Inertia

Edit `app/Http/Middleware/HandleInertiaRequests.php`. Add to the `share()` method:

```php
'openTimeCard' => fn () => $this->openTimeCardFor($request),
```

And add the helper method to the class:

```php
/**
 * @return array{id: int, clock_in_at: string}|null
 */
private function openTimeCardFor(Request $request): ?array
{
    $user = $request->user();

    if ($user === null) {
        return null;
    }

    $card = \App\Models\TimeCard::query()
        ->where('user_id', $user->id)
        ->whereNull('clock_out_at')
        ->latest('clock_in_at')
        ->first(['id', 'clock_in_at']);

    if ($card === null) {
        return null;
    }

    return [
        'id' => $card->id,
        'clock_in_at' => $card->clock_in_at->toIso8601String(),
    ];
}
```

This makes `openTimeCard` available on every Inertia page via `usePage().props.openTimeCard`, so the header widget can show real-time state without each page reloading it.

> The closure `fn () => ...` makes it a **lazy** shared prop — it only runs the query when included in the response, which Inertia does automatically on full visits. If you want it to run on partial reloads too, name it in the controller's `Inertia::render(...)` array or use `Inertia::optional()` style depending on your Inertia version.

### Step 7 — TypeScript types

Create or extend a shared types file (e.g. `resources/js/types/time-card.ts`):

```typescript
export type TimeCardSummary = {
    id: number;
    user_id: number;
    clock_in_at: string;
    clock_out_at: string | null;
    notes: string | null;
    duration_minutes?: number | null;
    user?: { id: number; name: string };
};

export function formatDuration(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) {
        return '—';
    }

    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    if (h === 0) {
        return `${m}m`;
    }

    return `${h}h ${m}m`;
}
```

If the target project has a global `Auth`/`SharedProps` type, extend it to include `openTimeCard`:

```typescript
// resources/js/types/inertia.ts (or wherever PageProps lives)
export type SharedProps = {
    auth: { user: { id: number; name: string; email: string; role?: string } | null };
    openTimeCard: { id: number; clock_in_at: string } | null;
    // ... existing shared props
};
```

### Step 8 — Header clock-in/out widget

Create `resources/js/components/clock-in-out-button.tsx`:

```tsx
import { router, usePage } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type SharedProps = {
    auth: { user: { id: number } | null };
    openTimeCard: { id: number; clock_in_at: string } | null;
};

function formatElapsed(startIso: string, now: number): string {
    const startMs = new Date(startIso).getTime();

    if (Number.isNaN(startMs)) {
        return '';
    }

    const totalSeconds = Math.max(0, Math.floor((now - startMs) / 1000));
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${h}:${pad(m)}:${pad(s)}`;
}

export function ClockInOutButton() {
    const { auth, openTimeCard } = usePage<SharedProps>().props;
    const [now, setNow] = useState(() => Date.now());
    const clockInAt = openTimeCard?.clock_in_at ?? null;

    // Tick once per second so elapsed reads like a stopwatch.
    // Depending on the ISO string (a primitive) keeps the interval stable
    // across unrelated re-renders of the parent.
    useEffect(() => {
        if (!clockInAt) {
            return;
        }

        setNow(Date.now());
        const id = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(id);
    }, [clockInAt]);

    if (!auth?.user) {
        return null;
    }

    const clockIn = () => {
        router.post(
            '/time-card/clock-in',
            {},
            { preserveScroll: true, preserveState: false },
        );
    };

    const clockOut = () => {
        router.post(
            '/time-card/clock-out',
            {},
            { preserveScroll: true, preserveState: false },
        );
    };

    if (clockInAt) {
        const elapsed = formatElapsed(clockInAt, now);

        return (
            <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">
                    <span className="hidden sm:inline">Clocked in </span>
                    <span
                        className="font-mono font-medium text-foreground tabular-nums"
                        aria-live="polite"
                    >
                        {elapsed}
                    </span>
                </span>
                <Button variant="destructive" size="sm" onClick={clockOut} className="gap-1">
                    <LogOut className="size-4" />
                    <span className="hidden sm:inline">Clock Out</span>
                </Button>
            </div>
        );
    }

    return (
        <Button size="sm" onClick={clockIn} className="gap-1">
            <LogIn className="size-4" />
            <span className="hidden sm:inline">Clock In</span>
        </Button>
    );
}
```

Then mount it in the app header (whatever file renders the top nav — usually `resources/js/components/app-header.tsx` or `resources/js/layouts/app-layout.tsx`):

```tsx
import { ClockInOutButton } from '@/components/clock-in-out-button';

// Inside the header's right-aligned actions area:
<ClockInOutButton />
```

### Step 9 — Time Card page

Create `resources/js/pages/time-card/index.tsx`:

```tsx
import { Head, router } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDuration, type TimeCardSummary } from '@/types/time-card';

type Props = {
    cards: TimeCardSummary[];
    openCard: TimeCardSummary | null;
    filters: { from: string; to: string; user_id: number | null };
    employees: { id: number; name: string; role: string }[];
    isAdmin: boolean;
    viewingUserId: number | null;
};

function formatDateTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function TimeCardIndex({
    cards,
    openCard,
    filters,
    employees,
    isAdmin,
    viewingUserId,
}: Props) {
    const [notes, setNotes] = useState('');
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        user_id: viewingUserId ? String(viewingUserId) : '',
    });

    const clockIn = () => {
        router.post('/time-card/clock-in', { notes: notes || undefined }, {
            preserveScroll: true,
            onSuccess: () => setNotes(''),
        });
    };

    const clockOut = () => {
        router.post('/time-card/clock-out', { notes: notes || undefined }, {
            preserveScroll: true,
            onSuccess: () => setNotes(''),
        });
    };

    const applyFilters = (e?: React.FormEvent) => {
        e?.preventDefault();
        const params: Record<string, string> = {};

        if (form.from) params.from = form.from;
        if (form.to) params.to = form.to;
        if (isAdmin && form.user_id) params.user_id = form.user_id;

        router.get('/time-card', params, { preserveState: true, replace: true });
    };

    const totalMinutes = cards.reduce(
        (sum, c) => sum + (c.duration_minutes ?? 0),
        0,
    );

    return (
        <>
            <Head title="Time Card" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Time Card</h1>
                <p className="text-sm text-muted-foreground">
                    Clock in and out to track your hours.
                </p>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {openCard ? 'You are clocked in' : 'Clock In / Out'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {openCard && (
                            <div className="rounded-md border bg-emerald-50 p-3 text-sm dark:bg-emerald-900/20">
                                Clocked in at{' '}
                                <span className="font-medium">
                                    {formatDateTime(openCard.clock_in_at)}
                                </span>
                                {openCard.notes && (
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {openCard.notes}
                                    </div>
                                )}
                            </div>
                        )}
                        <div>
                            <label className="mb-1 block text-xs font-medium">
                                Notes (optional)
                            </label>
                            <textarea
                                className="min-h-16 w-full rounded-md border bg-transparent p-2 text-sm"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder={
                                    openCard
                                        ? 'Add notes before clocking out'
                                        : 'Add notes for this shift'
                                }
                            />
                        </div>
                        <div className="flex gap-2">
                            {!openCard && (
                                <Button onClick={clockIn}>
                                    <LogIn className="size-4" /> Clock In
                                </Button>
                            )}
                            {openCard && (
                                <Button variant="destructive" onClick={clockOut}>
                                    <LogOut className="size-4" /> Clock Out
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid grid-cols-2 gap-3 md:grid-cols-4"
                        >
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium">From</label>
                                <Input
                                    type="date"
                                    value={form.from}
                                    onChange={(e) =>
                                        setForm({ ...form, from: e.target.value })
                                    }
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium">To</label>
                                <Input
                                    type="date"
                                    value={form.to}
                                    onChange={(e) =>
                                        setForm({ ...form, to: e.target.value })
                                    }
                                />
                            </div>
                            {isAdmin && (
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs font-medium">
                                        Employee
                                    </label>
                                    <Select
                                        value={form.user_id}
                                        onValueChange={(v) =>
                                            setForm({ ...form, user_id: v })
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {employees.map((emp) => (
                                                <SelectItem
                                                    key={emp.id}
                                                    value={String(emp.id)}
                                                >
                                                    {emp.name} ({emp.role})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="flex items-end">
                                <Button type="submit" className="w-full">
                                    Apply
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2">Clock In</th>
                                    <th className="px-4 py-2">Clock Out</th>
                                    <th className="px-4 py-2">Duration</th>
                                    <th className="px-4 py-2">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cards.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                                            No time cards in this range.
                                        </td>
                                    </tr>
                                )}
                                {cards.map((c) => (
                                    <tr key={c.id} className="border-t">
                                        <td className="px-4 py-2 font-mono">
                                            {formatDateTime(c.clock_in_at)}
                                        </td>
                                        <td className="px-4 py-2 font-mono">
                                            {c.clock_out_at
                                                ? formatDateTime(c.clock_out_at)
                                                : '— open —'}
                                        </td>
                                        <td className="px-4 py-2 font-mono">
                                            {formatDuration(c.duration_minutes)}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {c.notes ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                                {cards.length > 0 && (
                                    <tr className="border-t-2 bg-muted/40">
                                        <td colSpan={2} className="px-4 py-2 text-right font-medium">
                                            Total
                                        </td>
                                        <td className="px-4 py-2 font-mono font-semibold">
                                            {formatDuration(totalMinutes)}
                                        </td>
                                        <td />
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
```

> Wrap this page in the project's app layout if it isn't auto-wrapped. With `resolvePageComponent` and a default layout assigned, you don't need to add anything.

### Step 10 — Sidebar / nav entry (optional)

Wherever the target project defines nav items (e.g. `app-sidebar.tsx`), add:

```tsx
{ title: 'Time Card', href: '/time-card', icon: ClockIcon /* or any Lucide icon */ }
```

### Step 11 — Tests

Create `tests/Feature/TimeCardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\TimeCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clock_in(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/time-card/clock-in');

        $response->assertRedirect();
        $this->assertDatabaseHas('time_cards', [
            'user_id' => $user->id,
            'clock_out_at' => null,
        ]);
    }

    public function test_user_cannot_clock_in_twice(): void
    {
        $user = User::factory()->create();
        TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now(),
        ]);

        $this->actingAs($user)->post('/time-card/clock-in');

        $this->assertEquals(1, TimeCard::where('user_id', $user->id)->count());
    }

    public function test_user_can_clock_out(): void
    {
        $user = User::factory()->create();
        $card = TimeCard::create([
            'user_id' => $user->id,
            'clock_in_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->post('/time-card/clock-out');

        $this->assertNotNull($card->fresh()->clock_out_at);
    }

    public function test_clock_out_with_no_open_card_does_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/time-card/clock-out');

        $this->assertDatabaseCount('time_cards', 0);
    }

    public function test_index_shows_only_own_cards_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create();

        TimeCard::create(['user_id' => $user->id, 'clock_in_at' => now()]);
        TimeCard::create(['user_id' => $other->id, 'clock_in_at' => now()]);

        $response = $this->actingAs($user)
            ->get('/time-card?user_id='.$other->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('viewingUserId', $user->id)
            ->has('cards', 1)
        );
    }
}
```

Run with:

```bash
php artisan test --filter=TimeCardTest
```

## Verification checklist

After implementing, manually verify:

- [ ] Migrate succeeds: `php artisan migrate`.
- [ ] Tests pass: `php artisan test --filter=TimeCardTest`.
- [ ] Visit any page while logged in → header shows "Clock In" button.
- [ ] Click "Clock In" → button switches to red "Clock Out" with a live ticking `0:00:01`, `0:00:02`, …
- [ ] Refresh the page → still clocked in, elapsed continues from the real elapsed time (not zero).
- [ ] Visit `/time-card` → see the open card banner + a notes textarea + a Clock Out button.
- [ ] Add notes, click Clock Out → table row appears with duration, notes saved.
- [ ] Filter by date range → table updates.
- [ ] As admin, filter by employee → see that user's cards. As non-admin, the `user_id` filter is ignored.
- [ ] Trying to clock in while already clocked in → flash error toast, no duplicate row.
- [ ] Trying to clock out while not clocked in → flash error toast, no row created.

## Key design decisions (do not skip)

1. **Open card is enforced by a query, not a unique index.** The check `whereNull('clock_out_at')->exists()` runs before insert. Simple and good enough for low-volume use. If you expect race conditions (mobile users double-tapping), wrap clock-in in `DB::transaction` + a `lockForUpdate`.

2. **Notes are append-only on clock-out.** If the user wrote notes at clock-in and writes more at clock-out, both are kept (joined by newline). The controller's `trim(($card->notes ? $card->notes."\n" : '').$notes)` handles this.

3. **`openTimeCard` is shared via Inertia middleware, not fetched per page.** This is what makes the header widget work on every page without a separate API call. Don't try to lift this into a per-page prop.

4. **Elapsed time is computed client-side** from the ISO `clock_in_at` and `Date.now()`. The server never sends ticking duration — it sends a static timestamp, and the React component computes elapsed every second via `setInterval`. This keeps the server stateless.

5. **`preserveState: false` on clock-in/out posts.** This forces Inertia to refetch all props (including the shared `openTimeCard`), so the widget reflects new state immediately.

6. **Admin scoping is in the controller, not a policy.** Simple `if (! $user?->isAdmin()) { $viewingUserId = $user?->id; }`. If your project uses Laravel policies, port this to a `TimeCardPolicy::viewAny` if you prefer.

## What to ask the implementing agent

After pasting this, you can prompt: **"Implement the time log / clock-in feature exactly as described in TIMELOG_FEATURE_PLAN.md. Follow steps 1–11 in order. Stop after each step to confirm files compile and run tests at the end."**
