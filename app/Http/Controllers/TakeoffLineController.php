<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TakeoffLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TakeoffLineController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $project->takeoffLines()->create($this->validateData($request));

        return back()->with('success', 'Line added.');
    }

    public function update(Request $request, TakeoffLine $takeoffLine): RedirectResponse
    {
        $takeoffLine->update($this->validateData($request));

        return back()->with('success', 'Line updated.');
    }

    public function destroy(TakeoffLine $takeoffLine): RedirectResponse
    {
        $takeoffLine->delete();

        return back()->with('success', 'Line removed.');
    }

    /**
     * Toggle the ordered / on-site status. Available to any crew member so
     * field staff can track procurement without admin rights.
     */
    public function toggle(Request $request, TakeoffLine $takeoffLine): RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['ordered', 'on_site'])],
            'value' => 'required|boolean',
        ]);

        $takeoffLine->update([$data['field'] => $data['value']]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'price_item_id' => ['nullable', Rule::exists('price_items', 'id')],
            'category' => 'nullable|string|max:255',
            'item' => 'required|string|max:255',
            'formula' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:20',
            'waste_pct' => 'nullable|numeric|min:0|max:100',
            'supplier_id' => ['nullable', Rule::exists('vendors', 'id')],
            'ordered' => 'boolean',
            'on_site' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'sort' => 'nullable|integer|min:0',
        ]);
    }
}
