<?php

// Parse URL path directly: /business/analytics/reports/predefined/{type} or /business/analytics/reports/ai-generated or /business/analytics/reports
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = basename($_SERVER['SCRIPT_NAME']);

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Normalize path - remove script name if present
if (strpos($path, $scriptName) !== false) {
    $path = str_replace('/' . $scriptName, '', $path);
}

// Handle different URL patterns
$reportType = '';
$isAiGenerated = false;

// Parse URL patterns
if (preg_match('#/business/analytics/reports/predefined/([^/?]+)#', $path, $matches)) {
    // URL: /business/analytics/reports/predefined/{type}
    $reportType = urldecode($matches[1]);
} elseif (preg_match('#/business/analytics/reports/ai-generated#', $path)) {
    // URL: /business/analytics/reports/ai-generated
    $isAiGenerated = true;
    $reportType = isset($_GET['question']) ? 'ai-generated' : '';
} elseif (preg_match('#/reports/predefined/([^/?]+)#', $path, $matches)) {
    // Fallback: /reports/predefined/{type}
    $reportType = urldecode($matches[1]);
} elseif (preg_match('#/reports/ai-generated#', $path)) {
    // Fallback: /reports/ai-generated
    $isAiGenerated = true;
    $reportType = isset($_GET['question']) ? 'ai-generated' : '';
} elseif (isset($_GET['type'])) {
    // Fallback: query parameter (for direct file access)
    $reportType = $_GET['type'];
    if ($reportType === 'ai-generated') {
        $isAiGenerated = true;
    }
}

/**
 * Generate SQL from report key (simplified version for PHP)
 */
function generateSqlFromReportKey($reportKey, $params = [])
{
    $start = $params['start'] ?? date('Y-m-d', strtotime('-1 month'));
    $end = $params['end'] ?? date('Y-m-d');
    $audience = $params['audience'] ?? 'all';
    $N = (int) ($params['N'] ?? 100);

    // Build date filter strings
    $dateFilter = "WHERE id.invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')";
    $dateFilterAnd = "AND id.invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')";
    $dateFilterAndO = "AND o.invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')";
    $dateFilterO = "WHERE invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')";
    $dateFilterAndFt = " AND ft.first_date BETWEEN toDate('{$start}') AND toDate('{$end}')";

    // Audience filters
    $audienceFilter = '';
    if ($audience === 'members') {
        $audienceFilter = 'AND o.is_member = 1';
    } elseif ($audience === 'non_members') {
        $audienceFilter = 'AND o.is_member = 0';
    }

    // SQL templates for each report key (all available reports)
    $templates = [
        'top_spenders' => "SELECT id.customer_name, SUM(iid.total_price) AS total_spent
FROM invoice_details id
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
WHERE (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental',
                        'giftcard', 'appointment', 'subscription','guestpass','sponsorship','warranty', 'adavanceBookingFee', 'membershiprental') 
       OR lowerUTF8(iid.item_type) LIKE 'misc%' 
       OR lowerUTF8(iid.item_type) LIKE 'Misc%')
  {$dateFilterAnd}
GROUP BY id.customer_id, id.customer_name
ORDER BY total_spent DESC
LIMIT {$N}",

        'members' => "SELECT distinct customer_name FROM invoice_details WHERE is_member=1 AND invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')",

        'non_members' => "SELECT distinct customer_name FROM invoice_details WHERE is_member=0 AND invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')",

        'product_performance' => "WITH top_customers AS (
    SELECT customer_id, customer_name, SUM(total_amount) AS total_spent
    FROM invoice_details
    WHERE invoice_date BETWEEN toDate('{$start}') AND toDate('{$end}')
    GROUP BY customer_id, customer_name
    ORDER BY total_spent DESC
    LIMIT {$N}
),
ranked_products AS (
    SELECT
        tc.customer_id,
        tc.customer_name,
        iid.item_name,
        SUM(iid.total_price) AS total_spent,
        ROW_NUMBER() OVER (
            PARTITION BY tc.customer_id
            ORDER BY SUM(iid.total_price) DESC
        ) AS rn
    FROM top_customers AS tc
    INNER JOIN invoice_details AS id
        ON tc.customer_id = id.customer_id
    INNER JOIN invoice_items_detail AS iid
        ON iid.invoice_id = id.id
    WHERE iid.item_type = 'product'
      {$dateFilterAndO}
    GROUP BY tc.customer_id, tc.customer_name, iid.item_name
) SELECT tc.customer_name AS customer, item_name AS ITEM, sum(total_spent) AS total_spent 
FROM ranked_products  
WHERE rn <= {$N} 
GROUP BY customer, ITEM, rn 
ORDER BY total_spent DESC, rn ASC",

        'prd_top_sold' => "SELECT oi.item_name, oi.category, SUM(oi.quantity) AS units, SUM(oi.total_price) AS revenue
FROM invoice_items_detail oi 
INNER JOIN invoice_details o ON o.id = oi.invoice_id
WHERE oi.item_type = 'product' 
  AND oi.invoice_id NOT IN (SELECT DISTINCT invoiceId FROM Range_appointments)
  {$dateFilterAndO}
  {$audienceFilter}
  AND oi.SKU NOT IN ('')
  AND oi.category NOT IN ('Range Services', 'Memberships')
GROUP BY oi.item_name, oi.category
ORDER BY units DESC
LIMIT {$N}",

        'prd_turnover' => "SELECT oi.item_name, oi.category, count() AS lines, sum(oi.quantity) AS units
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='product' {$dateFilterAndO}
GROUP BY oi.item_name, oi.category
ORDER BY lines DESC
LIMIT {$N}",

        'prd_least' => "SELECT oi.item_name, sum(oi.quantity) AS units
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='product' 
  {$dateFilterAndO}
GROUP BY oi.item_name
ORDER BY units ASC
LIMIT {$N}",

        'prd_slow_movers' => "SELECT 
    pi.name AS product_name,
    pi.qoh AS quantity_on_hand,
    ifNull(dateDiff('day', max(o.invoice_date), today()), -1) AS days_since_last_sale
FROM product_inventory AS pi
LEFT JOIN invoice_items_detail AS iid
    ON iid.SKU = pi.sku
LEFT JOIN invoice_details AS o
    ON o.id = iid.invoice_id
GROUP BY pi.sku, pi.qoh, pi.name
ORDER BY days_since_last_sale DESC
LIMIT {$N}",

        'cln_sale_report_dly' => "SELECT 
    formatDateTime(id.invoice_date, '%d %b %Y') AS date,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    {$dateFilterAnd}
GROUP BY id.invoice_date, iid.item_name
ORDER BY id.invoice_date DESC, total_sales DESC",

        'cln_sale_report_wkly' => "SELECT 
    formatDateTime(toStartOfWeek(id.invoice_date), '%d %b %Y') AS week_start,
    formatDateTime(toStartOfWeek(id.invoice_date) + INTERVAL 6 DAY, '%d %b %Y') AS week_end,
    iid.item_name,
    SUM(iid.total_price) AS total_sales,
    COUNT(DISTINCT id.id) AS invoice_count
FROM invoice_details AS id
INNER JOIN invoice_items_detail AS iid 
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    {$dateFilterAnd}
GROUP BY 
    toStartOfWeek(id.invoice_date),
    iid.item_name
ORDER BY 
    toStartOfWeek(id.invoice_date) DESC, 
    total_sales DESC",

        'cln_sale_report_mnthly' => "SELECT
    formatDateTime(toStartOfMonth(id.invoice_date), '%b %Y') AS month,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
 {$dateFilterAnd}
GROUP BY toStartOfMonth(id.invoice_date), iid.item_name
ORDER BY toStartOfMonth(id.invoice_date) DESC, total_sales DESC",

        'sale_by_category' => "SELECT
    iid.category,
    iid.item_name,
    COUNT(DISTINCT id.id) as invoice_count,
    sum(iid.quantity) as qty_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    {$dateFilterAnd}
GROUP BY iid.category, iid.item_name
ORDER BY iid.category, total_sales DESC",

        'sale_by_subCategory' => "SELECT
    iid.item_name,
    iid.subcategory,
    COUNT(DISTINCT id.id) as invoice_count,
    COUNT(iid.quantity) as item_count,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    {$dateFilterAnd}
GROUP BY iid.item_name, iid.subcategory
ORDER BY iid.subcategory, total_sales DESC",

        'Trans_count_products' => "SELECT
    iid.item_name,
    COUNT(DISTINCT id.id) as transaction_count,
    sum(iid.quantity) as Quantity_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
  {$dateFilterAnd}
GROUP BY iid.item_name
ORDER BY transaction_count DESC",

        'cln_sale_mem_report_dly' => "SELECT 
    formatDateTime(id.invoice_date, '%d %b %Y') AS date,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
    {$dateFilterAnd}
GROUP BY id.invoice_date, iid.item_name
ORDER BY id.invoice_date DESC, total_sales DESC",

        'cln_sale_mem_report_wkly' => "SELECT 
    formatDateTime(toStartOfWeek(id.invoice_date), '%d %b %Y') AS week_start,
    formatDateTime(toStartOfWeek(id.invoice_date) + INTERVAL 6 DAY, '%d %b %Y') AS week_end,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid 
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
    {$dateFilterAnd}
GROUP BY toStartOfWeek(id.invoice_date), iid.item_name
ORDER BY toStartOfWeek(id.invoice_date) ASC, total_sales DESC",

        'cln_sale_mem_report_mnthly' => "SELECT
    formatDateTime(toStartOfMonth(id.invoice_date), '%b %Y') AS month,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
 {$dateFilterAnd}
GROUP BY toStartOfMonth(id.invoice_date), iid.item_name
ORDER BY toStartOfMonth(id.invoice_date) ASC, total_sales DESC",

        'mem_sale_by_category' => "SELECT iid.category, 
COUNT(DISTINCT id.id) as invoice_count, sum(iid.quantity) as qty_sold, 
SUM(iid.total_price) as total_sales FROM invoice_details id 
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id 
WHERE iid.item_type = 'membership' 
    {$dateFilterAnd}
GROUP BY iid.category
ORDER BY iid.category, total_sales DESC",

        'mem_Trans_count' => "SELECT
   TRIM(iid.item_name) as item_name,
    COUNT(DISTINCT id.id) as transaction_count,
    sum(iid.quantity) as times_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
  {$dateFilterAnd}
GROUP BY TRIM(iid.item_name)
ORDER BY transaction_count DESC",

        'svc_top_new' => "WITH first_dt AS (
    SELECT 
        customer_id, 
        MIN(invoice_date) AS first_date 
    FROM invoice_details 
    GROUP BY customer_id
)
SELECT 
    iid.item_name AS service_name, 
    COUNT(DISTINCT ft.customer_id) AS new_customers
FROM first_dt ft
INNER JOIN invoice_details id 
    ON id.customer_id = ft.customer_id 
    AND id.invoice_date = ft.first_date
INNER JOIN invoice_items_detail iid 
    ON iid.invoice_id = id.id
WHERE 
    iid.item_type = 'service'
   {$dateFilterAndFt}
GROUP BY service_name
ORDER BY new_customers DESC
LIMIT {$N}",

        'svc_first_service_customers' => "WITH first_dt AS (
  SELECT customer_id, min(invoice_date) AS first_date FROM invoice_details GROUP BY customer_id
)
SELECT distinct o.customer_name as CustomerName, oi.item_name as Service, formatDateTime(ft.first_date, '%d %b %Y') as First_Purchase_Date
FROM first_dt ft
INNER JOIN invoice_details o ON o.customer_id=ft.customer_id 
AND o.invoice_date=ft.first_date
INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='service' {$dateFilterAndO}",

        'range_busiest_month' => "SELECT 
    formatDateTime(o.invoice_date, '%b %y') AS yyyymm, 
    countDistinct(o.customer_id) AS customers
FROM invoice_details o 
INNER JOIN invoice_items_detail oi 
    ON oi.invoice_id = o.id
INNER JOIN Range_appointments ra 
    ON CAST(ra.invoiceId AS UInt64) = o.id
WHERE oi.item_type = 'service'
{$dateFilterAndO}
  {$audienceFilter}
GROUP BY yyyymm
ORDER BY customers DESC
LIMIT 1",

        'range_busiest_dow' => "SELECT toDayOfWeek(o.invoice_date) AS dow,
    arrayElement(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], dow) AS day_name,
    round(countDistinct(o.customer_id) / countDistinct(toDate(o.invoice_date))) AS avg_customers
FROM invoice_details o 
INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='service' AND oi.category='Gun Ranges & Instruction'
  {$dateFilterAndO}
  {$audienceFilter}
GROUP BY dow
ORDER BY avg_customers DESC",

        'cls_popular' => "SELECT oi.item_name AS class_name, sum(oi.quantity) AS seats_sold, countDistinct(o.id) AS invoice_Count
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='class' {$dateFilterAndO}
GROUP BY class_name
ORDER BY seats_sold DESC
LIMIT {$N}",

        'cls_new_customers' => "WITH first_dt AS (
  SELECT customer_id, min(invoice_date) AS first_date FROM invoice_details GROUP BY customer_id
)
SELECT oi.item_name AS class_name, count() AS new_customers
FROM first_dt ft
INNER JOIN invoice_details o ON o.customer_id=ft.customer_id AND o.invoice_date=ft.first_date
INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='class' {$dateFilterAndO}
GROUP BY class_name
ORDER BY new_customers DESC
LIMIT {$N}",

        'cls_top_spenders' => "SELECT o.customer_name, sum(oi.total_price) AS class_spend, count() AS class_lines
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='class' {$dateFilterAndO}
GROUP BY o.customer_name
ORDER BY class_spend DESC
LIMIT 100"
    ];

    return isset($templates[$reportKey]) ? $templates[$reportKey] : null;
}

// Handle AJAX request for running custom reports (to avoid CORS)
if (isset($_POST['action']) && $_POST['action'] === 'run_report' && isset($_POST['report_key'])) {
    header('Content-Type: application/json');

    $reportKey = $_POST['report_key'];
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-1 month'));
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $perPage = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 100;
    $audience = isset($_POST['audience']) ? $_POST['audience'] : 'all';
    $N = isset($_POST['N']) ? (int) $_POST['N'] : 100;

    // List of predefined report keys (Payment Reports)
    $predefinedReports = ['daily-sales', 'top-items', 'revenue-by-franchise', 'payments-by-method', 'refunds'];

    // Use predefined endpoint for Payment Reports, otherwise use /reports/run
    if (in_array($reportKey, $predefinedReports)) {
        // Fetch predefined report by key
        $endpoint = 'reports/predefined/' . urlencode($reportKey)
            . '?start_date=' . urlencode($startDate)
            . '&end_date=' . urlencode($endDate)
            . '&page=' . $page
            . '&per_page=' . $perPage;

        $response = Service_AnalyticService::apiRequest($endpoint, 'GET');
    } else {
        // Use /reports/run endpoint for custom reports
        $endpoint = 'reports/run';

        // First try with report_key (newer API version)
        $requestData = [
            'report_key' => $reportKey,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'page' => $page,
            'per_page' => $perPage,
            'audience' => $audience,
            'N' => $N
        ];

        $response = Service_AnalyticService::apiRequest($endpoint, 'POST', $requestData);

        // If API returns error about sql field being required, generate SQL and retry
        if (
            isset($response['success']) && !$response['success'] &&
            (isset($response['error']) && stripos($response['error'], 'sql field is required') !== false ||
                isset($response['message']) && stripos($response['message'], 'sql field is required') !== false)
        ) {

            // Generate SQL from report_key
            $sql = generateSqlFromReportKey($reportKey, [
                'start' => $startDate,
                'end' => $endDate,
                'audience' => $audience,
                'N' => $N
            ]);

            if (!empty($sql)) {
                // Retry with sql field
                $requestData = [
                    'sql' => $sql,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'page' => $page,
                    'per_page' => $perPage
                ];

                $response = Service_AnalyticService::apiRequest($endpoint, 'POST', $requestData);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// Handle AJAX request for AI chat (to avoid CORS)
if (isset($_POST['action']) && $_POST['action'] === 'ask_ai' && isset($_POST['question'])) {
    header('Content-Type: application/json');

    $question = $_POST['question'];
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-1 month'));
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

    // Call API via cURL (server-side, no CORS issues)
    $endpoint = 'reports/ask-ai';
    $requestData = [
        'question' => $question,
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    $response = Service_AnalyticService::apiRequest($endpoint, 'POST', $requestData);

    echo json_encode($response);
    exit;
}

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 100;

// Determine if we're showing landing page or individual report
$showLandingPage = empty($reportType);

// Fetch predefined reports list (for landing page)
$availableReports = [];
$reportsError = null;
if ($showLandingPage) {
    $reportsList = Service_AnalyticService::apiRequest('reports/predefined');
    $availableReports = isset($reportsList['data']) ? $reportsList['data'] : [];
    if (isset($reportsList['success']) && !$reportsList['success']) {
        $reportsError = isset($reportsList['error']) ? $reportsList['error'] : 'Failed to load reports';
    }
}

// Fetch report data (for individual report view)
$reportData = null;
$reportTitle = 'Select a report';
$error = null;

if (!$showLandingPage) {
    // Fetch report data
    if ($isAiGenerated && isset($_GET['question'])) {
        // Handle AI-generated report
        $endpoint = 'reports/ask-ai';
        $requestData = [
            'question' => $_GET['question'],
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $response = Service_AnalyticService::apiRequest($endpoint, 'POST', $requestData);
        $reportTitle = $_GET['question'];
    } else {
        // Handle predefined report
        $endpoint = 'reports/predefined/' . urlencode($reportType) . '?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '&page=' . $page . '&per_page=' . $perPage;
        $response = Service_AnalyticService::apiRequest($endpoint);
    }

    if (isset($response['success']) && $response['success']) {
        $reportData = $response;
        if (!$isAiGenerated && isset($response['meta']['title'])) {
            $reportTitle = $response['meta']['title'];
        }
    } else {
        $error = isset($response['error']) ? $response['error'] : (isset($response['message']) ? $response['message'] : 'Failed to load report');
    }
}

// Fetch all dashboards for navigation
$allDashboards = [];
$dashboardsResponse = Service_AnalyticService::apiRequest('dashboards');
if (isset($dashboardsResponse['success']) && $dashboardsResponse['success']) {
    $allDashboards = isset($dashboardsResponse['data']) ? $dashboardsResponse['data'] : [];
}
?>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<link rel="stylesheet" href="/reports-view.css" />
<!-- Dashboard Tabs Section -->
<div class="dashboard-tabs-container">
    <div class="container-full">
        <!-- Top Bar -->
        <div class="dashboard-tabs-top-bar">
            <div class="row">
                <div class="col-xs-12 col-sm-6">
                    <h1>Reports</h1>
                </div>
            </div>
        </div>

        <!-- Horizontal Tabs Menu -->
        <div class="dashboard-tabs-nav">
            <ul class="nav nav-tabs" style="border-bottom: none;">
                <!-- Reports Tab -->
                <li>
                    <a href="/business/analytics/reports" class="dashboard-tab active"
                        style="padding: 12px 16px; display: inline-block; border-bottom: 2px solid #33a1df; color: #33a1df; text-decoration: none;">
                        Reports
                    </a>
                </li>
                <?php foreach ($allDashboards ?? [] as $dash): ?>
                    <?php
                    $dashType = isset($dash['type']) ? $dash['type'] : '';
                    $dashTitle = isset($dash['title']) ? $dash['title'] : '';

                    // Handle custom routes if provided
                    if (isset($dash['route'])) {
                        $dashHref = $dash['route'];
                    } else {
                        $dashHref = "/business/analytics/dashboard?type=" . urlencode($dashType) . "&period=all_time";
                    }
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($dashHref); ?>" class="dashboard-tab"
                            style="padding: 12px 16px; display: inline-block; border-bottom: 2px solid transparent; color: #666; text-decoration: none;">
                            <?php echo htmlspecialchars($dashTitle); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php if ($showLandingPage): ?>
    <!-- Reports Landing Page with Sidebar -->
    <div class="reports-layout">
        <!-- Sidebar -->
        <aside class="reports-sidebar">
            <div class="report-search">
                <input type="text" id="report-search" placeholder="Find a report…" />
            </div>
            <nav id="report-nav" class="report-nav">
                <!-- Payment Reports -->
                <div>
                    <div class="section-header">Payment Reports</div>
                    <ul>
                        <li><button type="button" data-report="daily-sales" class="rep-btn">Daily Sales</button></li>
                        <li><button type="button" data-report="top-items" class="rep-btn">Top Items</button></li>
                        <li><button type="button" data-report="revenue-by-franchise" class="rep-btn">Revenue by
                                Franchise</button></li>
                        <li><button type="button" data-report="payments-by-method" class="rep-btn">Payments by
                                Method</button></li>
                        <li><button type="button" data-report="refunds" class="rep-btn">Refunds</button></li>
                    </ul>
                </div>
                <!-- Custom Reports -->
                <div>
                    <div class="section-header">Custom Reports</div>
                    <ul>
                        <li><button data-report="top_spenders" class="rep-btn">Top spending customers</button></li>
                        <li><button data-report="members" class="rep-btn">Top spending Members</button></li>
                        <li><button data-report="non_members" class="rep-btn">Top spending Non‑Members</button></li>
                        <li><button data-report="product_performance" class="rep-btn">Product Performance</button></li>
                    </ul>
                </div>
                <!-- Products -->
                <div>
                    <div class="section-header">Products</div>
                    <ul>
                        <li><button data-report="prd_top_sold" class="rep-btn">Top products sold (excluding Categories Range
                                Services, Memberships)</button></li>
                        <li><button data-report="prd_turnover" class="rep-btn">High turnover products</button></li>
                        <li><button data-report="prd_least" class="rep-btn">Least purchased products</button></li>
                        <li><button data-report="prd_slow_movers" class="rep-btn">Slow movers – longest on shelf</button>
                        </li>
                        <li><button data-report="cln_sale_report_dly" class="rep-btn">Clean Sales Report - Daily</button>
                        </li>
                        <li><button data-report="cln_sale_report_wkly" class="rep-btn">Clean Sales Report - Weekly</button>
                        </li>
                        <li><button data-report="cln_sale_report_mnthly" class="rep-btn">Clean Sales Report -
                                Monthly</button></li>
                        <li><button data-report="sale_by_category" class="rep-btn">Sales By Product Category</button></li>
                        <li><button data-report="sale_by_subCategory" class="rep-btn">Sales By Product Sub
                                categories</button></li>
                        <li><button data-report="Trans_count_products" class="rep-btn">Transaction Count for
                                products</button></li>
                    </ul>
                </div>
                <!-- Memberships -->
                <div>
                    <div class="section-header">Memberships</div>
                    <ul>
                        <li><button data-report="cln_sale_mem_report_dly" class="rep-btn">Clean Sales Report -
                                Daily</button></li>
                        <li><button data-report="cln_sale_mem_report_wkly" class="rep-btn">Clean Sales Report -
                                Weekly</button></li>
                        <li><button data-report="cln_sale_mem_report_mnthly" class="rep-btn">Clean Sales Report -
                                Monthly</button></li>
                        <li><button data-report="mem_sale_by_category" class="rep-btn">Sales By Membership Category</button>
                        </li>
                        <li><button data-report="mem_Trans_count" class="rep-btn">Transaction Count of Memberships</button>
                        </li>
                    </ul>
                </div>
                <!-- Services -->
                <div>
                    <div class="section-header">Services</div>
                    <ul>
                        <li><button data-report="svc_top_new" class="rep-btn">Top services for NEW customers</button></li>
                        <li><button data-report="svc_first_service_customers" class="rep-btn">Customers whose first item is
                                a service</button></li>
                        <li><button data-report="range_busiest_month" class="rep-btn">Gun Range – Busiest month</button>
                        </li>
                        <li><button data-report="range_busiest_dow" class="rep-btn">Gun Range – Busiest day of week</button>
                        </li>
                    </ul>
                </div>
                <!-- Classes -->
                <div>
                    <div class="section-header">Classes</div>
                    <ul>
                        <li><button data-report="cls_popular" class="rep-btn">Most popular classes</button></li>
                        <li><button data-report="cls_new_customers" class="rep-btn">Classes bringing NEW customers</button>
                        </li>
                        <li><button data-report="cls_top_spenders" class="rep-btn">Top 100 class spenders</button></li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="reports-main">
            <div
                style="background: var(--brand-primary); color: white; padding: 12px 24px; border-radius: 8px 8px 0 0; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="database" style="width: 20px; height: 20px;"></i>
                    <span id="active-report-title" style="font-weight: 600;">Select a report</span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                        <span>Date Range:</span>
                    </label>
                    <input id="dateRangePicker" type="text"
                        style="padding: 6px 12px; border-radius: 4px; border: none; font-size: 13px; min-width: 220px; cursor: pointer;"
                        placeholder="Select date range" readonly />
                    <button id="applyDateFilter"
                        style="padding: 6px 16px; background: white; color: var(--brand-primary); border: none; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                        Apply
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="table">
                        <i data-lucide="table" style="width: 16px; height: 16px; margin-right: 6px;"></i>
                        Table View
                    </button>
                    <button class="tab-btn" data-tab="chart">
                        <i data-lucide="pie-chart" style="width: 16px; height: 16px; margin-right: 6px;"></i>
                        Chart View
                    </button>
                    <button class="tab-btn" data-tab="export">
                        <i data-lucide="download" style="width: 16px; height: 16px; margin-right: 6px;"></i>
                        Export Data
                    </button>
                </div>

                <!-- Tab Contents -->
                <div id="tab-table" class="tab-content active">
                    <div class="table-container">
                        <table id="dataTable" style="width: 100%; font-size: 14px;">
                            <thead id="tableHead"></thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="100" style="text-align: center; color: #6c757d; padding: 32px;">
                                        Select a report from the sidebar to view data
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="paginationControls"></div>
                    </div>
                </div>

                <div id="tab-chart" class="tab-content">
                    <div class="chart-section">
                        <div class="chart-container">
                            <canvas id="dataChart"></canvas>
                        </div>
                    </div>
                </div>

                <div id="tab-export" class="tab-content">
                    <div style="text-align: center; padding: 48px;">
                        <i data-lucide="download"
                            style="width: 64px; height: 64px; color: var(--brand-primary); margin-bottom: 16px;"></i>
                        <button id="exportCSV"
                            style="padding: 12px 24px; background: var(--brand-primary); color: white; border: none; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer;">
                            Download CSV
                        </button>
                        <p style="margin-top: 16px; font-size: 12px; color: #6c757d;">The export will include the currently
                            displayed table data.</p>
                    </div>
                </div>
            </div>

            <!-- AI Chat Section -->
            <div id="ask-ai-section" class="ai-chat-section">
                <div class="ai-chat-header">
                    <i data-lucide="bot" style="width: 24px; height: 24px; color: var(--brand-primary);"></i>
                    <h2 style="font-size: 20px; font-weight: 600; color: #212529; margin: 0;">Ask for a report in plain
                        english</h2>
                </div>
                <form id="aiChatForm" class="ai-chat-form" action="#" method="POST" onsubmit="return false;">
                    <input type="text" id="aiChatInput" name="question"
                        placeholder="e.g., top 20 non-members by spend last quarter" class="ai-chat-input" required>
                    <button type="button" id="aiChatButton" class="ai-chat-button">GET REPORT</button>
                    <input type="hidden" id="aiChatStartDate" name="start_date"
                        value="<?php echo htmlspecialchars($startDate); ?>">
                    <input type="hidden" id="aiChatEndDate" name="end_date"
                        value="<?php echo htmlspecialchars($endDate); ?>">
                </form>
                <div class="ai-suggestions">
                    <div style="font-size: 14px; color: #6c757d; margin-bottom: 8px;">Frequently asked reports:</div>
                    <div class="ai-suggestion-chips">
                        <button type="button" class="ai-suggestion-chip"
                            data-question="Top 10 spending customers this month">Top 10 spending customers this
                            month</button>
                        <button type="button" class="ai-suggestion-chip"
                            data-question="Show me monthly sales trends for 2025">Show me monthly sales trends for
                            2025</button>
                        <button type="button" class="ai-suggestion-chip" data-question="High turnover products">High
                            turnover products</button>
                        <button type="button" class="ai-suggestion-chip"
                            data-question="Give me basic report for invoice list for this month">Give me basic report for
                            invoice list for this month</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script>
        lucide.createIcons();

        // Global variables
        let dateRangePicker;
        let currentData = [];
        let currentChart = null;
        let currentReportKey = null;
        let allTableData = [];
        let currentPage = 1;
        let rowsPerPage = 10;

        // Initialize Flatpickr date range picker
        const defaultStartDate = '<?php echo htmlspecialchars($startDate); ?>';
        const defaultEndDate = '<?php echo htmlspecialchars($endDate); ?>';

        dateRangePicker = flatpickr("#dateRangePicker", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [defaultStartDate, defaultEndDate],
            maxDate: "today",
            onChange: function (selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    const chatStartDate = document.getElementById('aiChatStartDate');
                    const chatEndDate = document.getElementById('aiChatEndDate');
                    if (chatStartDate) chatStartDate.value = selectedDates[0].toISOString().split('T')[0];
                    if (chatEndDate) chatEndDate.value = selectedDates[1].toISOString().split('T')[0];
                }
            }
        });

        // Report Titles
        const TITLES = {
            top_spenders: 'Top spending customers',
            all_customers: 'All customers (within date range)',
            members: 'Members only',
            non_members: 'Non‑Members only',
            non_member_savings: 'Top Non‑Members – potential membership savings',
            repeat_behavior: 'Repeat purchases by item/category',
            svc_top_new: 'Top services for NEW customers',
            svc_first_service_customers: 'Customers whose first purchase was a service',
            range_busiest_month: 'Gun Range – busiest month',
            range_busiest_dow: 'Gun Range – busiest day of week (avg last 12 months)',
            cls_popular: 'Most popular classes',
            cls_new_customers: 'Classes bringing NEW customers',
            cls_top_spenders: 'Top 100 class spenders',
            prd_top_sold: 'Top products sold (with exclusions)',
            prd_turnover: 'High turnover products',
            prd_least: 'Least purchased products',
            prd_slow_movers: 'Slow movers – longest on shelf',
            cln_sale_report_dly: 'Clean Sales Report - Daily',
            cln_sale_report_wkly: 'Clean Sales Report - weekly',
            cln_sale_report_mnthly: 'Clean Sales Report - Monthly',
            sale_by_category: 'Sales By Product Category',
            sale_by_subCategory: 'Sales By Product Sub categories',
            Trans_count_products: 'Transaction Count for products',
            mem_sale_by_category: 'Sales By Membership Category',
            mem_Trans_count: 'Transaction Count of Memberships',
            cln_sale_mem_report_dly: 'Clean Sales Report - Daily',
            cln_sale_mem_report_wkly: 'Clean Sales Report - Weekly',
            cln_sale_mem_report_mnthly: 'Clean Sales Report - Monthly',
            product_performance: 'Product Performance',
            'daily-sales': 'Daily Sales',
            'top-items': 'Top Items',
            'revenue-by-franchise': 'Revenue by Franchise',
            'payments-by-method': 'Payments by Method',
            'refunds': 'Refunds'
        };

        // SQL Templates
        const SQLS = {
            top_spenders: ({ audience, start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `WHERE id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `WHERE id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Top ${N} ${audience?.replace('_', '-')} customers by spend */ 
    SELECT id.customer_name, SUM(iid.total_price) AS total_spent
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
    WHERE (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental',
                            'giftcard', 'appointment', 'subscription','guestpass','sponsorship','warranty', 'adavanceBookingFee', 'membershiprental') 
        OR lowerUTF8(iid.item_type) LIKE 'misc%' 
        OR lowerUTF8(iid.item_type) LIKE 'Misc%')
    ${dateFilter}
    GROUP BY id.customer_id, id.customer_name
    ORDER BY total_spent DESC
    LIMIT ${N};`;
            },
            members: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT distinct customer_name FROM invoice_details WHERE is_member=1 ${dateFilter}`;
            },
            non_members: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT distinct customer_name FROM invoice_details WHERE is_member=0 ${dateFilter}`;
            },
            product_performance: ({ start, end, N }) => {
                let dateFilter = '';
                let dateFilter1 = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                    dateFilter1 = `WHERE invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                    dateFilter1 = `WHERE invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Top ${N} products spend by their top spending customers*/
    WITH top_customers AS (
        SELECT customer_id, customer_name, SUM(total_amount) AS total_spent
        FROM invoice_details
        ${dateFilter1}
        GROUP BY customer_id, customer_name
        ORDER BY total_spent DESC
        LIMIT ${N}
    ),
    ranked_products AS (
        SELECT
            tc.customer_id,
            tc.customer_name,
            iid.item_name,
            SUM(iid.total_price) AS total_spent,
            ROW_NUMBER() OVER (
                PARTITION BY tc.customer_id
                ORDER BY SUM(iid.total_price) DESC
            ) AS rn
        FROM top_customers AS tc
        INNER JOIN invoice_details AS id
            ON tc.customer_id = id.customer_id
        INNER JOIN invoice_items_detail AS iid
            ON iid.invoice_id = id.id
        WHERE iid.item_type = 'product'
        ${dateFilter}
        GROUP BY tc.customer_id, tc.customer_name, iid.item_name
    ) SELECT tc.customer_name AS customer, item_name AS ITEM, sum(total_spent) AS total_spent 
    FROM ranked_products  
    WHERE rn <= ${N} 
    GROUP BY customer, ITEM, rn 
    ORDER BY total_spent DESC, rn ASC;`;
            },
            svc_top_new: ({ start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = ` AND ft.first_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                }
                return `/* Top services that are first-ever purchase for customers */
    WITH first_dt AS (
        SELECT 
            customer_id, 
            MIN(invoice_date) AS first_date 
        FROM invoice_details 
        GROUP BY customer_id
    )
    SELECT 
        iid.item_name AS service_name, 
        COUNT(DISTINCT ft.customer_id) AS new_customers
    FROM first_dt ft
    INNER JOIN invoice_details id 
        ON id.customer_id = ft.customer_id 
        AND id.invoice_date = ft.first_date
    INNER JOIN invoice_items_detail iid 
        ON iid.invoice_id = id.id
    WHERE 
        iid.item_type = 'service'
    ${dateFilter}
    GROUP BY service_name
    ORDER BY new_customers DESC
    LIMIT ${N};`;
            },
            svc_first_service_customers: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Customers whose first purchase was a service */
    WITH first_dt AS (
    SELECT customer_id, min(invoice_date) AS first_date FROM invoice_details GROUP BY customer_id
    )
    SELECT distinct o.customer_name as CustomerName, oi.item_name as Service, formatDateTime(ft.first_date, '%d %b %Y') as First_Purchase_Date
    FROM first_dt ft
    INNER JOIN invoice_details o ON o.customer_id=ft.customer_id 
    AND o.invoice_date=ft.first_date
    INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
    WHERE oi.item_type='service' ${dateFilter}`;
            },
            range_busiest_month: ({ start, end, audience }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Range busiest month by distinct customers */
    SELECT 
        formatDateTime(o.invoice_date, '%b %y') AS yyyymm, 
        countDistinct(o.customer_id) AS customers
    FROM invoice_details o 
    INNER JOIN invoice_items_detail oi 
        ON oi.invoice_id = o.id
    INNER JOIN Range_appointments ra 
        ON CAST(ra.invoiceId AS UInt64) = o.id
    WHERE oi.item_type = 'service'
    ${dateFilter}
    ${audience === 'members' ? 'AND o.is_member = 1' : audience === 'non_members' ? 'AND o.is_member = 0' : ''}
    GROUP BY yyyymm
    ORDER BY customers DESC
    LIMIT 1;`;
            },
            range_busiest_dow: ({ audience, start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    const startDate = new Date(start);
                    const endDate = new Date(end);
                    startDate.setMonth(startDate.getMonth() - 12);
                    endDate.setMonth(endDate.getMonth() - 12);
                    const start12MonthsAgo = startDate.toISOString().split('T')[0];
                    const end12MonthsAgo = endDate.toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date >= toDate('${start12MonthsAgo}') AND o.invoice_date <= toDate('${end12MonthsAgo}')`;
                } else {
                    const defaultEnd = new Date();
                    const defaultStart = new Date();
                    defaultStart.setMonth(defaultStart.getMonth() - 12);
                    const start12MonthsAgo = defaultStart.toISOString().split('T')[0];
                    const end12MonthsAgo = defaultEnd.toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date >= toDate('${start12MonthsAgo}') AND o.invoice_date <= toDate('${end12MonthsAgo}')`;
                }
                return `/* Busiest day of week (avg last 12 months) */
    SELECT toDayOfWeek(o.invoice_date) AS dow,
        arrayElement(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], dow) AS day_name,
        round(countDistinct(o.customer_id) / countDistinct(toDate(o.invoice_date))) AS avg_customers
    FROM invoice_details o 
    INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
    WHERE oi.item_type='service' AND oi.category='Gun Ranges & Instruction'
    ${dateFilter}
    ${audience === 'members' ? 'AND o.is_member=1' : audience === 'non_members' ? 'AND o.is_member=0' : ''}
    GROUP BY dow
    ORDER BY avg_customers DESC`;
            },
            cls_popular: ({ start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Most popular classes */
    SELECT oi.item_name AS class_name, sum(oi.quantity) AS seats_sold, countDistinct(o.id) AS invoice_Count
    FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
    WHERE oi.item_type='class' ${dateFilter}
    GROUP BY class_name
    ORDER BY seats_sold DESC
    LIMIT ${N};`;
            },
            cls_new_customers: ({ start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                }
                return `/* Classes that bring NEW customers */
    WITH first_dt AS (
    SELECT customer_id, min(invoice_date) AS first_date FROM invoice_details GROUP BY customer_id
    )
    SELECT oi.item_name AS class_name, count() AS new_customers
    FROM first_dt ft
    INNER JOIN invoice_details o ON o.customer_id=ft.customer_id AND o.invoice_date=ft.first_date
    INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
    WHERE oi.item_type='class' ${dateFilter}
    GROUP BY class_name
    ORDER BY new_customers DESC
    LIMIT ${N};`;
            },
            cls_top_spenders: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = ` AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                }
                return `/* Top 100 customers by class spend */
    SELECT o.customer_name, sum(oi.total_price) AS class_spend, count() AS class_lines
    FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
    WHERE oi.item_type='class' ${dateFilter}
    GROUP BY o.customer_name
    ORDER BY class_spend DESC
    LIMIT 100;`;
            },
            prd_top_sold: ({ start, end, N, audience }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Top products sold with sample exclusions */
    SELECT oi.item_name, oi.category, SUM(oi.quantity) AS units, SUM(oi.total_price) AS revenue
    FROM invoice_items_detail oi 
    INNER JOIN invoice_details o ON o.id = oi.invoice_id
    WHERE oi.item_type = 'product' 
    AND oi.invoice_id NOT IN (SELECT DISTINCT invoiceId FROM Range_appointments)
    ${dateFilter}
    ${audience === 'members' ? 'AND o.is_member=1' : audience === 'non_members' ? 'AND o.is_member=0' : ''}
    AND oi.SKU NOT IN ('')
    AND oi.category NOT IN ('Range Services', 'Memberships')
    GROUP BY oi.item_name, oi.category
    ORDER BY units DESC
    LIMIT ${N};`;
            },
            prd_turnover: ({ start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                }
                return `/* High turnover products (line count) */
    SELECT oi.item_name, oi.category, count() AS lines, sum(oi.quantity) AS units
    FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
    WHERE oi.item_type='product' ${dateFilter}
    GROUP BY oi.item_name, oi.category
    ORDER BY lines DESC
    LIMIT ${N};`;
            },
            prd_least: ({ start, end, N }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND o.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `/* Least purchased products by units */
    SELECT oi.item_name, sum(oi.quantity) AS units
    FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
    WHERE oi.item_type='product' 
    ${dateFilter}
    GROUP BY oi.item_name
    ORDER BY units ASC
    LIMIT ${N};`;
            },
            prd_slow_movers: ({ N }) => `/* Slow movers (inventory-based) */
    SELECT 
        pi.name AS product_name,
        pi.qoh AS quantity_on_hand,
        ifNull(dateDiff('day', max(o.invoice_date), today()), -1) AS days_since_last_sale
    FROM product_inventory AS pi
    LEFT JOIN invoice_items_detail AS iid
        ON iid.SKU = pi.sku
    LEFT JOIN invoice_details AS o
        ON o.id = iid.invoice_id
    GROUP BY pi.sku, pi.qoh, pi.name
    ORDER BY days_since_last_sale DESC
    LIMIT ${N};`,
            cln_sale_report_dly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT 
        formatDateTime(id.invoice_date, '%d %b %Y') AS date,
        iid.item_name,
        SUM(iid.total_price) as total_sales,
        COUNT(DISTINCT id.id) as invoice_count
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
        ${dateFilter}
    GROUP BY id.invoice_date, iid.item_name
    ORDER BY id.invoice_date DESC, total_sales DESC`;
            },
            cln_sale_report_wkly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT 
        formatDateTime(toStartOfWeek(id.invoice_date), '%d %b %Y') AS week_start,
        formatDateTime(toStartOfWeek(id.invoice_date) + INTERVAL 6 DAY, '%d %b %Y') AS week_end,
        iid.item_name,
        SUM(iid.total_price) AS total_sales,
        COUNT(DISTINCT id.id) AS invoice_count
    FROM invoice_details AS id
    INNER JOIN invoice_items_detail AS iid 
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
        ${dateFilter}
    GROUP BY 
        toStartOfWeek(id.invoice_date),
        iid.item_name
    ORDER BY 
        toStartOfWeek(id.invoice_date) DESC, 
        total_sales DESC`;
            },
            cln_sale_report_mnthly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = ` AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                }
                return `SELECT
        formatDateTime(toStartOfMonth(id.invoice_date), '%b %Y') AS month,
        iid.item_name,
        SUM(iid.total_price) as total_sales,
        COUNT(DISTINCT id.id) as invoice_count
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
    ${dateFilter}
    GROUP BY toStartOfMonth(id.invoice_date), iid.item_name
    ORDER BY toStartOfMonth(id.invoice_date) DESC, total_sales DESC`;
            },
            sale_by_category: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT
        iid.category,
        iid.item_name,
        COUNT(DISTINCT id.id) as invoice_count,
        sum(iid.quantity) as qty_sold,
        SUM(iid.total_price) as total_sales
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
        ${dateFilter}
    GROUP BY iid.category, iid.item_name
    ORDER BY iid.category, total_sales DESC`;
            },
            sale_by_subCategory: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT
    iid.item_name,
        iid.subcategory,
        COUNT(DISTINCT id.id) as invoice_count,
        COUNT(iid.quantity ) as item_count,
        SUM(iid.total_price) as total_sales
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
        ${dateFilter}
    GROUP BY  iid.item_name, iid.subcategory
    ORDER BY iid.subcategory, total_sales DESC`;
            },
            Trans_count_products: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT
        iid.item_name,
        COUNT(DISTINCT id.id) as transaction_count,
        sum(iid.quantity) as Quantity_sold,
        SUM(iid.total_price) as total_sales
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'product'
    ${dateFilter}
    GROUP BY iid.item_name
    ORDER BY transaction_count DESC`;
            },
            cln_sale_mem_report_dly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT 
        formatDateTime(id.invoice_date, '%d %b %Y') AS date,
        iid.item_name,
        SUM(iid.total_price) as total_sales,
        COUNT(DISTINCT id.id) as invoice_count
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
    WHERE iid.item_type = 'membership'
        ${dateFilter}
    GROUP BY id.invoice_date, iid.item_name
    ORDER BY id.invoice_date DESC, total_sales DESC`;
            },
            cln_sale_mem_report_wkly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT 
        formatDateTime(toStartOfWeek(id.invoice_date), '%d %b %Y') AS week_start,
        formatDateTime(toStartOfWeek(id.invoice_date) + INTERVAL 6 DAY, '%d %b %Y') AS week_end,
        iid.item_name,
        SUM(iid.total_price) as total_sales,
        COUNT(DISTINCT id.id) as invoice_count
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid 
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'membership'
        ${dateFilter}
    GROUP BY toStartOfWeek(id.invoice_date), iid.item_name
    ORDER BY toStartOfWeek(id.invoice_date) ASC, total_sales DESC`;
            },
            cln_sale_mem_report_mnthly: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = ` AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = ` AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT
        formatDateTime(toStartOfMonth(id.invoice_date), '%b %Y') AS month,
        iid.item_name,
        SUM(iid.total_price) as total_sales,
        COUNT(DISTINCT id.id) as invoice_count
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'membership'
    ${dateFilter}
    GROUP BY toStartOfMonth(id.invoice_date), iid.item_name
    ORDER BY toStartOfMonth(id.invoice_date) ASC, total_sales DESC`;
            },
            mem_sale_by_category: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT iid.category, 
    COUNT(DISTINCT id.id) as invoice_count, sum(iid.quantity) as qty_sold, 
    SUM(iid.total_price) as total_sales FROM invoice_details id 
    INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id 
    WHERE iid.item_type = 'membership' 
        ${dateFilter}
    GROUP BY iid.category
    ORDER BY iid.category, total_sales DESC`;
            },
            mem_Trans_count: ({ start, end }) => {
                let dateFilter = '';
                if (start && end) {
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
                } else {
                    const defaultEnd = new Date().toISOString().split('T')[0];
                    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    dateFilter = `AND id.invoice_date BETWEEN toDate('${defaultStart}') AND toDate('${defaultEnd}')`;
                }
                return `SELECT
    TRIM(iid.item_name) as item_name,
        COUNT(DISTINCT id.id) as transaction_count,
        sum(iid.quantity) as times_sold,
        SUM(iid.total_price) as total_sales
    FROM invoice_details id
    INNER JOIN invoice_items_detail iid
        ON id.id = iid.invoice_id
    WHERE iid.item_type = 'membership'
    ${dateFilter}
    GROUP BY TRIM(iid.item_name)
    ORDER BY transaction_count DESC`;
            }
        };

        // Format date helper
        function formatDate(value) {
            if (!value) return value;
            const dateStr = String(value).trim();
            let date;
            try {
                if (dateStr.match(/^\d{4}-\d{2}-\d{2}/)) {
                    date = new Date(dateStr);
                } else {
                    date = new Date(dateStr);
                }
                if (isNaN(date.getTime())) return value;
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const day = date.getDate();
                const month = months[date.getMonth()];
                const year = date.getFullYear();
                return `${day} ${month} ${year}`;
            } catch (e) {
                return value;
            }
        }

        // Remove item prefix
        function removeItemPrefix(label) {
            if (!label) return label;
            const str = String(label).trim();
            const prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
            for (const prefix of prefixes) {
                if (str.toLowerCase().startsWith(prefix.toLowerCase())) {
                    return str.substring(prefix.length).trim();
                }
            }
            return str;
        }

        // Check if column is date
        function isDateColumn(columnName) {
            const dateKeywords = ['date', 'Date', 'DATE', 'time', 'Time', 'TIME', 'created', 'updated', 'invoice_date', 'first_date', 'week_start', 'week_end'];
            return dateKeywords.some(keyword => columnName.toLowerCase().includes(keyword.toLowerCase()));
        }

        // Format number
        function formatNumber(value, decimals = 0) {
            if (typeof value !== 'number') return value;
            return value.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        // Render table
        function renderTable(data) {
            if (!data || data.length === 0) {
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" class="text-center" style="color: #6c757d; padding: 32px;">No data available</td></tr>';
                document.getElementById('tableHead').innerHTML = '';
                return false;
            }

            allTableData = data;
            const headers = Object.keys(data[0]);
            const thead = document.getElementById('tableHead');
            const tbody = document.getElementById('tableBody');

            // Render header
            thead.innerHTML = '<tr>' + headers.map(h =>
                '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 500; color: #6c757d; text-transform: uppercase; border: 1px solid #dee2e6; background: #f8f9fa;">' +
                h.replace(/_/g, ' ') + '</th>'
            ).join('') + '</tr>';

            // Render body with pagination
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, data.length);
            const paginatedData = data.slice(startIndex, endIndex);

            const rowsHTML = paginatedData.map((row, index) => {
                const rowClass = (startIndex + index) % 2 === 0 ? 'background-color: white;' : 'background-color: #f8f9fa;';
                const cells = headers.map(key => {
                    let val = row[key];
                    if (isDateColumn(key) || (val && typeof val === 'string' && (val.match(/^\d{4}-\d{2}-\d{2}/)))) {
                        val = formatDate(val);
                    } else if (typeof val === 'string') {
                        val = removeItemPrefix(val);
                    } else if (typeof val === 'number') {
                        val = formatNumber(val, val % 1 ? 2 : 0);
                    }
                    return '<td style="padding: 12px 16px; font-size: 14px; border: 1px solid #dee2e6; color: #212529;">' + (val !== null && val !== undefined ? val : '') + '</td>';
                }).join('');
                return '<tr style="' + rowClass + '">' + cells + '</tr>';
            }).join('');

            tbody.innerHTML = rowsHTML;
            renderPagination(data.length);
        }

        // Render pagination
        function renderPagination(totalRows) {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const paginationContainer = document.getElementById('paginationControls');
            if (!paginationContainer || totalPages <= 1) {
                if (paginationContainer) paginationContainer.innerHTML = '';
                return false;
            }

            const startRow = (currentPage - 1) * rowsPerPage + 1;
            const endRow = Math.min(currentPage * rowsPerPage, totalRows);

            paginationContainer.innerHTML = `
                        <div style="padding: 16px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #dee2e6;">
                            <button onclick="changePage(${currentPage - 1})" style="padding: 6px 12px; background: #e9ecef; border: none; border-radius: 4px; cursor: pointer;" ${currentPage === 1 ? 'disabled' : ''}>← Previous</button>
                            <span style="font-size: 14px;">Page ${currentPage} of ${totalPages} (Showing ${startRow}-${endRow} of ${totalRows})</span>
                            <button onclick="changePage(${currentPage + 1})" style="padding: 6px 12px; background: #e9ecef; border: none; border-radius: 4px; cursor: pointer;" ${currentPage === totalPages ? 'disabled' : ''}>Next →</button>
                        </div>
                    `;
        }

        // Change page function
        window.changePage = function (page) {
            const totalPages = Math.ceil(allTableData.length / rowsPerPage);
            if (page < 1 || page > totalPages) return false;
            currentPage = page;
            renderTable(allTableData);
        };

        // Render chart
        function renderChart(data, chartType = 'bar') {
            if (!data || data.length === 0) return false;

            const canvas = document.getElementById('dataChart');
            if (!canvas) return false;

            const ctx = canvas.getContext('2d');
            if (currentChart) {
                currentChart.destroy();
            }

            const keys = Object.keys(data[0]);
            const labelKey = keys.find(key => typeof data[0][key] === 'string');
            const valueKey = keys.find(key => typeof data[0][key] === 'number');

            if (!labelKey || !valueKey) return false;

            const labels = data.map(row => removeItemPrefix(row[labelKey]));
            const values = data.map(row => parseFloat(row[valueKey]) || 0);

            currentChart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: valueKey.replace(/_/g, ' '),
                        data: values,
                        backgroundColor: chartType === 'line' ? 'rgba(51, 161, 223, 0.1)' : '#33a1df',
                        borderColor: '#33a1df',
                        borderWidth: 2,
                        fill: chartType === 'line',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie',
                            position: 'right'
                        }
                    },
                    scales: chartType !== 'pie' ? {
                        y: { beginAtZero: true }
                    } : undefined
                }
            });
        }

        // Load report - submit SQL to API /reports/run endpoint
        function loadReport(reportKey) {
            currentReportKey = reportKey;
            sessionStorage.setItem('currentReportKey', reportKey);

            const reportTitle = TITLES[reportKey] || 'Report';
            document.getElementById('active-report-title').textContent = reportTitle;
            localStorage.setItem('activeReportTitle', reportTitle);

            // Get dates from date range picker
            let start, end;
            if (dateRangePicker && dateRangePicker.selectedDates && dateRangePicker.selectedDates.length === 2) {
                const selectedDates = dateRangePicker.selectedDates;
                start = selectedDates[0].toISOString().split('T')[0];
                end = selectedDates[1].toISOString().split('T')[0];
            } else {
                const defaultEnd = new Date().toISOString().split('T')[0];
                const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                start = defaultStart;
                end = defaultEnd;
            }

            // Submit report key to PHP handler (which uses cURL to call API - avoids CORS)
            // SQL is generated server-side at API level
            const formData = new FormData();
            formData.append('action', 'run_report');
            formData.append('report_key', reportKey);
            formData.append('start_date', start);
            formData.append('end_date', end);
            formData.append('page', '1');
            formData.append('per_page', '100');
            formData.append('audience', 'all');
            formData.append('N', '100');

            // Show loading state
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" style="text-align: center; color: #6c757d; padding: 32px;">Loading report data...</td></tr>';
            document.getElementById('tableHead').innerHTML = '';

            // Call PHP endpoint (same origin, no CORS issues)
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.data) {
                        currentData = data.data;
                        allTableData = data.data;
                        currentPage = 1;
                        renderTable(data.data);
                        renderChart(data.data);

                        // Switch to table tab
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        document.querySelector('.tab-btn[data-tab="table"]').classList.add('active');
                        document.getElementById('tab-table').classList.add('active');
                    } else {
                        const errorMsg = data.error || data.message || 'Failed to load report';
                        document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" style="text-align: center; color: #dc3545; padding: 32px;">Error: ' + errorMsg + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    let errorMsg = error.message || 'Failed to connect to API';
                    if (errorMsg.includes('mixed') || errorMsg.includes('Mixed')) {
                        errorMsg = 'Mixed content error: Page is HTTPS but API is HTTP. Please configure API server to support HTTPS or access this page over HTTP.';
                    }
                    document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" style="text-align: center; color: #dc3545; padding: 32px;">Error: ' + errorMsg + '</td></tr>';
                });
        }

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');

                // Render chart when chart tab is clicked
                if (this.dataset.tab === 'chart' && currentData.length > 0) {
                    renderChart(currentData);
                }
            });
        });

        // Report buttons
        document.querySelectorAll('.rep-btn[data-report]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (this.dataset.report) {
                    loadReport(this.dataset.report);
                }
            });
        });

        // Search functionality
        document.getElementById('report-search').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const sectionHeaders = document.querySelectorAll('.section-header');
            const reportButtons = document.querySelectorAll('.rep-btn');

            if (searchTerm === '') {
                sectionHeaders.forEach(el => el.style.display = '');
            } else {
                sectionHeaders.forEach(el => el.style.display = 'none');
            }

            reportButtons.forEach(btn => {
                const text = btn.textContent.toLowerCase();
                const listItem = btn.closest('li');
                if (text.includes(searchTerm) || searchTerm === '') {
                    listItem.style.display = '';
                } else {
                    listItem.style.display = 'none';
                }
            });
        });

        // Export CSV
        document.getElementById('exportCSV').addEventListener('click', function () {
            if (!allTableData || allTableData.length === 0) {
                alert('No data to export');
                return false;
            }

            const headers = Object.keys(allTableData[0]);
            const csvRows = [];
            csvRows.push(headers.map(h => h.replace(/_/g, ' ')).join(','));

            allTableData.forEach(row => {
                const values = headers.map(header => {
                    let val = row[header];
                    if (val === null || val === undefined) val = '';
                    if (typeof val === 'string') {
                        val = val.replace(/"/g, '""');
                        if (val.includes(',') || val.includes('"')) {
                            val = '"' + val + '"';
                        }
                    }
                    return val;
                });
                csvRows.push(values.join(','));
            });

            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'report_' + new Date().getTime() + '.csv';
            link.click();
        });

        // AI Chat functionality
        const aiChatForm = document.getElementById('aiChatForm');
        const aiChatInput = document.getElementById('aiChatInput');
        const aiChatButton = document.getElementById('aiChatButton');
        const aiChatStartDate = document.getElementById('aiChatStartDate');
        const aiChatEndDate = document.getElementById('aiChatEndDate');

        if (!aiChatForm || !aiChatInput || !aiChatButton) {
            console.error('AI Chat form elements not found');
        }

        // Update date inputs when date range form changes
        const dateForm = document.querySelector('.date-form');
        if (dateForm) {
            dateForm.addEventListener('submit', function () {
                setTimeout(function () {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('start_date')) {
                        aiChatStartDate.value = urlParams.get('start_date');
                    }
                    if (urlParams.get('end_date')) {
                        aiChatEndDate.value = urlParams.get('end_date');
                    }
                }, 100);
            });
        }

        // Function to handle AI report request
        function handleAiReportRequest() {
            const question = aiChatInput.value.trim();
            if (!question) {
                alert('Please enter a question');
                return false;
            }

            // Disable form
            aiChatButton.disabled = true;
            aiChatButton.textContent = 'Loading...';

            // Call PHP handler (which uses cURL to call API - avoids CORS)
            const formData = new FormData();
            formData.append('action', 'ask_ai');
            formData.append('question', question);
            formData.append('start_date', aiChatStartDate.value);
            formData.append('end_date', aiChatEndDate.value);

            // Make request to PHP handler (same origin, no CORS issues)
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Re-enable form
                    aiChatButton.disabled = false;
                    aiChatButton.textContent = 'GET REPORT';

                    // Debug logging
                    console.log('AI Response:', data);

                    if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                        // Display results directly on the same page (like other reports)
                        currentData = data.data;
                        allTableData = data.data;
                        currentPage = 1;
                        
                        // Update title to show the question
                        const titleElement = document.getElementById('active-report-title');
                        if (titleElement) {
                            titleElement.textContent = question;
                        }
                        
                        // Render table and chart
                        renderTable(data.data);
                        renderChart(data.data);
                        
                        // Switch to table tab
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        const tableBtn = document.querySelector('.tab-btn[data-tab="table"]');
                        const tableContent = document.getElementById('tab-table');
                        if (tableBtn) tableBtn.classList.add('active');
                        if (tableContent) tableContent.classList.add('active');
                        if (tableContent) {
                            tableContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else if (data.success && data.data && Array.isArray(data.data) && data.data.length === 0) {
                        // No data returned
                        const titleElement = document.getElementById('active-report-title');
                        if (titleElement) {
                            titleElement.textContent = question;
                        }
                        renderTable([]);
                        alert('No data found for your question. Please try rephrasing your question.');
                    } else {
                        // Show error
                        const errorMsg = data.error || data.message || 'Failed to generate report';
                        console.error('AI Error:', errorMsg, data);
                        alert('Error: ' + errorMsg);
                    }
                })
                .catch(error => {
                    // Re-enable form
                    aiChatButton.disabled = false;
                    aiChatButton.textContent = 'GET REPORT';

                    console.error('Error:', error);
                    alert('Error: ' + (error.message || 'Failed to connect to server'));
                });
        }

        // Handle suggestion chips
        document.querySelectorAll('.ai-suggestion-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                aiChatInput.value = this.getAttribute('data-question');
                handleAiReportRequest();
            });
        });

        // Handle AI form submission
        if (aiChatForm) {
            aiChatForm.addEventListener('submit', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handleAiReportRequest();
                return false;
            });
        }

        // Handle button click (as backup, since button is now type="button")
        if (aiChatButton) {
            aiChatButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handleAiReportRequest();
                return false;
            });
        }
    </script>
<?php else: ?>
    <!-- Individual Report View -->
    <div style="min-height: calc(100vh - 80px);">
        <!-- Period Filters -->
        <div class="period-filters">
            <div class="container-full">
                <div class="period-filters-content">
                    <div>
                        <h1 style="font-size: 24px; font-weight: bold; color: #212529; margin: 0;">
                            <span id="active-report-title"><?php echo htmlspecialchars($reportTitle); ?></span>
                        </h1>
                        <?php if ($startDate && $endDate): ?>
                            <div style="font-size: 14px; color: #6c757d; margin-top: 4px;">
                                Date Range: <?php echo htmlspecialchars(Service_AnalyticService::formatDate($startDate)); ?> -
                                <?php echo htmlspecialchars(Service_AnalyticService::formatDate($endDate)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <form method="GET" class="date-form"
                            action="/business/analytics/reports/predefined/<?php echo htmlspecialchars($reportType); ?>">
                            <label style="font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                <span>Date Range:</span>
                            </label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                            <span>to</span>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                            <button type="submit">
                                <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
                                Apply
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="container-full" style="padding: 24px;">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start;">
                        <div style="flex-shrink: 0; margin-right: 12px;">
                            <i data-lucide="alert-circle" style="width: 20px; height: 20px; color: #dc3545;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 500; color: #721c24; margin-top: 0; margin-bottom: 8px;">
                                Error</h4>
                            <div style="font-size: 14px; color: #721c24;">
                                <p style="margin: 0;"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Table View -->
            <div class="table-container">
                <div class="widget-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;">Report Data</h3>
                        <?php if ($reportData && isset($reportData['data']) && !empty($reportData['data'])): ?>
                            <button class="download-button" onclick="exportTableToCSV()">
                                <i data-lucide="download" style="width: 16px; height: 16px;"></i>
                                Download CSV
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="widget-body">
                    <div style="overflow-x: auto;">
                        <table id="dataTable" class="table table-bordered">
                            <thead id="tableHead"></thead>
                            <tbody id="tableBody"></tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($reportData && isset($reportData['meta']['pagination'])):
                        $pagination = $reportData['meta']['pagination'];
                        ?>
                        <div class="pagination-container">
                            <div style="font-size: 14px; color: #212529;">
                                Showing <?php echo ($pagination['page'] - 1) * $pagination['per_page'] + 1; ?> to
                                <?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?>
                                of
                                <?php echo $pagination['total']; ?> results
                            </div>
                            <div class="pagination-buttons">
                                <?php if ($pagination['page'] > 1): ?>
                                    <a
                                        href="/business/analytics/reports/predefined/<?php echo htmlspecialchars($reportType); ?>?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>&page=<?php echo $pagination['page'] - 1; ?>">Previous</a>
                                <?php endif; ?>
                                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                                    <a
                                        href="/business/analytics/reports/predefined/<?php echo htmlspecialchars($reportType); ?>?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>&page=<?php echo $pagination['page'] + 1; ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart View -->
            <div class="chart-section">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Chart View</h3>
                <div class="chart-container">
                    <canvas id="chartCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Report data from PHP
        const reportData = <?php echo json_encode($reportData); ?>;
        let allTableData = reportData && reportData.data ? reportData.data : [];

        // Format date function
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
        }

        // Check if column is date
        function isDateColumn(columnName) {
            const dateColumns = ['date', 'invoice_date', 'payment_date', 'created_at', 'updated_at', 'last_sale', 'month'];
            return dateColumns.some(col => columnName.toLowerCase().includes(col));
        }

        // Remove item prefix
        function removeItemPrefix(label) {
            if (!label) return label;
            const str = String(label).trim();
            const prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
            for (const prefix of prefixes) {
                if (str.toLowerCase().startsWith(prefix.toLowerCase())) {
                    return str.substring(prefix.length).trim();
                }
            }
            if (str.toLowerCase() === 'n/a' || str.toLowerCase() === 'na') {
                return 'Others';
            }
            return str;
        }

        // Format number
        function formatNumber(num, decimals = 2) {
            return parseFloat(num).toFixed(decimals);
        }

        // Render table
        function renderTable(data) {
            if (!data || data.length === 0) {
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" class="text-center" style="color: #6c757d; padding: 32px;">No data available</td></tr>';
                return false;
            }

            const headers = Object.keys(data[0]);
            const thead = document.getElementById('tableHead');
            const tbody = document.getElementById('tableBody');

            // Render header
            thead.innerHTML = '<tr>' + headers.map(h =>
                '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 500; color: #6c757d; text-transform: uppercase; border: 1px solid #dee2e6;">' +
                h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) +
                '</th>'
            ).join('') + '</tr>';

            // Render body
            const rowsHTML = data.map((row, index) => {
                const rowClass = index % 2 === 0 ? 'background-color: white;' : 'background-color: #f8f9fa;';
                const cells = headers.map(key => {
                    let val = row[key];

                    if (isDateColumn(key) || (val && typeof val === 'string' && (val.match(/^\d{4}-\d{2}-\d{2}/) || val.match(/^\d{2}\/\d{2}\/\d{4}/)))) {
                        val = formatDate(val);
                    } else if (typeof val === 'string') {
                        val = removeItemPrefix(val);
                    } else if (typeof val === 'number') {
                        val = formatNumber(val, val % 1 ? 2 : 0);
                    }

                    return '<td style="padding: 12px 16px; font-size: 14px; border: 1px solid #dee2e6; color: #212529;">' + (val !== null && val !== undefined ? val : '') + '</td>';
                }).join('');
                return '<tr style="' + rowClass + '">' + cells + '</tr>';
            }).join('');

            tbody.innerHTML = rowsHTML;
        }

        // Render chart
        function renderChart(data) {
            if (!data || data.length === 0) {
                return false;
            }

            const headers = Object.keys(data[0]);
            const labelKey = headers[0];
            const valueKey = headers.find(h => h.toLowerCase().includes('total') || h.toLowerCase().includes('sales') || h.toLowerCase().includes('amount') || h.toLowerCase().includes('revenue')) || headers[1];

            const labels = data.map(row => {
                let label = row[labelKey];
                if (typeof label === 'string') {
                    label = removeItemPrefix(label);
                }
                return label;
            });
            const values = data.map(row => parseFloat(row[valueKey]) || 0);

            const ctx = document.getElementById('chartCanvas');
            if (window.chartInstance) {
                window.chartInstance.destroy();
            }

            window.chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                        data: values,
                        backgroundColor: '#33a1df',
                        borderColor: '#33a1df',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Export to CSV
        function exportTableToCSV() {
            if (!allTableData || allTableData.length === 0) {
                alert('No data to export');
                return false;
            }

            const headers = Object.keys(allTableData[0]);
            const csvRows = [];

            // Add headers
            csvRows.push(headers.map(h => h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(','));

            // Add data rows
            allTableData.forEach(row => {
                const values = headers.map(header => {
                    let val = row[header];
                    if (val === null || val === undefined) {
                        val = '';
                    } else if (typeof val === 'string') {
                        // Escape quotes and wrap in quotes if contains comma or quote
                        val = val.replace(/"/g, '""');
                        if (val.includes(',') || val.includes('"') || val.includes('\n')) {
                            val = '"' + val + '"';
                        }
                    }
                    return val;
                });
                csvRows.push(values.join(','));
            });

            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'report_' + new Date().getTime() + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Initialize
        if (reportData && reportData.data) {
            renderTable(reportData.data);
            renderChart(reportData.data);
        }
    </script>
<?php endif; ?>