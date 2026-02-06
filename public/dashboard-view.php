<?php

// Increase memory limit for dashboard operations
ini_set('memory_limit', '256M');

// Get parameters
$dashboardType = isset($_GET['type']) ? $_GET['type'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

if (empty($dashboardType)) {
    header('Location: /business/analytics/dashboards');
    exit;
}

$isFinancial = ($dashboardType === 'financial');
$isFinancialTable = ($dashboardType === 'financial-table');
$isFinancialDashboard = $isFinancial || $isFinancialTable;

// Build API endpoint
if ($isFinancial) {
    $endpoint = 'dashboards/financial/data';
} elseif ($isFinancialTable) {
    $endpoint = 'dashboards/financial-table/data';
} else {
    $endpoint = 'dashboards/' . urlencode($dashboardType);
    $params = [];

    // If custom dates are provided, use them instead of period
    if ($startDate && $endDate) {
        $params[] = 'start_date=' . urlencode($startDate);
        $params[] = 'end_date=' . urlencode($endDate);
    } elseif ($period !== 'all_time') {
        // Only use period if no custom dates are provided
        $params[] = 'period=' . urlencode($period);
    }

    if (!empty($params)) {
        $endpoint .= '?' . implode('&', $params);
    }
}

// Fetch dashboard data
$response = Service_AnalyticService::apiRequest($endpoint);
$dashboard = null;
$error = null;
$allDashboards = [];
$financialData = null;
$financialTableData = null;

if (isset($response['success']) && $response['success']) {
    if ($isFinancialDashboard) {
        $financialData = isset($response['data']) ? $response['data'] : null;
        // Debug: Log if financialData is empty or null
        if (empty($financialData) && $isFinancial) {
            $error = 'Financial data is empty. API Response: ' . json_encode($response, JSON_PRETTY_PRINT);
        }
    } else {
        $dashboard = isset($response['data']) ? $response['data'] : null;
    }
} else {
    $error = isset($response['error']) ? $response['error'] : (isset($response['message']) ? $response['message'] : 'Failed to load dashboard');
    // Include full response for debugging
    if ($isFinancialDashboard) {
        $error .= ' | Full Response: ' . json_encode($response, JSON_PRETTY_PRINT);
    }
}

// Fetch all dashboards for navigation
$dashboardsResponse = Service_AnalyticService::apiRequest('dashboards');
if (isset($dashboardsResponse['success']) && $dashboardsResponse['success']) {
    $allDashboards = isset($dashboardsResponse['data']) ? $dashboardsResponse['data'] : [];
}

if ($isFinancialTable && $financialData) {
    $financialTableData = $financialData;
}

$dashboardTitle = $isFinancial ? 'Financial Dashboard' : ($isFinancialTable ? 'Financial Table' : (isset($dashboard['title']) ? $dashboard['title'] : ucfirst($dashboardType)));
$widgets = isset($dashboard['widgets']) ? $dashboard['widgets'] : [];

$periods = [
    'all_time' => 'All Time',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    'year' => 'This Year',
    'last_year' => 'Last Year'
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="/dashboard-view.css" />

<div class="main-content-inner">
    <div id="breadcrumbs" class="breadcrumbs">
        <script type="text/javascript">
            try {
                ace.settings.check('breadcrumbs', 'fixed');
            } catch (e) {
            }
        </script>

        <ul class="breadcrumb">
            <li>
                <i class="icon-home green"></i>
                <a href="/business/index/dashboard">Home</a>
            </li>            
            <li>
                <a href="/business/analytics">Analytics</a>
            </li>
            <li class="active">Dashboard</li>
        </ul>
    </div>
    <div class="page-content">


        <div style="min-height: 100vh;">
            <!-- Dashboard Tabs Section -->
            <div class="dashboard-tabs-container">
                <div class="container-full">
                    <!-- Top Bar -->
                    <div class="dashboard-tabs-top-bar">
                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <h1>Dashboards</h1>
                            </div>
                            <!-- Period Filters -->
                            <?php if (!$isFinancialDashboard): ?>
                                <div class="col-xs-12 col-sm-6 text-right">
                                    <div style="display: inline-block;">
                                        <?php foreach ($periods as $periodKey => $periodLabel): ?>
                                            <?php
                                            $isActive = ($startDate && $endDate) ? false : ($period === $periodKey);
                                            $href = "/business/analytics/dashboard?type=" . urlencode($dashboardType) . "&period=" . urlencode($periodKey);
                                            ?>
                                            <a href="<?php echo htmlspecialchars($href); ?>"
                                                class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-default'; ?>"
                                                style="margin-left: 5px; margin-right: 5px;">
                                                <?php echo htmlspecialchars($periodLabel); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Horizontal Tabs Menu -->
                    <div class="dashboard-tabs-nav">
                        <ul class="nav nav-tabs" style="border-bottom: none;">
                            <!-- Reports Tab -->
                            <li>
                                <a href="/business/analytics/reports" class="dashboard-tab"
                                    style="padding: 12px 16px; display: inline-block; border-bottom: 2px solid transparent; color: #666; text-decoration: none;">
                                    Reports
                                </a>
                            </li>
                            <?php foreach ($allDashboards ?? [] as $dash): ?>
                                <?php
                                $dashType = isset($dash['type']) ? $dash['type'] : '';
                                $dashTitle = isset($dash['title']) ? $dash['title'] : '';
                                $isCurrent = ($dashboardType === $dashType);

                                // Handle custom routes if provided
                                if (isset($dash['route'])) {
                                    $dashHref = $dash['route'];
                                } else {
                                    $dashHref = "/business/analytics/dashboard?type=" . urlencode($dashType) . "&period=" . urlencode($period);
                                }
                                ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($dashHref); ?>"
                                        class="dashboard-tab <?php echo $isCurrent ? 'active' : ''; ?>"
                                        style="padding: 12px 16px; display: inline-block; border-bottom: 2px solid <?php echo $isCurrent ? '#33a1df' : 'transparent'; ?>; color: <?php echo $isCurrent ? '#33a1df' : '#666'; ?>; text-decoration: none;">
                                        <?php echo htmlspecialchars($dashTitle); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Dashboard Title and Description -->
            <?php if (!$isFinancialDashboard && isset($dashboard['title'])): ?>
                <div class="container-full">
                    <div class="container-full">
                        <h2><?php echo htmlspecialchars($dashboard['title']); ?></h2>
                        <?php if (!empty($dashboard['description'])): ?>
                            <p><?php echo htmlspecialchars($dashboard['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($isFinancialDashboard): ?>
                <div class="container-full">
                    <div class="container-full">
                        <h2><?php echo htmlspecialchars($dashboardTitle); ?></h2>
                    </div>
                </div>
            <?php endif; ?>

            <div style="min-height: calc(100vh - 80px);">

                <!-- Dashboard Content -->
                <div class="container-full" style="padding: 24px 15px;">
                    <?php if ($error): ?>
                        <div class="alert alert-info"
                            style="background-color: rgba(51, 161, 223, 0.1); border: 1px solid rgba(51, 161, 223, 0.3); color: #1e6fa8; margin-bottom: 24px;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isFinancialTable && $financialTableData): ?>
                        <!-- Financial Table View -->
                        <?php
                        $monthlyData = isset($financialTableData['monthly_data']) ? $financialTableData['monthly_data'] : [];
                        $columnTotals = isset($financialTableData['column_totals']) ? $financialTableData['column_totals'] : [];
                        function formatCurrency($amount)
                        {
                            return '$' . number_format($amount, 2);
                        }
                        ?>
                        <div class="widget-card">
                            <div class="widget-header">
                                <h3>Financial Table - Last 12 Months</h3>
                            </div>
                            <div class="widget-body">
                                <div style="overflow-x: auto;">
                                    <table class="table table-bordered" style="width: 100%;">
                                        <thead>
                                            <tr style="background-color: #f8f9fa;">
                                                <th style="padding: 12px; text-align: left; font-weight: 600;">Month</th>
                                                <th style="padding: 12px; text-align: right; font-weight: 600;">Memberships
                                                </th>
                                                <th style="padding: 12px; text-align: right; font-weight: 600;">Products
                                                </th>
                                                <th style="padding: 12px; text-align: right; font-weight: 600;">Services
                                                </th>
                                                <th style="padding: 12px; text-align: right; font-weight: 600;">Training
                                                </th>
                                                <th style="padding: 12px; text-align: right; font-weight: 600;">Packages
                                                </th>
                                                <th
                                                    style="padding: 12px; text-align: right; font-weight: 600; background-color: #e9ecef;">
                                                    TOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthlyData as $monthKey => $data): ?>
                                                <tr>
                                                    <td style="padding: 10px; font-weight: 500;">
                                                        <?php echo htmlspecialchars($data['month_year'] ?? $data['month_abbr'] ?? $monthKey); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: right;">
                                                        <?php echo formatCurrency($data['memberships'] ?? 0); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: right;">
                                                        <?php echo formatCurrency($data['products'] ?? 0); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: right;">
                                                        <?php echo formatCurrency($data['services'] ?? 0); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: right;">
                                                        <?php echo formatCurrency($data['training'] ?? 0); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: right;">
                                                        <?php echo formatCurrency($data['packages'] ?? 0); ?>
                                                    </td>
                                                    <td
                                                        style="padding: 10px; text-align: right; font-weight: 600; background-color: #f8f9fa;">
                                                        <?php echo formatCurrency($data['total'] ?? 0); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr
                                                style="background-color: #f8f9fa; font-weight: 600; border-top: 2px solid #dee2e6;">
                                                <td style="padding: 10px; font-weight: 700;">TOTAL</td>
                                                <td style="padding: 10px; text-align: right; font-weight: 700;">
                                                    <?php echo formatCurrency($columnTotals['memberships'] ?? 0); ?>
                                                </td>
                                                <td style="padding: 10px; text-align: right; font-weight: 700;">
                                                    <?php echo formatCurrency($columnTotals['products'] ?? 0); ?>
                                                </td>
                                                <td style="padding: 10px; text-align: right; font-weight: 700;">
                                                    <?php echo formatCurrency($columnTotals['services'] ?? 0); ?>
                                                </td>
                                                <td style="padding: 10px; text-align: right; font-weight: 700;">
                                                    <?php echo formatCurrency($columnTotals['training'] ?? 0); ?>
                                                </td>
                                                <td style="padding: 10px; text-align: right; font-weight: 700;">
                                                    <?php echo formatCurrency($columnTotals['packages'] ?? 0); ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; text-align: right; font-weight: 700; color: var(--brand-primary); background-color: #e9ecef;">
                                                    <?php echo formatCurrency($columnTotals['total'] ?? 0); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($isFinancial && $financialData): ?>
                        <!-- Financial Card View -->
                        <?php
                        // Calculate dates
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $weekStart = date('Y-m-d', strtotime('monday this week'));
                        $prevWeekStart = date('Y-m-d', strtotime('monday last week'));
                        $prevWeekEnd = date('Y-m-d', strtotime('sunday last week'));
                        $monthStart = date('Y-m-01');
                        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
                        $yearStart = date('Y-01-01');
                        $lastYearStart = date('Y-01-01', strtotime('-1 year'));

                        // Date formatting functions
                        function formatDate($date)
                        {
                            $timestamp = strtotime($date);
                            $day = date('j', $timestamp);
                            $suffix = date('S', $timestamp); // Gets 'st', 'nd', 'rd', 'th'
                            return ucfirst(date('M', $timestamp)) . ' ' . $day . $suffix . ' ' . date('Y', $timestamp);
                        }

                        function formatMonthYear($date)
                        {
                            $timestamp = strtotime($date);
                            return strtoupper(date('M', $timestamp)) . ' ' . date('Y', $timestamp);
                        }

                        function formatYear($date)
                        {
                            return date('Y', strtotime($date));
                        }

                        function formatCurrency($amount)
                        {
                            return '$' . number_format($amount, 2);
                        }

                        // Period titles with formatted dates
                        $periods = [
                            'today' => 'Daily Sales - Sales Today ' . formatDate($today),
                            'yesterday' => 'Daily Sales - Sales Yesterday ' . formatDate($yesterday),
                            'week_to_date' => 'Week (to date) Sales - as of ' . formatDate($today),
                            'prev_week' => 'Previous Week Sales - ' . formatDate($prevWeekStart) . ' - ' . formatDate($prevWeekEnd),
                            'month_to_date' => 'Month (to date) Sales - as of ' . formatDate($today),
                            'last_month' => 'Last Month Sales - ' . formatMonthYear($lastMonthStart),
                            'year_to_date' => 'Year (to date) Sales - as of ' . formatDate($today),
                            'last_year' => 'Last Year Sales - ' . formatYear($lastYearStart)
                        ];

                        // Revenue card configuration with icons
                        $revenueCards = [
                            [
                                'type' => 'membership',
                                'label' => 'Membership Revenue',
                                'icon' => 'fas fa-id-card'
                            ],
                            [
                                'type' => 'products',
                                'label' => 'Products Revenue',
                                'icon' => 'fas fa-box'
                            ],
                            [
                                'type' => 'training',
                                'label' => 'Training Revenue',
                                'icon' => 'fas fa-graduation-cap'
                            ],
                            [
                                'type' => 'services',
                                'label' => 'Services Revenue',
                                'icon' => 'fas fa-concierge-bell'
                            ],
                            [
                                'type' => 'giftcards',
                                'label' => 'Gift Card Sales',
                                'icon' => 'fas fa-gift'
                            ],
                            [
                                'type' => 'total',
                                'label' => 'Total Revenue',
                                'icon' => 'fas fa-calculator'
                            ]
                        ];
                        ?>
                        <div style="margin-bottom: 24px;">
                            <p style="color: #6c757d; font-size: 14px;">Revenue breakdown by type and time period</p>
                        </div>

                        <?php 
                        // Debug: Check if financialData exists and has data
                        if (empty($financialData)): ?>
                            <div class="alert alert-warning" style="padding: 20px; margin: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                                <strong>Warning:</strong> No financial data received from API.
                                <?php if (isset($error)): ?>
                                    <br>Error: <?php echo htmlspecialchars($error); ?>
                                <?php endif; ?>
                                <?php if (isset($response)): ?>
                                    <br>Response: <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;"><?php echo htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)); ?></pre>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php 
                            // Debug: Show sample data structure (only if all values are zero)
                            $hasNonZeroData = false;
                            foreach ($financialData as $periodKey => $periodData) {
                                if (is_array($periodData)) {
                                    foreach ($periodData as $key => $value) {
                                        if ($key !== 'trend' && $key !== 'locations' && (float)$value > 0) {
                                            $hasNonZeroData = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                            if (!$hasNonZeroData): ?>
                                <div class="alert alert-info" style="padding: 15px; margin: 20px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; font-size: 12px;">
                                    <strong>Info:</strong> All revenue values are zero. This could mean:
                                    <ul style="margin: 10px 0; padding-left: 20px;">
                                        <li>No transactions found in the database for the selected periods</li>
                                        <li>All transactions have status other than 'active'</li>
                                        <li>Date filters are excluding all data</li>
                                    </ul>
                                    <details style="margin-top: 10px;">
                                        <summary style="cursor: pointer; font-weight: bold;">Show API Response Structure</summary>
                                        <pre style="background: #f5f5f5; padding: 10px; margin-top: 10px; overflow: auto; max-height: 200px; font-size: 11px;"><?php echo htmlspecialchars(json_encode($financialData, JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php foreach ($periods as $periodKey => $periodLabel): ?>
                            <?php if (isset($financialData[$periodKey])): ?>
                                <?php $periodData = $financialData[$periodKey]; ?>
                                <div class="section-bg-dark" style="border-radius: 8px; padding: 24px; margin-bottom: 24px;">
                                    <!-- Section Header -->
                                    <div class="section-header">
                                        <h2 class="section-title"><?php echo htmlspecialchars($periodLabel); ?></h2>
                                    </div>

                                    <!-- Revenue Cards Grid: 2x3 + Total Card -->
                                    <div class="revenue-grid">
                                        <!-- 6 Revenue Cards in 2x3 grid -->
                                        <div class="revenue-cards-container">
                                            <?php foreach ($revenueCards as $card): ?>
                                                <?php
                                                if ($card['type'] === 'total') {
                                                    $amount = isset($periodData['total']) ? (float)$periodData['total'] : 0;
                                                } else {
                                                    $amount = isset($periodData[$card['type']]) ? (float)$periodData[$card['type']] : 0;
                                                }
                                                $cardClass = 'revenue-card-' . $card['type'];
                                                ?>
                                                <div class="revenue-card <?php echo $cardClass; ?>">
                                                    <div class="revenue-card-bg-icons">
                                                        <i class="<?php echo $card['icon']; ?> revenue-card-bg-icon"></i>
                                                    </div>
                                                    <div class="revenue-card-amount">
                                                        <?php echo formatCurrency($amount); ?>
                                                    </div>
                                                    <div class="revenue-card-separator"></div>
                                                    <div class="revenue-card-label">
                                                        <?php echo htmlspecialchars($card['label']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Total Revenue Card (Large, on the right) -->
                                        <div class="total-revenue-wrapper">
                                            <div class="total-revenue-card">
                                                <div class="total-revenue-title">Total revenue</div>
                                                <div class="total-revenue-amount">
                                                    <?php echo number_format(isset($periodData['total']) ? (float)$periodData['total'] : 0, 0); ?>
                                                </div>

                                                <!-- Line Chart -->
                                                <?php if (isset($periodData['trend']) && !empty($periodData['trend']['labels'])): ?>
                                                    <div style="height: 120px; margin-bottom: 20px;">
                                                        <canvas id="trend-chart-<?php echo $periodKey; ?>"
                                                            style="max-height: 120px;"></canvas>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Regular Dashboard View -->
                        <div class="row container-full" id="dashboard-widgets">
                            <?php foreach ($widgets as $widget): ?>
                                <div class="col-lg-6 col-md-12" style="margin-bottom: 24px;">
                                    <div class="panel panel-default widget-container"
                                        data-widget-id="<?php echo htmlspecialchars($widget['id']); ?>"
                                        style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #ddd;">
                                        <div class="panel-heading">
                                            <h3 class="panel-title"  title="<?php echo htmlspecialchars($widget['title']); ?>">
                                                <?php echo htmlspecialchars($widget['title']); ?>
                                            </h3>
                                        </div>
                                        <div class="panel-body" style="padding: 16px;">
                                            <?php if (isset($widget['error'])): ?>
                                                <div class="text-danger" style="font-size: 14px;">
                                                    <?php echo htmlspecialchars($widget['error']); ?>
                                                </div>
                                            <?php elseif ($widget['type'] === 'metric' && !empty($widget['data'])): ?>
                                                <?php
                                                $metricCount = min(count($widget['data'][0]), 4);
                                                $colClass = $metricCount === 1 ? 'col-md-12' : ($metricCount === 2 ? 'col-md-6' : ($metricCount === 3 ? 'col-md-4' : 'col-md-3'));
                                                ?>
                                                <div style="margin-bottom: <?php echo isset($widget['chartData']) && !empty($widget['chartData']) ? '16px' : '0'; ?>;">
                                                    <div class="row">
                                                        <?php foreach ($widget['data'][0] as $key => $value): ?>
                                                            <?php
                                                            $rawValue = $value;
                                                            if (is_numeric($value)) {
                                                                if ($value >= 1000000) {
                                                                    $formatted = number_format($value / 1000000, 1);
                                                                    $value = rtrim(rtrim($formatted, '0'), '.') . 'M';
                                                                } elseif ($value >= 1000) {
                                                                    $formatted = number_format($value / 1000, 1);
                                                                    $value = rtrim(rtrim($formatted, '0'), '.') . 'K';
                                                                } else {
                                                                    $value = number_format($value, is_float($rawValue) ? 2 : 0);
                                                                }
                                                            }
                                                            ?>
                                                            <div class="<?php echo $colClass; ?>"
                                                                style="text-align: center; margin-bottom: 16px;">
                                                                <div style="font-size: 24px; font-weight: bold; color: #333;">
                                                                    <?php echo htmlspecialchars($value); ?>
                                                                </div>
                                                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                                                    <?php echo htmlspecialchars(str_replace('_', ' ', $key)); ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php if (isset($widget['chartData']) && !empty($widget['chartData']) && isset($widget['config']['chart'])): ?>
                                                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
                                                        <div class="chart-container" style="height: 250px;">
                                                            <canvas id="chart-<?php echo htmlspecialchars($widget['id']); ?>"></canvas>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif ($widget['type'] === 'table' && !empty($widget['data'])): ?>
                                                <div style="overflow-x: auto;">
                                                    <table class="table table-bordered table-striped" style="font-size: 14px;">
                                                        <thead>
                                                            <tr style="background-color: #f8f9fa;">
                                                                <?php
                                                                $columns = isset($widget['config']['columns']) ? $widget['config']['columns'] : array_keys($widget['data'][0]);
                                                                foreach ($columns as $col):
                                                                    $headerText = str_replace('_', ' ', $col);
                                                                    ?>
                                                                    <th style="padding: 8px 12px; text-align: left; font-size: 12px; font-weight: 500; color: #666; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;"
                                                                        title="<?php echo htmlspecialchars($headerText); ?>">
                                                                        <?php echo htmlspecialchars($headerText); ?>
                                                                    </th>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach (array_slice($widget['data'], 0, 10) as $row): ?>
                                                                <tr>
                                                                    <?php foreach ($columns as $col): ?>
                                                                        <td style="padding: 8px 12px; color: #333;">
                                                                            <?php
                                                                            $value = isset($row[$col]) ? $row[$col] : '';
                                                                            $isDateCol = Service_AnalyticService::isDateColumn($col);

                                                                            if ($isDateCol && $value) {
                                                                                echo htmlspecialchars(Service_AnalyticService::formatDate($value));
                                                                            } elseif (is_numeric($value)) {
                                                                                echo htmlspecialchars(Service_AnalyticService::formatNumber($value, is_float($value) ? 2 : 0));
                                                                            } else {
                                                                                echo htmlspecialchars(Service_AnalyticService::removeItemPrefix($value));
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    <?php if (count($widget['data']) > 10): ?>
                                                        <div style="font-size: 12px; color: #999; margin-top: 8px; text-align: center;">
                                                            Showing 10 of <?php echo count($widget['data']); ?> rows</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (in_array($widget['type'], ['bar', 'line', 'pie']) && !empty($widget['data'])): ?>
                                                <div class="chart-container">
                                                    <canvas id="chart-<?php echo htmlspecialchars($widget['id']); ?>"></canvas>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 14px; color: #666; text-align: center; padding: 32px;">No
                                                    data
                                                    available</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($widgets)): ?>
                                <div class="col-lg-12">
                                    <div class="panel panel-default"
                                        style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #ddd; padding: 48px; text-align: center;">
                                        <i data-lucide="inbox"
                                            style="width: 64px; height: 64px; color: #999; margin: 0 auto 16px; display: block;"></i>
                                        <h3 style="font-size: 20px; font-weight: 600; color: #333; margin-bottom: 8px;">No
                                            Widgets
                                            Available</h3>
                                        <p style="color: #666;">This dashboard doesn't have any widgets configured.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                lucide.createIcons();

                <?php if ($isFinancial && $financialData): ?>
                    // Render financial dashboard trend charts
                    const financialData = <?php echo json_encode($financialData); ?>;
                    const periods = ['today', 'yesterday', 'week_to_date', 'prev_week', 'month_to_date', 'last_month', 'year_to_date', 'last_year'];

                    periods.forEach(function (periodKey) {
                        if (financialData[periodKey] && financialData[periodKey].trend && financialData[periodKey].trend.labels) {
                            const trend = financialData[periodKey].trend;
                            const canvas = document.getElementById('trend-chart-' + periodKey);
                            if (!canvas) return;

                            new Chart(canvas, {
                                type: 'line',
                                data: {
                                    labels: trend.labels,
                                    datasets: [{
                                        label: 'Revenue',
                                        data: trend.data,
                                        borderColor: '#007bff',
                                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.4,
                                        pointRadius: 0,
                                        pointHoverRadius: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            enabled: true,
                                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                            titleColor: '#fff',
                                            bodyColor: '#fff',
                                            borderColor: '#007bff',
                                            borderWidth: 1
                                        }
                                    },
                                    scales: {
                                        x: {
                                            display: true,
                                            grid: {
                                                display: false
                                            },
                                            ticks: {
                                                color: 'rgba(255, 255, 255, 0.7)',
                                                font: {
                                                    size: 10
                                                }
                                            }
                                        },
                                        y: {
                                            display: false,
                                            grid: {
                                                display: false
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    });
                <?php else: ?>
                    // Render charts for metric widgets with chart data
                    const widgets = <?php echo json_encode($widgets); ?>;

                    widgets.forEach(function (widget) {
                        // Handle metric widgets with charts
                        if (widget.type === 'metric' && widget.chartData && widget.chartData.length > 0 && widget.config && widget.config.chart) {
                            const canvas = document.getElementById('chart-' + widget.id);
                            if (!canvas) return;

                            const chartConfig = widget.config.chart;
                            const chartData = widget.chartData;
                            const chartType = chartConfig.type || 'pie';
                            const labelField = chartConfig.labelField || Object.keys(chartData[0] || {})[0];
                            const valueField = chartConfig.valueField || Object.keys(chartData[0] || {})[1];

                            const labels = chartData.map(row => {
                                let label = row[labelField] || '';
                                if (typeof label === 'string') {
                                    // Remove prefixes
                                    const prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
                                    for (const prefix of prefixes) {
                                        if (label.toLowerCase().startsWith(prefix.toLowerCase())) {
                                            label = label.substring(prefix.length).trim();
                                            break;
                                        }
                                    }
                                    if (label.toLowerCase() === 'n/a' || label.toLowerCase() === 'na') {
                                        label = 'Others';
                                    }
                                    // Truncate labels for bar charts to 15 characters
                                    if (chartType === 'bar' && label.length > 15) {
                                        label = label.substring(0, 12) + '...';
                                    }
                                }
                                return label;
                            });
                            const values = chartData.map(row => parseFloat(row[valueField] || 0));

                            new Chart(canvas, {
                                type: chartType,
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: valueField.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                                        data: values,
                                        backgroundColor: chartType === 'pie' ?
                                            ['#33a1df', '#4ade80', '#fbbf24', '#f87171', '#a78bfa', '#60a5fa', '#34d399', '#fb923c', '#f472b6', '#818cf8'] :
                                            '#33a1df',
                                        borderColor: '#33a1df',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom'
                                        }
                                    },
                                    scales: chartType !== 'pie' ? {
                                        y: {
                                            beginAtZero: true
                                        }
                                    } : {}
                                }
                            });
                            return;
                        }

                        // Handle regular chart widgets (bar, line, pie)
                        if (!['bar', 'line', 'pie'].includes(widget.type) || !widget.data || widget.data.length === 0) {
                            return;
                        }

                        const canvas = document.getElementById('chart-' + widget.id);
                        if (!canvas) return;

                        const data = widget.data;
                        const headers = Object.keys(data[0]);
                        const labelKey = headers[0];
                        const valueKey = headers.find(h => h.toLowerCase().includes('total') || h.toLowerCase().includes('sales') || h.toLowerCase().includes('amount') || h.toLowerCase().includes('revenue') || h.toLowerCase().includes('count')) || headers[1];

                        const labels = data.map(row => {
                            let label = row[labelKey];
                            if (typeof label === 'string') {
                                // Remove prefixes
                                const prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
                                for (const prefix of prefixes) {
                                    if (label.toLowerCase().startsWith(prefix.toLowerCase())) {
                                        label = label.substring(prefix.length).trim();
                                        break;
                                    }
                                }
                                if (label.toLowerCase() === 'n/a' || label.toLowerCase() === 'na') {
                                    label = 'Others';
                                }
                                // Truncate labels for bar charts to 15 characters
                                if (widget.type === 'bar' && label.length > 15) {
                                    label = label.substring(0, 12) + '...';
                                }
                            }
                            return label;
                        });
                        const values = data.map(row => parseFloat(row[valueKey]) || 0);

                        const chartType = widget.type === 'pie' ? 'pie' : (widget.type === 'line' ? 'line' : 'bar');

                        new Chart(canvas, {
                            type: chartType,
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                                    data: values,
                                    backgroundColor: chartType === 'pie' ?
                                        ['#33a1df', '#4ade80', '#fbbf24', '#f87171', '#a78bfa', '#60a5fa', '#34d399', '#fb923c', '#f472b6', '#818cf8'] :
                                        '#33a1df',
                                    borderColor: '#33a1df',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: chartType !== 'pie' ? {
                                    y: {
                                        beginAtZero: true
                                    },
                                    x: chartType === 'bar' ? {
                                        ticks: {
                                            callback: function(value, index) {
                                                const label = this.getLabelForValue(value);
                                                // Truncate labels for bar charts to 15 characters
                                                if (typeof label === 'string' && label.length > 15) {
                                                    return label.substring(0, 12) + '...';
                                                }
                                                return label;
                                            },
                                            maxRotation: 45,
                                            minRotation: 0
                                        }
                                    } : {}
                                } : {}
                            }
                        });
                    });
                <?php endif; ?>
            </script>
        </div>
    </div>
</div>