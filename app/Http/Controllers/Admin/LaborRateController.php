<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaborRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LaborRateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        LaborRate::create($this->validateData($request));

        return back()->with('success', 'Labor rate added.');
    }

    public function update(Request $request, LaborRate $laborRate): RedirectResponse
    {
        $laborRate->update($this->validateData($request));

        return back()->with('success', 'Labor rate updated.');
    }

    public function destroy(LaborRate $laborRate): RedirectResponse
    {
        $laborRate->delete();

        return back()->with('success', 'Labor rate removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'class_name' => 'required|string|max:255',
            'base_rate' => 'nullable|numeric|min:0',
            'burden_rate' => 'nullable|numeric|min:0',
            'bill_rate' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
