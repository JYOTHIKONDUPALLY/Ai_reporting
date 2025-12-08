<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;

// Authentication routes (public)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Redirect root to login
Route::get('/', function () {
    if (session()->has('authenticated') && session('authenticated') === true) {
        return redirect()->route('reports.index');
    }
    return redirect()->route('login');
});

// Protected routes (require authentication)
Route::middleware(['auth'])->group(function () {
    Route::get('/report/test', [ReportController::class, 'test']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/run', [ReportController::class, 'run'])->name('reports.run');
    Route::get('/reports/predefined/{type}', [ReportController::class, 'predefined'])->name('reports.predefined');
    Route::post('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::post('/reports/ask-ai', [ReportController::class, 'askAi'])->name('reports.askAi');
    Route::get('/reports/test-ai', [ReportController::class, 'testAi']);

    // Dashboard routes
    Route::get('/dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
    Route::get('/dashboards/{type}', [DashboardController::class, 'show'])->name('dashboards.show');
    Route::get('/dashboards/{type}/data', [DashboardController::class, 'getData'])->name('dashboards.data');
});
