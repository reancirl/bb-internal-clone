<?php

namespace App\Http\Controllers;

use App\Models\TradePartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TradePartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $trade = $request->string('trade')->toString();
        $location = $request->string('location')->toString();
        $usedOnly = $request->boolean('used_only');
        $perPage = $this->perPage($request);

        $partners = TradePartner::query()
            ->with('trades:id,trade_partner_id,trade')
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereLike('name', $search)
                    ->orWhereLike('notes', $search)
                    ->orWhereLike('phone', $search)
                    ->orWhereLike('email', $search);
            }))
            ->when($trade !== '', fn ($q) => $q->whereHas('trades', fn ($t) => $t->where('trade', $trade)))
            ->when($location !== '', fn ($q) => $q->where('location', $location))
            ->when($usedOnly, fn ($q) => $q->where('used_before', true))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TradePartner $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'location' => $p->location,
                'phone' => $p->phone,
                'email' => $p->email,
                'used_before' => $p->used_before,
                'negotiated_price' => $p->negotiated_price,
                'referral_source' => $p->referral_source,
                'notes' => $p->notes,
                'do_not_use' => $p->do_not_use,
                'trades' => $p->trades->pluck('trade')->values(),
            ]);

        return Inertia::render('trade-partners/index', [
            'partners' => $partners,
            'filters' => [
                'search' => $search,
                'trade' => $trade,
                'location' => $location,
                'used_only' => $usedOnly,
                'per_page' => $perPage,
            ],
            'tradeOptions' => TradePartner::TRADES,
            'locationOptions' => TradePartner::query()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
                ->values(),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $partner = TradePartner::create($this->attributes($data));
        $this->syncTrades($partner, $data['trades'] ?? []);

        return back()->with('success', 'Trade partner added.');
    }

    public function update(Request $request, TradePartner $tradePartner): RedirectResponse
    {
        $data = $this->validateData($request);

        $tradePartner->update($this->attributes($data));
        $this->syncTrades($tradePartner, $data['trades'] ?? []);

        return back()->with('success', 'Trade partner updated.');
    }

    public function destroy(TradePartner $tradePartner): RedirectResponse
    {
        $tradePartner->delete();

        return back()->with('success', 'Trade partner removed.');
    }

    /**
     * Resolve the rows-per-page, restricted to the allowed options (default 10).
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 10);

        return in_array($perPage, [10, 20, 30], true) ? $perPage : 10;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'used_before' => 'boolean',
            'negotiated_price' => 'nullable|string|max:255',
            'referral_source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'do_not_use' => 'boolean',
            'trades' => 'array',
            'trades.*' => ['string', Rule::in(TradePartner::TRADES)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'used_before' => $data['used_before'] ?? false,
            'negotiated_price' => $data['negotiated_price'] ?? null,
            'referral_source' => $data['referral_source'] ?? null,
            'notes' => $data['notes'] ?? null,
            'do_not_use' => $data['do_not_use'] ?? false,
        ];
    }

    /**
     * @param  array<int, string>  $trades
     */
    private function syncTrades(TradePartner $partner, array $trades): void
    {
        $trades = array_values(array_unique($trades));

        $partner->trades()->delete();
        foreach ($trades as $trade) {
            $partner->trades()->create(['trade' => $trade]);
        }
    }
}
