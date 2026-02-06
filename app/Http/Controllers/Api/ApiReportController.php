<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ClickhouseService;
use App\Services\AiSqlService;
use Illuminate\Support\Facades\Response;

class ApiReportController extends Controller
{
    protected $clickhouse;

    public function __construct(ClickhouseService $clickhouse)
    {
        $this->clickhouse = $clickhouse;
    }

    /**
     * Test API connection
     */
    public function test()
    {
        try {
            $sql = "SELECT now() AS server_time";
            $results = $this->clickhouse->select($sql);
            
            return response()->json([
                'success' => true,
                'message' => 'API connection successful',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all available predefined reports
     */
    public function listPredefined()
    {
        $reports = [
            [
                'type' => 'daily-sales',
                'title' => 'Daily Sales',
                'description' => 'Daily sales breakdown by date'
            ],
            [
                'type' => 'top-items',
                'title' => 'Top Items',
                'description' => 'Top selling items by quantity and sales'
            ],
            [
                'type' => 'revenue-by-franchise',
                'title' => 'Revenue by Franchise',
                'description' => 'Revenue breakdown by franchise location'
            ],
            [
                'type' => 'payments-by-method',
                'title' => 'Payments by Method',
                'description' => 'Payment transactions grouped by payment method'
            ],
            [
                'type' => 'refunds',
                'title' => 'Refunds',
                'description' => 'List of all refund transactions'
            ],
            [
                'type' => 'products',
                'title' => 'Products',
                'description' => 'Sales report for products only'
            ],
            [
                'type' => 'memberships',
                'title' => 'Memberships',
                'description' => 'Sales report for memberships only'
            ],
            [
                'type' => 'services',
                'title' => 'Services',
                'description' => 'Sales report for services only'
            ],
            [
                'type' => 'classes',
                'title' => 'Classes',
                'description' => 'Sales report for classes only'
            ],
            [
                'type' => 'custom-reports',
                'title' => 'Custom Reports',
                'description' => 'Create custom reports using AI-powered queries'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Get a specific predefined report
     */
    public function getPredefined(Request $request, $type)
    {
        // Get date filters or use default (last month)
        $endDate = $request->input('end_date', date('Y-m-d'));
        $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));
        
        // Get pagination parameters
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 100);
        $offset = ($page - 1) * $perPage;
        
        // Build date filter clause
        $dateFilter = "WHERE invoice_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')";
        $dateFilterJoin = "AND invoice_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')";
        $dateFilterPayment = "WHERE payment_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')";
        
        $queries = [
            'daily-sales' => "SELECT
                                formatDateTime(id.invoice_date, '%d %b %Y') AS invoice_date_formatted,
                                SUM(iid.total_price) AS daily_sales
                            FROM invoice_details id
                            INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
                            {$dateFilter}
                            AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental',
                            'giftcard', 'appointment', 'subscription','guestpass','sponsorship','warranty', 'adavanceBookingFee', 'membershiprental') 
                                 OR lowerUTF8(iid.item_type) LIKE 'misc%' 
                                 OR lowerUTF8(iid.item_type) LIKE 'Misc%')
                            GROUP BY formatDateTime(id.invoice_date, '%d %b %Y')
                            ORDER BY formatDateTime(id.invoice_date, '%d %b %Y') DESC",

            'top-items' => "SELECT iid.item_name, iid.item_type, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                            FROM invoice_items_detail iid
                            INNER JOIN invoice_details id ON iid.invoice_id = id.id
                            {$dateFilter}
                            AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental',
                            'giftcard', 'appointment', 'subscription','guestpass','sponsorship','warranty', 'adavanceBookingFee', 'membershiprental') 
                                 OR lowerUTF8(iid.item_type) LIKE 'misc%' 
                                 OR lowerUTF8(iid.item_type) LIKE 'Misc%')
                            GROUP BY iid.item_name, iid.item_type
                            ORDER BY total_sales DESC",

            'revenue-by-franchise' => "SELECT id.franchise, SUM(iid.total_price) AS total_revenue 
                                       FROM invoice_details id
                                       INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
                                       {$dateFilter}
                                       AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental',
                            'giftcard', 'appointment', 'subscription','guestpass','sponsorship','warranty', 'adavanceBookingFee', 'membershiprental') 
                                            OR lowerUTF8(iid.item_type) LIKE 'misc%' 
                                            OR lowerUTF8(iid.item_type) LIKE 'Misc%')
                                       GROUP BY id.franchise 
                                       ORDER BY total_revenue DESC",

            'payments-by-method' => "SELECT payment_method, COUNT(*) AS transactions, SUM(amount_paid) AS collected 
                                     FROM paymentDetails 
                                     {$dateFilterPayment}
                                     GROUP BY payment_method 
                                     ORDER BY collected DESC",

            'refunds' => "SELECT iid.invoice_id, iid.item_name, iid.refund_amount, iid.refund_tax 
                          FROM invoice_items_detail iid
                          INNER JOIN invoice_details id ON iid.invoice_id = id.id
                          {$dateFilter}
                          and iid.refund_amount > 0 
                          ORDER BY iid.refund_amount DESC",

            'products' => "SELECT iid.item_name, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                           FROM invoice_items_detail iid
                           INNER JOIN invoice_details id ON iid.invoice_id = id.id
                           {$dateFilter}
                           AND lowerUTF8(iid.item_type) = 'product'
                           GROUP BY iid.item_name
                           ORDER BY total_sales DESC",

            'memberships' => "SELECT iid.item_name, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                              FROM invoice_items_detail iid
                              INNER JOIN invoice_details id ON iid.invoice_id = id.id
                              {$dateFilter}
                              AND lowerUTF8(iid.item_type) = 'membership'
                              GROUP BY iid.item_name
                              ORDER BY total_sales DESC",

            'services' => "SELECT iid.item_name, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                           FROM invoice_items_detail iid
                           INNER JOIN invoice_details id ON iid.invoice_id = id.id
                           {$dateFilter}
                           AND lowerUTF8(iid.item_type) = 'service'
                           GROUP BY iid.item_name
                           ORDER BY total_sales DESC",

            'classes' => "SELECT iid.item_name, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                          FROM invoice_items_detail iid
                          INNER JOIN invoice_details id ON iid.invoice_id = id.id
                          {$dateFilter}
                          AND lowerUTF8(iid.item_type) = 'class'
                          GROUP BY iid.item_name
                          ORDER BY total_sales DESC"
        ];

        $titles = [
            'daily-sales' => 'Daily Sales',
            'top-items' => 'Top Items',
            'revenue-by-franchise' => 'Revenue by Franchise',
            'payments-by-method' => 'Payments by Method',
            'refunds' => 'Refunds',
            'products' => 'Products',
            'memberships' => 'Memberships',
            'services' => 'Services',
            'classes' => 'Classes',
            'custom-reports' => 'Custom Reports'
        ];

        // Handle custom-reports type (redirect to Ask-AI)
        if ($type === 'custom-reports') {
            return response()->json([
                'success' => false,
                'message' => 'Use the Ask-AI feature to create custom reports',
                'redirect_to_ai' => true
            ], 400);
        }

        if (!isset($queries[$type])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid report type',
                'available_types' => array_keys($queries)
            ], 400);
        }

        try {
            $sql = $queries[$type] . " LIMIT $perPage OFFSET $offset";
            $results = $this->clickhouse->select($sql);

            // Get total count for pagination (remove LIMIT/OFFSET from query)
            $countSql = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+/i', '', $queries[$type]);
            $countSql = "SELECT COUNT(*) as total FROM ({$countSql})";
            $countResult = $this->clickhouse->select($countSql);
            $total = $countResult[0]['total'] ?? count($results);

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'type' => $type,
                    'title' => $titles[$type] ?? 'Report',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => (int) $total,
                        'total_pages' => (int) ceil($total / $perPage)
                    ],
                    'sql' => $sql
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Query execution failed - '.$sql,
                'error' => $e->getMessage(),
                'sql' => $queries[$type] ?? null
            ], 500);
        }
    }

    /**
     * Run a custom report by key (SQL generated server-side)
     */
    public function run(Request $request)
    {
        $request->validate([
            'report_key' => 'required|string',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:1000',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'audience' => 'sometimes|string',
            'N' => 'sometimes|integer|min:1|max:10000'
        ]);

        $reportKey = $request->input('report_key');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 100);
        $offset = ($page - 1) * $perPage;
        
        // Get date filters or use default (last month)
        $endDate = $request->input('end_date', date('Y-m-d'));
        $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));
        $audience = $request->input('audience', 'all');
        $N = (int) $request->input('N', 100);

        try {
            // Generate SQL from report key
            $sql = $this->generateSqlFromReportKey($reportKey, [
                'start' => $startDate,
                'end' => $endDate,
                'audience' => $audience,
                'N' => $N
            ]);

            if (empty($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid report key or SQL template not available',
                    'report_key' => $reportKey
                ], 400);
            }

            // Clean SQL: remove comments, semicolons, and convert MySQL syntax to ClickHouse
            $sql = $this->cleanSql($sql);
            
            // Add pagination if no LIMIT already in query
            $paginatedSql = $sql;
            if (!preg_match('/limit/i', $sql)) {
                $paginatedSql .= " LIMIT $perPage OFFSET $offset";
            }

            $results = $this->clickhouse->select($paginatedSql);

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'report_key' => $reportKey,
                    'page' => $page,
                    'per_page' => $perPage,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'sql' => $paginatedSql
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Query execution failed - '.$sql,
                'error' => $e->getMessage(),
                'report_key' => $reportKey
            ], 500);
        }
    }

    /**
     * Generate SQL from report key
     */
    protected function generateSqlFromReportKey($reportKey, $params = [])
    {
        $start = $params['start'] ?? date('Y-m-d', strtotime('-1 month'));
        $end = $params['end'] ?? date('Y-m-d');
        $audience = $params['audience'] ?? 'all';
        $N = $params['N'] ?? 100;

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

        // SQL templates for each report key
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
      {$dateFilterAnd}
    GROUP BY tc.customer_id, tc.customer_name, iid.item_name
) SELECT tc.customer_name AS customer, item_name AS ITEM, sum(total_spent) AS total_spent 
FROM ranked_products  
WHERE rn <= {$N} 
GROUP BY customer, ITEM, rn 
ORDER BY total_spent DESC, rn ASC",

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
LIMIT 100",

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

            'prd_slow_movers' => " SELECT
pi.name AS product_name,
    pi.sku,
    pi.qoh,
    pi.qor,
    dateDiff('day', pi.created_at, today()) AS inventory_age_days,
    SUM(iid.quantity) AS total_sold_qty,
    MAX(o.invoice_date) AS last_sale_date,
    ifNull(
        dateDiff('day', MAX(o.invoice_date), today()),
        9999
    ) AS days_since_last_sale
FROM product_inventory pi
LEFT JOIN invoice_items_detail iid
    ON iid.SKU = pi.sku
LEFT JOIN invoice_details o
    ON o.id = iid.invoice_id
WHERE (pi.qoh + pi.qor) > 0
GROUP BY
    pi.name,
    pi.sku,
    pi.qoh,
    pi.qor,
    pi.created_at
    HAVING inventory_age_days > 60
    order by inventory_age_days  desc
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
    iid.subcategory,
    COUNT(DISTINCT id.id) as invoice_count,
    COUNT(iid.quantity) as item_count,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    {$dateFilterAnd}
GROUP BY iid.item_id, iid.subcategory
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
ORDER BY transaction_count DESC"
        ];

        return $templates[$reportKey] ?? null;
    }

    /**
     * Ask AI to generate and execute a query
     */
    public function askAi(Request $request, AiSqlService $ai, ClickhouseService $db)
    {
        $request->validate([
            'question' => 'required|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date'
        ]);

        $question = $request->input('question');
        
        // Get date filters or use default (last month)
        $endDate = $request->input('end_date', date('Y-m-d'));
        $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));

        try {
            // Pass date range to AI service
            $sql = $ai->generateSql($question, $startDate, $endDate);
            
            // Validate SQL was generated
            if (empty($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate SQL query. Please try rephrasing your question.',
                    'question' => $question
                ], 400);
            }
            
            // Append provider ID to all table names (default: 2087)
            
            $results = $db->select($sql);

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'question' => $question,
                    'sql' => $sql,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI query generation or execution failed',
                'error' => $e->getMessage(),
                'question' => $question
            ], 500);
        }
    }

    /**
     * Export report data as CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'sql' => 'required|string'
        ]);

        $sql = $request->input('sql');

        try {
            $results = $this->clickhouse->select($sql);

            // Convert results to CSV
            $filename = "report_" . date('Ymd_His') . ".csv";
            $handle = fopen('php://temp', 'r+');

            if (!empty($results)) {
                // Add headers
                fputcsv($handle, array_keys($results[0]));
                // Add rows
                foreach ($results as $row) {
                    fputcsv($handle, $row);
                }
            }

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return Response::make($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=$filename",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean SQL query: remove comments, semicolons, and convert MySQL to ClickHouse syntax
     */
    private function cleanSql($sql)
    {
        // Remove Markdown code fences (```sql ... ```)
        $sql = preg_replace('/```(sql)?/i', '', $sql);
        $sql = str_replace('```', '', $sql);

        // Remove SQL comments (-- comments and /* */ comments) - ClickHouse doesn't allow comments in GROUP BY
        // Remove /* */ style comments first (block comments) - handles multi-line comments
        $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);
        // Remove -- style comments (line comments) - handle both end-of-line and inline comments
        $sql = preg_replace('/\s*--.*$/m', '', $sql);

        // Remove semicolons at the end (ClickHouse will add FORMAT JSON, semicolons cause syntax errors)
        $sql = rtrim($sql, ';');

        // Convert MySQL DATE_FORMAT to ClickHouse formatDateTime (if mistakenly used)
        $sql = preg_replace('/DATE_FORMAT\s*\(/i', 'formatDateTime(', $sql);

        // Clean up whitespace: normalize multiple spaces/newlines to single space
        $sql = preg_replace('/\s+/', ' ', $sql);
        // Fix spacing around commas
        $sql = preg_replace('/\s*,\s*/', ', ', $sql);
        // Remove trailing commas that might result from comment removal
        $sql = preg_replace('/,\s*$/', '', $sql);
        $sql = preg_replace('/,\s*,/', ',', $sql); // Remove double commas

        return trim($sql);
    }
}

