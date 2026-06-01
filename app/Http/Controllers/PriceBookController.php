<?php

namespace App\Http\Controllers;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PriceBookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $categoryId = $request->integer('category');
        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 20, 30], true)) {
            $perPage = 10;
        }

        $items = PriceItem::query()
            ->with(['category:id,code,name,sort', 'preferredVendor:id,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->when($categoryId > 0, fn ($q) => $q->where('price_category_id', $categoryId))
            ->join('price_categories', 'price_categories.id', '=', 'price_items.price_category_id')
            ->orderBy('price_categories.sort')
            ->orderBy('price_items.name')
            ->select('price_items.*')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PriceItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'fast_price' => $i->fast_price,
                'material_cost' => $i->material_cost,
                'bb_install_rate' => $i->bb_install_rate,
                'sub_install_rate' => $i->sub_install_rate,
                'price_category_id' => $i->price_category_id,
                'category_code' => $i->category->code,
                'category_name' => $i->category->name,
                'preferred_vendor_id' => $i->preferred_vendor_id,
                'vendor_name' => $i->preferredVendor?->name,
                'notes' => $i->notes,
            ]);

        return Inertia::render('price-book/index', [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'category' => $categoryId ?: '',
                'per_page' => $perPage,
            ],
            'categories' => PriceCategory::query()
                ->orderBy('sort')
                ->get(['id', 'code', 'name']),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'unitOptions' => PriceItem::UNITS,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PriceItem::create($this->validateData($request));

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, PriceItem $priceItem): RedirectResponse
    {
        $priceItem->update($this->validateData($request));

        return back()->with('success', 'Item updated.');
    }

    public function destroy(PriceItem $priceItem): RedirectResponse
    {
        $priceItem->delete();

        return back()->with('success', 'Item removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'price_category_id' => ['required', Rule::exists('price_categories', 'id')],
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:20',
            'fast_price' => 'nullable|numeric|min:0',
            'material_cost' => 'nullable|numeric|min:0',
            'bb_install_rate' => 'nullable|numeric|min:0',
            'sub_install_rate' => 'nullable|numeric|min:0',
            'preferred_vendor_id' => ['nullable', Rule::exists('vendors', 'id')],
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}
