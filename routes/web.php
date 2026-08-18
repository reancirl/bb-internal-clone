<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DecisionCatalogController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EquipmentRateController;
use App\Http\Controllers\Admin\LaborRateController;
use App\Http\Controllers\Admin\RateController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ChangeOrderController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DailyLogPhotoController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PriceBookController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SelectionController;
use App\Http\Controllers\TakeoffLineController;
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

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');

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

    // M3 — Projects + Takeoff Calculator (everyone views; admins manage; crew toggle ordered/on-site)
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}/export', [ProjectController::class, 'export'])->name('projects.export');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('takeoff-lines/{takeoffLine}/toggle', [TakeoffLineController::class, 'toggle'])->name('takeoff-lines.toggle');

    // Customer Selections (Buildertrend-style) — everyone views; admins manage
    Route::get('projects/{project}/selections', [SelectionController::class, 'index'])->name('selections.index');
    Route::middleware('admin')->group(function () {
        Route::post('projects/{project}/selections/generate', [SelectionController::class, 'generate'])->name('selections.generate');
        Route::put('selections/{selection}', [SelectionController::class, 'update'])->name('selections.update');
        Route::delete('selections/{selection}', [SelectionController::class, 'destroy'])->name('selections.destroy');
        Route::post('selections/{selection}/choices', [SelectionController::class, 'storeChoice'])->name('selections.choices.store');
        Route::put('selection-choices/{choice}', [SelectionController::class, 'updateChoice'])->name('selections.choices.update');
        Route::delete('selection-choices/{choice}', [SelectionController::class, 'destroyChoice'])->name('selections.choices.destroy');
        Route::post('selections/{selection}/approve', [SelectionController::class, 'approve'])->name('selections.approve');
    });

    // Daily Logs — field documentation; everyone views and writes, authors
    // (or admins) edit/delete their own entries
    Route::get('daily-logs', [DailyLogController::class, 'index'])->name('daily-logs.index');
    Route::post('daily-logs', [DailyLogController::class, 'store'])->name('daily-logs.store');
    Route::put('daily-logs/{dailyLog}', [DailyLogController::class, 'update'])->name('daily-logs.update');
    Route::delete('daily-logs/{dailyLog}', [DailyLogController::class, 'destroy'])->name('daily-logs.destroy');
    Route::post('daily-logs/{dailyLog}/photos', [DailyLogPhotoController::class, 'store'])->name('daily-logs.photos.store');
    Route::get('daily-log-photos/{photo}', [DailyLogPhotoController::class, 'show'])->name('daily-logs.photos.show');
    Route::get('daily-log-photos/{photo}/thumb', [DailyLogPhotoController::class, 'thumb'])->name('daily-logs.photos.thumb');
    Route::delete('daily-log-photos/{photo}', [DailyLogPhotoController::class, 'destroy'])->name('daily-logs.photos.destroy');

    // Project Budget (bid vs actual) — office only per permissions matrix
    Route::middleware('admin')->group(function () {
        Route::get('projects/{project}/budget', [BudgetController::class, 'index'])->name('budget.index');
        Route::post('projects/{project}/budget/generate', [BudgetController::class, 'generate'])->name('budget.generate');
        Route::post('projects/{project}/budget/lines', [BudgetController::class, 'storeLine'])->name('budget.lines.store');
        Route::get('projects/{project}/budget/export', [BudgetController::class, 'export'])->name('budget.export');
        Route::put('budget-lines/{line}', [BudgetController::class, 'updateLine'])->name('budget.lines.update');
        Route::delete('budget-lines/{line}', [BudgetController::class, 'destroyLine'])->name('budget.lines.destroy');

        // Change Orders (customer-approved contract changes)
        Route::post('projects/{project}/change-orders', [ChangeOrderController::class, 'store'])->name('change-orders.store');
        Route::patch('projects/{project}/contract', [ChangeOrderController::class, 'updateContract'])->name('projects.contract');
        Route::put('change-orders/{changeOrder}', [ChangeOrderController::class, 'update'])->name('change-orders.update');
        Route::post('change-orders/{changeOrder}/decide', [ChangeOrderController::class, 'decide'])->name('change-orders.decide');
        Route::post('change-orders/{changeOrder}/revert', [ChangeOrderController::class, 'revert'])->name('change-orders.revert');
        Route::delete('change-orders/{changeOrder}', [ChangeOrderController::class, 'destroy'])->name('change-orders.destroy');

        // Purchase Orders (committed material costs per project)
        Route::get('projects/{project}/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('projects/{project}/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchaseOrder}/transition', [PurchaseOrderController::class, 'transition'])->name('purchase-orders.transition');
        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
        Route::get('purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])->name('purchase-orders.pdf');
    });

    // M7 — Jobs + Calendar (everyone views; assigned crew toggle status; admins manage)
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('calendar', [JobController::class, 'calendar'])->name('calendar.index');
    Route::patch('jobs/{job}/status', [JobController::class, 'updateStatus'])->name('jobs.status');

    Route::middleware('admin')->group(function () {
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('projects/{project}/lines', [TakeoffLineController::class, 'store'])->name('takeoff-lines.store');
        Route::put('takeoff-lines/{takeoffLine}', [TakeoffLineController::class, 'update'])->name('takeoff-lines.update');
        Route::delete('takeoff-lines/{takeoffLine}', [TakeoffLineController::class, 'destroy'])->name('takeoff-lines.destroy');

        Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::get('jobs/{job}/shift-preview', [JobController::class, 'shiftPreview'])->name('jobs.shift-preview');
        Route::put('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::delete('jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // CRM Pipeline (leads kanban, detail, analytics, activities)
    Route::get('leads', [LeadController::class, 'pipeline'])->name('leads.index');
    Route::get('leads/analytics', [LeadController::class, 'analytics'])->name('leads.analytics');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    Route::post('leads/{lead}/activities', [LeadController::class, 'storeActivity'])->name('leads.activities.store');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    // M2 — Labor & Equipment rate tables
    Route::get('rates', [RateController::class, 'index'])->name('rates.index');
    Route::post('labor-rates', [LaborRateController::class, 'store'])->name('labor-rates.store');
    Route::put('labor-rates/{laborRate}', [LaborRateController::class, 'update'])->name('labor-rates.update');
    Route::delete('labor-rates/{laborRate}', [LaborRateController::class, 'destroy'])->name('labor-rates.destroy');
    Route::post('equipment-rates', [EquipmentRateController::class, 'store'])->name('equipment-rates.store');
    Route::put('equipment-rates/{equipmentRate}', [EquipmentRateController::class, 'update'])->name('equipment-rates.update');
    Route::delete('equipment-rates/{equipmentRate}', [EquipmentRateController::class, 'destroy'])->name('equipment-rates.destroy');

    // M8 — Employees (manage crew/admin accounts)
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('employees/{employee}/password', [EmployeeController::class, 'resetPassword'])->name('employees.password');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::patch('employees/{employee}/restore', [EmployeeController::class, 'restore'])->withTrashed()->name('employees.restore');

    // Decision catalog (selections template shared by all projects)
    Route::get('decision-catalog', [DecisionCatalogController::class, 'index'])->name('decision-catalog.index');
    Route::post('decision-categories', [DecisionCatalogController::class, 'storeCategory'])->name('decision-categories.store');
    Route::put('decision-categories/{category}', [DecisionCatalogController::class, 'updateCategory'])->name('decision-categories.update');
    Route::post('decision-categories/{category}/items', [DecisionCatalogController::class, 'storeItem'])->name('decision-items.store');
    Route::put('decision-items/{item}', [DecisionCatalogController::class, 'updateItem'])->name('decision-items.update');

    // Proposals (customer-facing quotes snapshotted from the takeoff estimate)
    Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
    Route::post('projects/{project}/proposals', [ProposalController::class, 'store'])->name('proposals.store');
    Route::get('proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
    Route::get('proposals/{proposal}/pdf', [ProposalController::class, 'pdf'])->name('proposals.pdf');
    Route::put('proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
    Route::post('proposals/{proposal}/transition', [ProposalController::class, 'transition'])->name('proposals.transition');
    Route::delete('proposals/{proposal}', [ProposalController::class, 'destroy'])->name('proposals.destroy');

    Route::get('reports', [BudgetController::class, 'report'])->name('reports.index');
    Route::get('reports/time-summary', [ReportController::class, 'timeSummary'])->name('reports.time-summary');
    Route::get('reports/time-summary/export', [ReportController::class, 'timeSummaryExport'])->name('reports.time-summary.export');
    Route::get('reports/material-status', [ReportController::class, 'materialStatus'])->name('reports.material-status');
    Route::get('reports/material-status/export', [ReportController::class, 'materialStatusExport'])->name('reports.material-status.export');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
