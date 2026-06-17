<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentRate;
use App\Models\LaborRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'laborRates' => LaborRate::query()->orderBy('id')->get(),
            'equipmentRates' => EquipmentRate::query()->orderBy('name')->get(),
        ]);
    }

    public function storeLabor(Request $request): JsonResponse
    {
        $rate = LaborRate::create($this->validateLabor($request));

        return response()->json(['message' => 'Labor rate added.', 'laborRate' => $rate], 201);
    }

    public function updateLabor(Request $request, LaborRate $laborRate): JsonResponse
    {
        $laborRate->update($this->validateLabor($request));

        return response()->json(['message' => 'Labor rate updated.', 'laborRate' => $laborRate->fresh()]);
    }

    public function destroyLabor(LaborRate $laborRate): JsonResponse
    {
        $laborRate->delete();

        return response()->json(['message' => 'Labor rate removed.']);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $rate = EquipmentRate::create($this->validateEquipment($request));

        return response()->json(['message' => 'Equipment added.', 'equipmentRate' => $rate], 201);
    }

    public function updateEquipment(Request $request, EquipmentRate $equipmentRate): JsonResponse
    {
        $equipmentRate->update($this->validateEquipment($request));

        return response()->json(['message' => 'Equipment updated.', 'equipmentRate' => $equipmentRate->fresh()]);
    }

    public function destroyEquipment(EquipmentRate $equipmentRate): JsonResponse
    {
        $equipmentRate->delete();

        return response()->json(['message' => 'Equipment removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLabor(Request $request): array
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

    /**
     * @return array<string, mixed>
     */
    private function validateEquipment(Request $request): array
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
