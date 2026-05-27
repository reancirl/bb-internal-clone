<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\TimeCardController;
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

    // Stubs — routes for sidebar nav consistency; pages render "Coming soon" placeholders
    // until their milestone is implemented. See docs/05-implementation-plan.md
    Route::get('projects', fn () => Inertia::render('coming-soon', ['title' => 'Projects', 'milestone' => 'M1']))->name('projects.index');
    Route::get('calendar', fn () => Inertia::render('coming-soon', ['title' => 'Calendar', 'milestone' => 'M7']))->name('calendar.index');
    Route::get('jobs', fn () => Inertia::render('coming-soon', ['title' => 'Jobs', 'milestone' => 'M7']))->name('jobs.index');
    Route::get('trade-partners', fn () => Inertia::render('coming-soon', ['title' => 'Trade Partners', 'milestone' => 'M1']))->name('trade-partners.index');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('employees', fn () => Inertia::render('coming-soon', ['title' => 'Employees', 'milestone' => 'M2']))->name('employees.index');
    Route::get('reports', fn () => Inertia::render('coming-soon', ['title' => 'Reports', 'milestone' => 'M8']))->name('reports.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
