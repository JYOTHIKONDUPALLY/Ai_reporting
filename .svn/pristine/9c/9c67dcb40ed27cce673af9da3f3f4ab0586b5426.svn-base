<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ClickhouseService;
use Illuminate\Support\Facades\Response;
use App\Services\AiSqlService;

class ReportController extends Controller
{
    protected $clickhouse;

    public function __construct(ClickhouseService $clickhouse)
    {
        $this->clickhouse = $clickhouse;
    }

    public function test()
    {
        $sql = "SELECT now() AS server_time"; // simple query
        $results = $this->clickhouse->select($sql);

        return response()->json($results);
    }
    public function index()
{
    // Redirect to daily-sales report by default
    return redirect()->route('reports.predefined', 'daily-sales');
}





public function predefined(Request $request, $type)
{
    // Get date filters or use default (last month)
    $endDate = $request->input('end_date', date('Y-m-d'));
    $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));
    
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
                        AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') 
                             OR lowerUTF8(iid.item_type) LIKE 'misc%' 
                             OR lowerUTF8(iid.item_type) LIKE 'Misc%')
                        GROUP BY formatDateTime(id.invoice_date, '%d %b %Y')
                        ORDER BY formatDateTime(id.invoice_date, '%d %b %Y') DESC",

        'top-items' => "SELECT iid.item_name, iid.item_type, SUM(iid.quantity) AS total_qty, SUM(iid.total_price) AS total_sales 
                        FROM invoice_items_detail iid
                        INNER JOIN invoice_details id ON iid.invoice_id = id.id
                        {$dateFilter}
                        AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') 
                             OR lowerUTF8(iid.item_type) LIKE 'misc%' 
                             OR lowerUTF8(iid.item_type) LIKE 'Misc%')
                        GROUP BY iid.item_name, iid.item_type
                        ORDER BY total_sales DESC",

        'revenue-by-franchise' => "SELECT id.franchise, SUM(iid.total_price) AS total_revenue 
                                   FROM invoice_details id
                                   INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
                                   {$dateFilter}
                                   AND (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') 
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
                      ORDER BY iid.refund_amount DESC"
    ];

    $titles = [
        'daily-sales' => 'Daily Sales',
        'top-items' => 'Top Items',
        'revenue-by-franchise' => 'Revenue by Franchise',
        'payments-by-method' => 'Payments by Method',
        'refunds' => 'Refunds'
    ];

    if (!isset($queries[$type])) {
        return redirect()->route('reports.index')->with('error', 'Invalid report type');
    }

    $page = (int) $request->input('page', 1);
    $perPage = (int) $request->input('perPage', 100);
    $offset = ($page - 1) * $perPage;

    try {
        $sql = $queries[$type] . " LIMIT $perPage OFFSET $offset";

        $results = $this->clickhouse->select($sql);

        return view('reports.index', [
            'results' => $results,
            'sql' => $queries[$type],
            'page' => $page,
            'perPage' => $perPage,
            'predefinedType' => $type,
            'predefinedTitle' => $titles[$type] ?? 'Report',
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    } catch (\Exception $e) {
        return view('reports.index', [
            'results' => [],
            'sql' => $queries[$type],
            'error' => $e->getMessage(),
            'page' => $page,
            'perPage' => $perPage,
            'predefinedType' => $type,
            'predefinedTitle' => $titles[$type] ?? 'Report',
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
}



public function export(Request $request)
{
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
        return back()->with('error', 'Export failed: ' . $e->getMessage());
    }
}


public function testAi(AiSqlService $ai, ClickhouseService $db)
{
    $question = "Show me the last 5 invoices with their total amounts";

    try {
        $sql = $ai->generateSql($question);
        $results = $db->select($sql);

        return response()->json([
            'question' => $question,
            'sql' => $sql,
            'results' => $results
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

public function askAi(Request $request, AiSqlService $ai, ClickhouseService $db)
{
    $question = $request->input('question');
    
    // Get date filters or use default (last month)
    $endDate = $request->input('end_date', date('Y-m-d'));
    $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));

    // Debug: Log the request data
    \Log::info('AI Request Data:', [
        'all_input' => $request->all(),
        'question' => $question,
        'question_type' => gettype($question),
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

    // Validate that question is provided and not empty
    if (empty($question)) {
        return view('reports.index', [
            'results' => [],
            'sql' => '',
            'question' => '',
            'error' => 'Please provide a question for the AI to process. Received: ' . json_encode($request->all()),
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    try {
        // Pass date range to AI service
        $sql = $ai->generateSql($question, $startDate, $endDate);
        
        // Validate SQL was generated
        if (empty($sql)) {
            return view('reports.index', [
                'results' => [],
                'sql' => '',
                'question' => $question,
                'aiQuestionTitle' => $question,
                'error' => 'Failed to generate SQL query. Please try rephrasing your question or contact support if the issue persists.',
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
        }
        
        $results = $db->select($sql);

        return view('reports.index', [
            'results' => $results,
            'sql' => $sql,
            'question' => $question,
            'aiQuestionTitle' => $question, // Use the question as the title
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    } catch (\Exception $e) {
        // Log error for debugging
        \Log::error('AI SQL Generation Error:', [
            'question' => $question,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Always show error message to user - don't hide AI API errors
        $errorMessage = $e->getMessage();
        
        return view('reports.index', [
            'results' => [],
            'sql' => '',
            'question' => $question,
            'aiQuestionTitle' => $question,
            'error' => $errorMessage, // Show the actual error message to user
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
}
public function run(Request $request)
{
    $sql = $request->input('sql');
    $page = (int) $request->input('page', 1);
    $perPage = (int) $request->input('perPage', 100); // Default = 100
    $offset = ($page - 1) * $perPage;
    
    // Get date filters or use default (last month)
    $endDate = $request->input('end_date', date('Y-m-d'));
    $startDate = $request->input('start_date', date('Y-m-d', strtotime('-1 month')));

    try {
        // Clean SQL: remove comments, semicolons, and convert MySQL syntax to ClickHouse
        $sql = $this->cleanSql($sql);
        
        // Add pagination if no LIMIT already in query
        $paginatedSql = $sql;
        if (!preg_match('/limit/i', $sql)) {
            $paginatedSql .= " LIMIT $perPage OFFSET $offset";
        }

        $results = $this->clickhouse->select($paginatedSql);

        return view('reports.index', [
            'results' => $results,
            'sql' => $sql,
            'page' => $page,
            'perPage' => $perPage,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    } catch (\Exception $e) {
        return view('reports.index', [
            'results' => [],
            'sql' => $sql,
            'error' => $e->getMessage(),
            'page' => $page,
            'perPage' => $perPage,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
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
