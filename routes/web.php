<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EquipmentRateController;
use App\Http\Controllers\Admin\LaborRateController;
use App\Http\Controllers\Admin\RateController;
use App\Http\Controllers\PriceBookController;
use App\Http\Controllers\TimeCardController;
use App\Http\Controllers\TradePartnerController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('time-card', [TimeCardController::class, 'index'])->name('time-card.index');
    Route::post('time-card/clock-in', [TimeCardController::class, 'clockIn'])->name('time-card.clock-in');
    Route::post('time-card/clock-out', [TimeCardController::class, 'clockOut'])->name('time-card.clock-out');

    // M1 — Trade Partners + Vendors directory (everyone can view; admins manage)
    Route::get('trade-partners', [TradePartnerController::class, 'index'])->name('trade-partners.index');
    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');

    Route::middleware('admin')->group(function () {
        Route::post('trade-partners', [TradePartnerController::class, 'store'])->name('trade-partners.store');
        Route::put('trade-partners/{tradePartner}', [TradePartnerController::class, 'update'])->name('trade-partners.update');
        Route::delete('trade-partners/{tradePartner}', [TradePartnerController::class, 'destroy'])->name('trade-partners.destroy');

        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    });

    // M2 — Price Book (everyone can view; admins manage items)
    Route::get('price-book', [PriceBookController::class, 'index'])->name('price-book.index');
    Route::middleware('admin')->group(function () {
        Route::post('price-book', [PriceBookController::class, 'store'])->name('price-book.store');
        Route::put('price-book/{priceItem}', [PriceBookController::class, 'update'])->name('price-book.update');
        Route::delete('price-book/{priceItem}', [PriceBookController::class, 'destroy'])->name('price-book.destroy');
    });

    // Stubs — routes for sidebar nav consistency; pages render "Coming soon" placeholders
    // until their milestone is implemented. See docs/PLAN.md
    Route::get('projects', fn () => Inertia::render('coming-soon', ['title' => 'Projects', 'milestone' => 'M3']))->name('projects.index');
    Route::get('calendar', fn () => Inertia::render('coming-soon', ['title' => 'Calendar', 'milestone' => 'M7']))->name('calendar.index');
    Route::get('jobs', fn () => Inertia::render('coming-soon', ['title' => 'Jobs', 'milestone' => 'M7']))->name('jobs.index');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // M2 — Labor & Equipment rate tables
    Route::get('rates', [RateController::class, 'index'])->name('rates.index');
    Route::post('labor-rates', [LaborRateController::class, 'store'])->name('labor-rates.store');
    Route::put('labor-rates/{laborRate}', [LaborRateController::class, 'update'])->name('labor-rates.update');
    Route::delete('labor-rates/{laborRate}', [LaborRateController::class, 'destroy'])->name('labor-rates.destroy');
    Route::post('equipment-rates', [EquipmentRateController::class, 'store'])->name('equipment-rates.store');
    Route::put('equipment-rates/{equipmentRate}', [EquipmentRateController::class, 'update'])->name('equipment-rates.update');
    Route::delete('equipment-rates/{equipmentRate}', [EquipmentRateController::class, 'destroy'])->name('equipment-rates.destroy');

    Route::get('employees', fn () => Inertia::render('coming-soon', ['title' => 'Employees', 'milestone' => 'M8']))->name('employees.index');
    Route::get('reports', fn () => Inertia::render('coming-soon', ['title' => 'Reports', 'milestone' => 'M8']))->name('reports.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
