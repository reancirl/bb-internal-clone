<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EquipmentRateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        EquipmentRate::create($this->validateData($request));

        return back()->with('success', 'Equipment added.');
    }

    public function update(Request $request, EquipmentRate $equipmentRate): RedirectResponse
    {
        $equipmentRate->update($this->validateData($request));

        return back()->with('success', 'Equipment updated.');
    }

    public function destroy(EquipmentRate $equipmentRate): RedirectResponse
    {
        $equipmentRate->delete();

        return back()->with('success', 'Equipment removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'day_rate' => 'nullable|numeric|min:0',
            'week_rate' => 'nullable|numeric|min:0',
            'month_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
