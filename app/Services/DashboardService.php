<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    protected $clickhouse;
    protected $configPath;

    public function __construct(ClickhouseService $clickhouse)
    {
        $this->clickhouse = $clickhouse;
        $this->configPath = storage_path('app/dashboards');
        
        // Create dashboards directory if it doesn't exist
        if (!File::exists($this->configPath)) {
            File::makeDirectory($this->configPath, 0755, true);
        }
    }

    /**
     * Load dashboard configuration from JSON file
     */
    public function loadDashboardConfig(string $dashboardType): ?array
    {
        $configFile = $this->configPath . '/' . $dashboardType . '.json';
        
        if (!File::exists($configFile)) {
            Log::warning("Dashboard config not found: {$configFile}");
            return null;
        }

        $config = json_decode(File::get($configFile), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Invalid JSON in dashboard config: {$configFile}", [
                'error' => json_last_error_msg()
            ]);
            return null;
        }

        return $config;
    }

    /**
     * Execute SQL query for a widget
     */
    public function executeWidgetQuery(string $sql, array $params = []): array
    {
        try {
            // Replace parameters in SQL if provided
            foreach ($params as $key => $value) {
                $sql = str_replace(':' . $key, $value, $sql);
            }
            
            return $this->clickhouse->select($sql);
        } catch (\Exception $e) {
            Log::error("Widget query execution failed", [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get dashboard data with all widgets executed
     */
    public function getDashboardData(string $dashboardType, array $filters = []): array
    {
        log::info('Getting dashboard data', [
            'dashboard' => $dashboardType
        ]);
        $config = $this->loadDashboardConfig($dashboardType);
        
        if (!$config) {
            return [
                'error' => "Dashboard configuration not found: {$dashboardType}",
                'widgets' => []
            ];
        }

        $widgets = [];
        
        foreach ($config['widgets'] ?? [] as $widget) {
            $sql = $widget['sql'] ?? '';
            
            if (empty($sql)) {
                $widgets[] = [
                    'id' => $widget['id'] ?? uniqid(),
                    'title' => $widget['title'] ?? 'Untitled',
                    'type' => $widget['type'] ?? 'table',
                    'error' => 'No SQL query defined',
                    'data' => []
                ];
                continue;
            }

            // Apply filters to SQL
            $sql = $this->applyFilters($sql, $filters);
            
            // Execute query
            $data = $this->executeWidgetQuery($sql, $widget['params'] ?? []);
            
            // Process pie chart data to limit to top 10 items
            if (($widget['type'] ?? '') === 'pie' && !empty($data)) {
                $data = $this->processPieChartData($data, $widget['config'] ?? []);
            }
            
            $widgets[] = [
                'id' => $widget['id'] ?? uniqid(),
                'title' => $widget['title'] ?? 'Untitled',
                'type' => $widget['type'] ?? 'table',
                'config' => $widget['config'] ?? [],
                'data' => $data,
                'sql' => $sql
            ];
        }

        return [
            'title' => $config['title'] ?? ucfirst($dashboardType) . ' Dashboard',
            'description' => $config['description'] ?? '',
            'layout' => $config['layout'] ?? 'grid',
            'widgets' => $widgets
        ];
    }

    /**
     * Process pie chart data to limit to top 10 items and group rest as "Others"
     */
    protected function processPieChartData(array $data, array $config): array
    {
        if (empty($data) || count($data) <= 10) {
            return $data;
        }

        // Get the value field from config
        $valueField = $config['valueField'] ?? null;
        $labelField = $config['labelField'] ?? null;

        // If config doesn't specify fields, try to infer from data
        if (!$valueField || !$labelField) {
            $keys = array_keys($data[0] ?? []);
            if (count($keys) >= 2) {
                $labelField = $labelField ?? $keys[0];
                $valueField = $valueField ?? $keys[1];
            } else {
                return $data; // Can't process without proper fields
            }
        }

        // Sort data by value in descending order
        usort($data, function($a, $b) use ($valueField) {
            $valA = is_numeric($a[$valueField] ?? 0) ? (float)$a[$valueField] : 0;
            $valB = is_numeric($b[$valueField] ?? 0) ? (float)$b[$valueField] : 0;
            return $valB <=> $valA;
        });

        // Take top 10 items
        $topItems = array_slice($data, 0, 10);
        $remainingItems = array_slice($data, 10);

        // Calculate sum of remaining items
        $othersSum = 0;
        foreach ($remainingItems as $item) {
            $value = is_numeric($item[$valueField] ?? 0) ? (float)$item[$valueField] : 0;
            $othersSum += $value;
        }

        // Add "Others" item if there are remaining items
        if ($othersSum > 0 && !empty($remainingItems)) {
            $othersItem = [];
            $othersItem[$labelField] = 'Others';
            $othersItem[$valueField] = $othersSum;
            
            // Preserve other fields from the first remaining item
            foreach (array_keys($remainingItems[0]) as $key) {
                if ($key !== $labelField && $key !== $valueField) {
                    $othersItem[$key] = null; // or set to a default value
                }
            }
            
            $topItems[] = $othersItem;
        }

        return $topItems;
    }

    /**
     * Get date range based on period
     */
    protected function getDateRangeForPeriod(string $period): array
    {
        $now = \Carbon\Carbon::now();
        
        switch ($period) {
            case 'this_month':
                return [
                    'start_date' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $now->copy()->endOfMonth()->format('Y-m-d')
                ];
            
            case 'last_month':
                $lastMonth = $now->copy()->subMonth();
                return [
                    'start_date' => $lastMonth->copy()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $lastMonth->copy()->endOfMonth()->format('Y-m-d')
                ];
            
            case 'year':
                return [
                    'start_date' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end_date' => $now->copy()->endOfYear()->format('Y-m-d')
                ];
            
            case 'last_year':
                $lastYear = $now->copy()->subYear();
                return [
                    'start_date' => $lastYear->copy()->startOfYear()->format('Y-m-d'),
                    'end_date' => $lastYear->copy()->endOfYear()->format('Y-m-d')
                ];
            
            default:
                return [
                    'start_date' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end_date' => $now->copy()->endOfMonth()->format('Y-m-d')
                ];
        }
    }

    /**
     * Apply date and other filters to SQL query
     */
    protected function applyFilters(string $sql, array $filters): string
    {
        $startDate = null;
        $endDate = null;

        // If period is provided, calculate date range
        if (isset($filters['period'])) {
            $dateRange = $this->getDateRangeForPeriod($filters['period']);
            $startDate = $dateRange['start_date'];
            $endDate = $dateRange['end_date'];
        } elseif (isset($filters['start_date']) && isset($filters['end_date'])) {
            // Use explicit date range if provided
            $startDate = $filters['start_date'];
            $endDate = $filters['end_date'];
        }

        // Apply date range filter if we have dates
        if ($startDate && $endDate) {
            // Detect which date column is used in the query
            $dateColumn = $this->detectDateColumn($sql);
            
            // Only apply date filter if we found a date column and the query references invoice/order tables
            if ($dateColumn && $this->shouldApplyDateFilter($sql)) {
                $dateCondition = "{$dateColumn} BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')";
                
                // Insert WHERE clause before ORDER BY, GROUP BY, LIMIT, etc.
                $sql = $this->insertWhereClause($sql, $dateCondition);
            }
        }

        // Apply franchise filter if provided
        if (isset($filters['franchise'])) {
            $franchiseCondition = "franchise = '{$filters['franchise']}'";
            $sql = $this->insertWhereClause($sql, $franchiseCondition);
        }

        return $sql;
    }

    /**
     * Insert WHERE clause condition before ORDER BY, GROUP BY, LIMIT, etc.
     */
    protected function insertWhereClause(string $sql, string $condition): string
    {
        // Check if WHERE clause already exists
        $hasWhere = stripos($sql, 'WHERE') !== false;
        
        // Find the position to insert the condition
        // Look for ORDER BY, GROUP BY, LIMIT, HAVING (case insensitive)
        $patterns = [
            '/\s+ORDER\s+BY\s+/i',
            '/\s+GROUP\s+BY\s+/i',
            '/\s+LIMIT\s+/i',
            '/\s+HAVING\s+/i',
        ];
        
        $insertPosition = -1;
        $insertBefore = null;
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                if ($insertPosition === -1 || $pos < $insertPosition) {
                    $insertPosition = $pos;
                    $insertBefore = $matches[0][0];
                }
            }
        }
        
        // If we found a position before ORDER BY, GROUP BY, etc., insert there
        if ($insertPosition !== -1) {
            $before = substr($sql, 0, $insertPosition);
            $after = substr($sql, $insertPosition);
            
            if ($hasWhere) {
                return $before . " AND {$condition}" . $after;
            } else {
                return $before . " WHERE {$condition}" . $after;
            }
        }
        
        // Otherwise, append at the end
        if ($hasWhere) {
            return $sql . " AND {$condition}";
        } else {
            return $sql . " WHERE {$condition}";
        }
    }

    /**
     * Detect which date column is used in the SQL query
     * For invoice_details table, always use invoice_date
     */
    protected function detectDateColumn(string $sql): ?string
    {
        // If query references invoice_details table, always use invoice_date
        if (stripos($sql, 'invoice_details') !== false) {
            // Try to find the table alias for invoice_details
            // Look for patterns like: FROM invoice_details alias or JOIN invoice_details alias
            $alias = null;
            
            // Check for FROM clause with alias
            if (preg_match('/FROM\s+invoice_details\s+(\w+)/i', $sql, $matches)) {
                $alias = $matches[1];
            }
            // Check for JOIN clause with alias
            elseif (preg_match('/JOIN\s+invoice_details\s+(\w+)/i', $sql, $matches)) {
                $alias = $matches[1];
            }
            // Check for INNER JOIN, LEFT JOIN, etc.
            elseif (preg_match('/(INNER|LEFT|RIGHT|FULL)?\s*JOIN\s+invoice_details\s+(\w+)/i', $sql, $matches)) {
                $alias = $matches[2];
            }
            
            // Only use alias if it's not a SQL keyword
            if ($alias && !$this->isSqlKeyword($alias)) {
                return "{$alias}.invoice_date";
            }
            
            // No valid alias found, use invoice_date directly
            return 'invoice_date';
        }
        
        // For other tables, check if created_at is used
        if (stripos($sql, 'created_at') !== false) {
            // Try to find alias for created_at (only if it's actually used with an alias in the query)
            if (preg_match('/\b([a-z_]+)\.created_at\b/i', $sql, $matches)) {
                $potentialAlias = $matches[1];
                // Verify it's not a SQL keyword
                if (!$this->isSqlKeyword($potentialAlias)) {
                    return $matches[0];
                }
            }
            return 'created_at';
        }
        
        return null;
    }

    /**
     * Check if a word is a SQL keyword that shouldn't be used as a table alias
     */
    protected function isSqlKeyword(string $word): bool
    {
        $keywords = [
            'select', 'from', 'where', 'group', 'order', 'by', 'having', 'limit', 'offset',
            'join', 'inner', 'left', 'right', 'full', 'outer', 'on', 'as', 'and', 'or', 'not',
            'union', 'intersect', 'except', 'distinct', 'all', 'case', 'when', 'then', 'else', 'end',
            'between', 'in', 'like', 'is', 'null', 'exists', 'any', 'some', 'count', 'sum', 'avg',
            'max', 'min', 'if', 'nullif', 'coalesce', 'cast', 'convert', 'date', 'time', 'datetime'
        ];
        
        return in_array(strtolower($word), $keywords);
    }

    /**
     * Determine if date filter should be applied to this query
     */
    protected function shouldApplyDateFilter(string $sql): bool
    {
        $sqlUpper = strtoupper($sql);
        
        // Don't apply date filter to queries that:
        // 1. Only query inventory tables (no invoice/order data)
        if (stripos($sql, 'product_inventory') !== false && 
            stripos($sql, 'invoice_details') === false && 
            stripos($sql, 'invoice_items') === false) {
            return false;
        }
        
        // 2. Are aggregate queries without date references (like category summaries)
        // But we'll allow them if they reference invoice tables
        
        // 3. Queries that already have explicit date filters in WHERE clause
        // (We'll still add our filter, but this could be enhanced)
        
        // Apply filter if query references invoice/order tables
        return stripos($sql, 'invoice_details') !== false || 
               stripos($sql, 'invoice_items') !== false ||
               stripos($sql, 'invoice_date') !== false ||
               stripos($sql, 'created_at') !== false;
    }

    /**
     * List all available dashboard types
     */
    public function listDashboards(): array
    {
        $dashboards = [];
        
        if (!File::exists($this->configPath)) {
            return $dashboards;
        }

        $files = glob($this->configPath . '/*.json');

        foreach ($files as $filePath) {
            $fileName = basename($filePath, '.json');
            $config = json_decode(File::get($filePath), true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($config)) {
                $dashboards[] = [
                    'type' => $fileName,
                    'title' => $config['title'] ?? ucfirst(str_replace('-', ' ', $fileName)),
                    'description' => $config['description'] ?? ''
                ];
            }
        }

        return $dashboards;
    }
}

