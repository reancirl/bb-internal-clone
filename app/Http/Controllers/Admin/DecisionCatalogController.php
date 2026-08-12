<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DecisionCategory;
use App\Models\DecisionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DecisionCatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/decision-catalog', [
            'categories' => DecisionCategory::with('items')
                ->orderBy('sort_order')->orderBy('id')
                ->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('decision_categories', 'name')->where('scope', $request->input('scope'))],
            'scope' => ['required', Rule::in(DecisionCategory::SCOPES)],
            'sort_order' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        DecisionCategory::create($data);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, DecisionCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('decision_categories', 'name')->where('scope', $request->input('scope', $category->scope))->ignore($category->id)],
            'scope' => ['sometimes', Rule::in(DecisionCategory::SCOPES)],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function storeItem(Request $request, DecisionCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255', Rule::unique('decision_items', 'label')->where('decision_category_id', $category->id)],
            'recommended' => ['nullable', 'string', 'max:255'],
            'guidance' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category->items()->create($data);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, DecisionItem $item): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('decision_items', 'label')->where('decision_category_id', $item->decision_category_id)->ignore($item->id)],
            'recommended' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guidance' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->update($data);

        return back()->with('success', 'Item updated.');
    }
}
