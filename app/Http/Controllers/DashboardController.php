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

        // Add financial dashboards at the beginning of the list
        $financialDashboards = [
            [
                'type' => 'financial',
                'title' => 'Financial Card View',
                'route' => 'dashboards.financial'
            ],
            [
                'type' => 'financial-table',
                'title' => 'Financial Table View',
                'route' => 'dashboards.financial-table'
            ]
        ];
        $allDashboards = array_merge($financialDashboards, $allDashboards);

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

    /**
     * Display Financial Dashboard
     */
    public function financial()
    {
        $clickhouse = app(\App\Services\ClickhouseService::class);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $prevWeekStart = date('Y-m-d', strtotime('monday last week'));
        $prevWeekEnd = date('Y-m-d', strtotime('sunday last week'));
        
        // Month to date and last month
        $monthStart = date('Y-m-01'); // First day of current month
        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month')); // Last day of last month
        
        // Year to date and last year
        $yearStart = date('Y-01-01'); // First day of current year
        $lastYearStart = date('Y-01-01', strtotime('-1 year'));
        $lastYearEnd = date('Y-12-31', strtotime('-1 year')); // Last day of last year

        // Saleable item types filter
        $saleableFilter = "(lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') OR lowerUTF8(iid.item_type) LIKE 'misc%' OR lowerUTF8(iid.item_type) LIKE 'Misc%')";

        // Function to get revenue breakdown by type
        $getRevenueBreakdown = function($startDate, $endDate) use ($clickhouse, $saleableFilter) {
            $sql = "
                SELECT 
                    CASE 
                        WHEN lowerUTF8(iid.item_type) = 'membership' THEN 'membership'
                        WHEN lowerUTF8(iid.item_type) IN ('product', 'Product') THEN 'products'
                        WHEN lowerUTF8(iid.item_type) IN ('class', 'appointment', 'Appointment') THEN 'training'
                        WHEN lowerUTF8(iid.item_type) IN ('service', 'Service') THEN 'services'
                        WHEN lowerUTF8(iid.item_type) IN ('giftcard', 'GiftCard') THEN 'giftcards'
                        ELSE 'other'
                    END AS revenue_type,
                    SUM(iid.total_price) AS revenue
                FROM invoice_items_detail AS iid
                INNER JOIN invoice_details AS idt ON iid.invoice_id = idt.id
                WHERE idt.invoice_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')
                    AND idt.status = '1'
                    AND {$saleableFilter}
                GROUP BY revenue_type
            ";

            $results = $clickhouse->select($sql);
            $breakdown = [
                'membership' => 0,
                'products' => 0,
                'training' => 0,
                'services' => 0,
                'giftcards' => 0,
                'total' => 0
            ];

            foreach ($results as $row) {
                $type = $row['revenue_type'] ?? 'other';
                $revenue = (float)($row['revenue'] ?? 0);
                if (isset($breakdown[$type])) {
                    $breakdown[$type] = $revenue;
                }
                $breakdown['total'] += $revenue;
            }

            return $breakdown;
        };

        // Function to get monthly trend data (last 6 months)
        $getMonthlyTrend = function($endDate) use ($clickhouse, $saleableFilter) {
            $sixMonthsAgo = date('Y-m-01', strtotime($endDate . ' -5 months'));
            $sql = "
                SELECT 
                    formatDateTime(toStartOfMonth(idt.invoice_date), '%b') AS month,
                    SUM(iid.total_price) AS revenue
                FROM invoice_items_detail AS iid
                INNER JOIN invoice_details AS idt ON iid.invoice_id = idt.id
                WHERE idt.invoice_date >= toDate('{$sixMonthsAgo}')
                    AND idt.invoice_date <= toDate('{$endDate}')
                    AND idt.status = '1'
                    AND {$saleableFilter}
                GROUP BY toStartOfMonth(idt.invoice_date), month
                ORDER BY toStartOfMonth(idt.invoice_date) ASC
            ";

            $results = $clickhouse->select($sql);
            $trend = [
                'labels' => [],
                'data' => []
            ];

            foreach ($results as $row) {
                $trend['labels'][] = $row['month'] ?? '';
                $trend['data'][] = (float)($row['revenue'] ?? 0);
            }

            return $trend;
        };

        // Function to get location breakdown
        $getLocationBreakdown = function($startDate, $endDate) use ($clickhouse, $saleableFilter) {
            $sql = "
                SELECT 
                    idt.location,
                    SUM(iid.total_price) AS revenue
                FROM invoice_items_detail AS iid
                INNER JOIN invoice_details AS idt ON iid.invoice_id = idt.id
                WHERE idt.invoice_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')
                    AND idt.status = '1'
                    AND {$saleableFilter}
                GROUP BY idt.location
            ";

            $results = $clickhouse->select($sql);
            $locations = [
                'Warehouse' => 0,
                'Transport' => 0,
                'Retail' => 0
            ];

            foreach ($results as $row) {
                $location = $row['location'] ?? '';
                $revenue = (float)($row['revenue'] ?? 0);
                
                // Match location names (case-insensitive)
                $locationLower = strtolower($location);
                if (strpos($locationLower, 'warehouse') !== false) {
                    $locations['Warehouse'] += $revenue;
                } elseif (strpos($locationLower, 'transport') !== false) {
                    $locations['Transport'] += $revenue;
                } elseif (strpos($locationLower, 'retail') !== false) {
                    $locations['Retail'] += $revenue;
                }
            }

            return $locations;
        };

        // Get data for all periods
        $todayData = $getRevenueBreakdown($today, $today);
        $todayData['trend'] = $getMonthlyTrend($today);
        $todayData['locations'] = $getLocationBreakdown($today, $today);
        
        $yesterdayData = $getRevenueBreakdown($yesterday, $yesterday);
        $yesterdayData['trend'] = $getMonthlyTrend($yesterday);
        $yesterdayData['locations'] = $getLocationBreakdown($yesterday, $yesterday);
        
        $weekToDateData = $getRevenueBreakdown($weekStart, $today);
        $weekToDateData['trend'] = $getMonthlyTrend($today);
        $weekToDateData['locations'] = $getLocationBreakdown($weekStart, $today);
        
        $prevWeekData = $getRevenueBreakdown($prevWeekStart, $prevWeekEnd);
        $prevWeekData['trend'] = $getMonthlyTrend($prevWeekEnd);
        $prevWeekData['locations'] = $getLocationBreakdown($prevWeekStart, $prevWeekEnd);
        
        $monthToDateData = $getRevenueBreakdown($monthStart, $today);
        $monthToDateData['trend'] = $getMonthlyTrend($today);
        $monthToDateData['locations'] = $getLocationBreakdown($monthStart, $today);
        
        $lastMonthData = $getRevenueBreakdown($lastMonthStart, $lastMonthEnd);
        $lastMonthData['trend'] = $getMonthlyTrend($lastMonthEnd);
        $lastMonthData['locations'] = $getLocationBreakdown($lastMonthStart, $lastMonthEnd);
        
        $yearToDateData = $getRevenueBreakdown($yearStart, $today);
        $yearToDateData['trend'] = $getMonthlyTrend($today);
        $yearToDateData['locations'] = $getLocationBreakdown($yearStart, $today);
        
        $lastYearData = $getRevenueBreakdown($lastYearStart, $lastYearEnd);
        $lastYearData['trend'] = $getMonthlyTrend($lastYearEnd);
        $lastYearData['locations'] = $getLocationBreakdown($lastYearStart, $lastYearEnd);

        // Get all dashboards for the tab menu
        try {
            $allDashboards = $this->dashboardService->listDashboards();
        } catch (\Exception $e) {
            $allDashboards = [];
        }

        // Add financial dashboards at the beginning of the list
        $financialDashboards = [
            [
                'type' => 'financial',
                'title' => 'Financial Card View',
                'route' => 'dashboards.financial'
            ],
            [
                'type' => 'financial-table',
                'title' => 'Financial Table View',
                'route' => 'dashboards.financial-table'
            ]
        ];
        $allDashboards = array_merge($financialDashboards, $allDashboards);

        return view('dashboards.financial', [
            'todayData' => $todayData,
            'yesterdayData' => $yesterdayData,
            'weekToDateData' => $weekToDateData,
            'prevWeekData' => $prevWeekData,
            'monthToDateData' => $monthToDateData,
            'lastMonthData' => $lastMonthData,
            'yearToDateData' => $yearToDateData,
            'lastYearData' => $lastYearData,
            'today' => $today,
            'yesterday' => $yesterday,
            'weekStart' => $weekStart,
            'prevWeekStart' => $prevWeekStart,
            'prevWeekEnd' => $prevWeekEnd,
            'monthStart' => $monthStart,
            'lastMonthStart' => $lastMonthStart,
            'lastMonthEnd' => $lastMonthEnd,
            'yearStart' => $yearStart,
            'lastYearStart' => $lastYearStart,
            'lastYearEnd' => $lastYearEnd,
            'allDashboards' => $allDashboards
        ]);
    }

    /**
     * Display Financial Dashboard Table View
     */
    public function financialTable()
    {
        $clickhouse = app(\App\Services\ClickhouseService::class);
        $currentYear = date('Y');
        $yearStart = date('Y-01-01');
        $yearEnd = date('Y-12-31');

        // Saleable item types filter
        $saleableFilter = "(lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') OR lowerUTF8(iid.item_type) LIKE 'misc%' OR lowerUTF8(iid.item_type) LIKE 'Misc%')";

        // Query monthly revenue breakdown by type
        $sql = "
            SELECT 
                toStartOfMonth(idt.invoice_date) AS month_start,
                formatDateTime(toStartOfMonth(idt.invoice_date), '%b') AS month_abbr,
                CASE 
                    WHEN lowerUTF8(iid.item_type) = 'membership' THEN 'memberships'
                    WHEN lowerUTF8(iid.item_type) IN ('product', 'Product') THEN 'products'
                    WHEN lowerUTF8(iid.item_type) IN ('service', 'Service') THEN 'services'
                    WHEN lowerUTF8(iid.item_type) IN ('class', 'appointment', 'Appointment') THEN 'training'
                    WHEN lowerUTF8(iid.item_type) = 'package' THEN 'packages'
                    ELSE 'other'
                END AS revenue_type,
                SUM(iid.total_price) AS revenue
            FROM invoice_items_detail AS iid
            INNER JOIN invoice_details AS idt ON iid.invoice_id = idt.id
            WHERE idt.invoice_date BETWEEN toDate('{$yearStart}') AND toDate('{$yearEnd}')
                AND idt.status = '1'
                AND {$saleableFilter}
            GROUP BY month_start, month_abbr, revenue_type
            ORDER BY month_start ASC
        ";

        $results = $clickhouse->select($sql);

        // Initialize monthly data structure
        $monthlyData = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        foreach ($months as $month) {
            $monthlyData[$month] = [
                'memberships' => 0,
                'products' => 0,
                'services' => 0,
                'training' => 0,
                'packages' => 0,
                'total' => 0
            ];
        }

        // Populate monthly data
        foreach ($results as $row) {
            $month = $row['month_abbr'] ?? '';
            $type = $row['revenue_type'] ?? 'other';
            $revenue = (float)($row['revenue'] ?? 0);
            
            if (isset($monthlyData[$month]) && isset($monthlyData[$month][$type])) {
                $monthlyData[$month][$type] = $revenue;
                $monthlyData[$month]['total'] += $revenue;
            }
        }

        // Calculate column totals
        $columnTotals = [
            'memberships' => 0,
            'products' => 0,
            'services' => 0,
            'training' => 0,
            'packages' => 0,
            'total' => 0
        ];

        foreach ($monthlyData as $month => $data) {
            foreach ($columnTotals as $key => $value) {
                if ($key !== 'total') {
                    $columnTotals[$key] += $data[$key] ?? 0;
                }
            }
        }
        $columnTotals['total'] = array_sum(array_slice($columnTotals, 0, -1));

        // Get all dashboards for the tab menu
        try {
            $allDashboards = $this->dashboardService->listDashboards();
        } catch (\Exception $e) {
            $allDashboards = [];
        }

        // Add financial dashboards at the beginning of the list
        $financialDashboards = [
            [
                'type' => 'financial',
                'title' => 'Financial Card View',
                'route' => 'dashboards.financial'
            ],
            [
                'type' => 'financial-table',
                'title' => 'Financial Table View',
                'route' => 'dashboards.financial-table'
            ]
        ];
        $allDashboards = array_merge($financialDashboards, $allDashboards);

        return view('dashboards.financial-table', [
            'monthlyData' => $monthlyData,
            'columnTotals' => $columnTotals,
            'currentYear' => $currentYear,
            'allDashboards' => $allDashboards
        ]);
    }
}

