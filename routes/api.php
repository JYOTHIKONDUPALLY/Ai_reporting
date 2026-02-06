<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiReportController;
use App\Http\Controllers\Api\ApiDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Routes - All return JSON responses
Route::prefix('v1')->group(function () {
    
    // Reports API
    Route::prefix('reports')->group(function () {
        // List available predefined reports
        Route::get('/predefined', [ApiReportController::class, 'listPredefined']);
        
        // Get a specific predefined report
        Route::get('/predefined/{type}', [ApiReportController::class, 'getPredefined']);
        
        // Run a custom SQL query
        Route::post('/run', [ApiReportController::class, 'run']);
        
        // Ask AI to generate and execute a query
        Route::post('/ask-ai', [ApiReportController::class, 'askAi']);
        
        // Export report data as CSV
        Route::post('/export', [ApiReportController::class, 'export']);
        
        // Test connection
        Route::get('/test', [ApiReportController::class, 'test']);
    });
    
    // Dashboards API
    Route::prefix('dashboards')->group(function () {
        // List all available dashboards
        Route::get('/', [ApiDashboardController::class, 'index']);
        
        // Get a specific dashboard with all widgets
        Route::get('/{type}', [ApiDashboardController::class, 'show']);
        
        // Get dashboard data (same as show but explicit)
        Route::get('/{type}/data', [ApiDashboardController::class, 'getData']);
        
        // Get financial dashboard data
        Route::get('/financial/data', [ApiDashboardController::class, 'financial']);
        
        // Get financial table view data
        Route::get('/financial-table/data', [ApiDashboardController::class, 'financialTable']);
    });
});

