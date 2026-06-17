<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\RateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PriceBookController;
use App\Http\Controllers\Api\TimeCardController;
use App\Http\Controllers\Api\TradePartnerController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile JSON API (token auth via Laravel Sanctum)
|--------------------------------------------------------------------------
| Consumed by the Expo / React Native app. All routes are prefixed with
| /api. Clients log in once, store the returned bearer token, and send it
| as `Authorization: Bearer {token}` on every authenticated request.
*/

// Public
Route::post('login', [AuthController::class, 'login'])->name('api.login');

// Authenticated (any role)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user'])->name('api.user');
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

    // Time Card — self-service for everyone
    Route::get('time-card', [TimeCardController::class, 'index'])->name('api.time-card.index');
    Route::post('time-card/clock-in', [TimeCardController::class, 'clockIn'])->name('api.time-card.clock-in');
    Route::post('time-card/clock-out', [TimeCardController::class, 'clockOut'])->name('api.time-card.clock-out');

    // Directories + Price Book — everyone reads
    Route::get('trade-partners', [TradePartnerController::class, 'index'])->name('api.trade-partners.index');
    Route::get('vendors', [VendorController::class, 'index'])->name('api.vendors.index');
    Route::get('price-book', [PriceBookController::class, 'index'])->name('api.price-book.index');

    // Admin-only — manage records, rates, and view stats
    Route::middleware('admin')->group(function () {
        Route::post('trade-partners', [TradePartnerController::class, 'store'])->name('api.trade-partners.store');
        Route::put('trade-partners/{tradePartner}', [TradePartnerController::class, 'update'])->name('api.trade-partners.update');
        Route::delete('trade-partners/{tradePartner}', [TradePartnerController::class, 'destroy'])->name('api.trade-partners.destroy');

        Route::post('vendors', [VendorController::class, 'store'])->name('api.vendors.store');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('api.vendors.update');
        Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('api.vendors.destroy');

        Route::post('price-book', [PriceBookController::class, 'store'])->name('api.price-book.store');
        Route::put('price-book/{priceItem}', [PriceBookController::class, 'update'])->name('api.price-book.update');
        Route::delete('price-book/{priceItem}', [PriceBookController::class, 'destroy'])->name('api.price-book.destroy');

        Route::prefix('admin')->name('api.admin.')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::get('rates', [RateController::class, 'index'])->name('rates.index');
            Route::post('labor-rates', [RateController::class, 'storeLabor'])->name('labor-rates.store');
            Route::put('labor-rates/{laborRate}', [RateController::class, 'updateLabor'])->name('labor-rates.update');
            Route::delete('labor-rates/{laborRate}', [RateController::class, 'destroyLabor'])->name('labor-rates.destroy');
            Route::post('equipment-rates', [RateController::class, 'storeEquipment'])->name('equipment-rates.store');
            Route::put('equipment-rates/{equipmentRate}', [RateController::class, 'updateEquipment'])->name('equipment-rates.update');
            Route::delete('equipment-rates/{equipmentRate}', [RateController::class, 'destroyEquipment'])->name('equipment-rates.destroy');
        });
    });
});
