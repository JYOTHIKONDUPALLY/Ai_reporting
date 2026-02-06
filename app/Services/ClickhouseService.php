<?php

namespace App\Services;

use ClickHouseDB\Client;

class ClickhouseService
{
    protected $client;
    protected $providerId = '2087'; // Default provider ID

    public function __construct()
    {
        $this->client = new Client([
            'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
            'port' => env('CLICKHOUSE_PORT', 8123),
            'username' => env('CLICKHOUSE_USERNAME', 'default'),
            'password' => env('CLICKHOUSE_PASSWORD', ''),
        ]);

        $this->client->database(env('CLICKHOUSE_DATABASE', 'default'));
        $this->client->setTimeout(10);
        $this->client->setConnectTimeOut(5);
    }

    /**
     * Append provider ID suffix to table names in SQL
     */
    protected function appendProviderIdToTables(string $sql): string
    {
        // List of table names that need provider ID suffix
        $tables = [
            'invoice_details',
            'invoice_items_detail',
            'product_inventory',
            'Range_appointments',
            'paymentDetails',
            'customers',
            'class_sessions',
            'memberships'
        ];
        
        // Replace each table name with provider-suffixed version
        // Use word boundaries to avoid partial matches, but don't match if already suffixed
        foreach ($tables as $table) {
            $suffixedTable = $table . '_' . $this->providerId;
            
            // Pattern: Match table name that is NOT already suffixed with _digits
            // This handles: FROM table, FROM table AS alias, JOIN table alias, etc.
            $pattern = '/\b' . preg_quote($table, '/') . '(?!_\d+)\b/i';
            $sql = preg_replace($pattern, $suffixedTable, $sql);
        }
        
        return $sql;
    }

    public function select($sql)
    {
        // Automatically append provider ID to table names before executing
        $originalSql = $sql;
        $sql = $this->appendProviderIdToTables($sql);
        
        // Log the SQL transformation for debugging (can be removed later)
        if (config('app.debug')) {
            \Log::debug('ClickHouse SQL transformation', [
                'original' => $originalSql,
                'transformed' => $sql,
                'provider_id' => $this->providerId
            ]);
        }
        
        try {
            return $this->client->select($sql)->rows();
        } catch (\Exception $e) {
            // Log the error and SQL for debugging
            \Log::error('ClickHouse query failed', [
                'original_sql' => $originalSql,
                'transformed_sql' => $sql,
                'error' => $e->getMessage(),
                'provider_id' => $this->providerId
            ]);
            throw $e;
        }
    }
}

