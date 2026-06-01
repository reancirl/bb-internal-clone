<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentRate;
use App\Models\LaborRate;
use Inertia\Inertia;
use Inertia\Response;

class RateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/rates', [
            'laborRates' => LaborRate::query()->orderBy('id')->get(),
            'equipmentRates' => EquipmentRate::query()->orderBy('name')->get(),
        ]);
    }
}
