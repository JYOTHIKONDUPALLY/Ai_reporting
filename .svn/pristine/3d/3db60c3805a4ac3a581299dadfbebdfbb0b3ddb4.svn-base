<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display dashboard index page
     */
    public function index()
    {
        try {
            $dashboards = $this->dashboardService->listDashboards();
        } catch (\Exception $e) {
            \Log::error('Error loading dashboards: ' . $e->getMessage());
            $dashboards = [];
        }
        
        return view('dashboards.index', [
            'dashboards' => $dashboards
        ]);
    }

    /**
     * Display a specific dashboard
     */
    public function show(Request $request, string $type)
    {
        $period = $request->input('period', 'all_time');
        
        $filters = [
            'period' => $period,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'franchise' => $request->input('franchise'),
        ];

        // Only include period in filters if it's not 'all_time'
        if ($period === 'all_time') {
            unset($filters['period']);
        }

        $dashboardData = $this->dashboardService->getDashboardData($type, array_filter($filters));
        
        // Get all dashboards for the tab menu
        try {
            $allDashboards = $this->dashboardService->listDashboards();
        } catch (\Exception $e) {
            $allDashboards = [];
        }

        return view('dashboards.show', [
            'dashboard' => $dashboardData,
            'dashboardType' => $type,
            'filters' => $filters,
            'allDashboards' => $allDashboards,
            'currentPeriod' => $period
        ]);
    }

    /**
     * Get dashboard data as JSON (for AJAX requests)
     */
    public function getData(Request $request, string $type)
    {
        $period = $request->input('period', 'this_month');
        
        $filters = [
            'period' => $period,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'franchise' => $request->input('franchise'),
        ];

        $dashboardData = $this->dashboardService->getDashboardData($type, array_filter($filters));

        return response()->json($dashboardData);
    }
}

