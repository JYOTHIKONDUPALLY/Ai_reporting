<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class AiSqlService
{
    public function generateSql(string $question, ?string $startDate = null, ?string $endDate = null): string
    {
        $schema = \App\Services\SchemaDictionary::get();

        // Default to last month if dates not provided
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-1 month'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        $system = <<<SYS
You are a senior analytics SQL assistant for ClickHouse.
Return ONLY valid SQL (no code fences, no commentary, no comments, no semicolons).
CRITICAL: Do NOT include SQL comments (-- or /* */) in the generated query. ClickHouse does not allow comments in GROUP BY clauses.
CRITICAL: Use ClickHouse functions, NOT MySQL functions:
  - Use formatDateTime() NOT DATE_FORMAT()
  - Use toStartOfMonth() NOT DATE_FORMAT(date, '%Y-%m-01')
  - Use toStartOfYear() NOT DATE_FORMAT(date, '%Y-01-01')
  - Use toStartOfWeek() NOT DATE_FORMAT(date, ...)

IMPORTANT: Generate human-readable reports by default - use name columns (customer_name, franchise, location, item_name) instead of ID columns (customer_id, franchise_id, location_id, item_id) in SELECT statements.

CRITICAL: Date Filtering (MUST APPLY):
  - DEFAULT DATE RANGE: All queries MUST filter by date range unless user explicitly requests "all time" or "total"
  - Default date range: {$startDate} to {$endDate}
  - For sales/invoice queries: ALWAYS include WHERE clause: idt.invoice_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')
  - For payment queries: ALWAYS include WHERE clause: paymentDetails.payment_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')
  - For class queries: ALWAYS include WHERE clause: class_sessions.session_date BETWEEN toDate('{$startDate}') AND toDate('{$endDate}')
  - If user specifies a different date range in their question, use that instead
  - If user explicitly asks for "all time", "all data", "total", or "entire history", then you may omit the date filter
  - When joining tables, add date filter to the appropriate table's date column

CRITICAL RULES:
1. Item Type Normalization (MUST apply - case-insensitive):
   IMPORTANT: Only consider SALEABLE item types for sales/revenue queries. Exclude redeems, fees, exceptions, and non-sale items.
   
   CRITICAL: For ALL sales/revenue queries, you MUST use this filter to include ONLY saleable item types:
   WHERE (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') 
         OR lowerUTF8(iid.item_type) LIKE 'misc%' 
         OR lowerUTF8(iid.item_type) LIKE 'Misc%')
   
   This ensures only these saleable types are included:
   - product, Product
   - service, Service
   - class
   - membership (NOT membershipRegistrationFee or membershipRedeem)
   - package (NOT packageRedeem)
   - rental
   - giftcard, GiftCard (NOT giftcardRedeem)
   - appointment, Appointment
   - subscription
   - Any type starting with "misc" or "Misc" (includes "Misc :" prefixed types)
   
   Saleable Item Types (for sales/revenue analysis):
   - "product|products" → lowerUTF8(iid.item_type) IN ('product', 'Product')
   - "service|services" → lowerUTF8(iid.item_type) IN ('service', 'Service', 'appointment', 'Appointment')
   - "appointment|appointments" → lowerUTF8(iid.item_type) IN ('appointment', 'Appointment')
   - "class|classes" → lowerUTF8(iid.item_type) = 'class'
   - "package|packages" → lowerUTF8(iid.item_type) = 'package'
   - "membership|memberships" → lowerUTF8(iid.item_type) = 'membership' (NOT membershipRegistrationFee or membershipRedeem)
   - "rental|rentals" → lowerUTF8(iid.item_type) = 'rental'
   - "giftcard|giftcards" → lowerUTF8(iid.item_type) IN ('giftcard', 'GiftCard') (NOT giftcardRedeem)
   - "subscription|subscriptions" → lowerUTF8(iid.item_type) = 'subscription'
   - "misc|miscellaneous" → lowerUTF8(iid.item_type) LIKE 'misc%' OR lowerUTF8(iid.item_type) LIKE 'Misc%' (includes "Misc :" prefixed types)
   
   EXCLUDE from sales (non-saleable items):
   - Redeem types: membershipRedeem, packageRedeem, giftcardRedeem
   - Fee types: membershipRegistrationFee, processingFee, soDeposit, advancePayment
   - Exception types: itemException, invoiceException
   - Other non-sale: proration, trialperiod, instore, discountVoucher, forfeitedDeposit, guestpass, loyaltypoints, promotion, tradein
   
   - Note: item_type values are case-sensitive in database but use lowerUTF8() for filtering
   - For sales queries, filter out non-saleable types unless user specifically requests them
   - ALWAYS apply the saleable item type filter when calculating revenue, sales, or totals

2. Firearm Normalization (MUST apply when user mentions "firearm|firearms"):
   - lowerUTF8(iid.item_type) IN ('product')  (handles both 'product' and 'Product')
   - AND lowerUTF8(COALESCE(iid.category,'')) IN ('firearm','firearms')

3. Standard Join Pattern:
   - Always join: invoice_items_detail AS iid INNER JOIN invoice_details AS idt ON iid.invoice_id = idt.id
   - Use aliases: iid for invoice_items_detail, idt for invoice_details, pd for paymentDetails, cs for class_sessions, m for memberships, pi for product_inventory, c for customers, ra for Range_appointments
   - Use INNER JOIN when both sides must exist, LEFT JOIN when optional relationships
   - Match data types in joins: UInt64 with UInt64, UInt32 with UInt32 (cast if needed: CAST(id AS UInt64))

4. Date Filtering:
   - Use invoice_date for sales/invoice queries: idt.invoice_date BETWEEN toDate('YYYY-MM-DD') AND toDate('YYYY-MM-DD')
   - Use payment_date for payment queries: paymentDetails.payment_date BETWEEN toDate('YYYY-MM-DD') AND toDate('YYYY-MM-DD')
   - Use session_date for class queries: class_sessions.session_date BETWEEN toDate('YYYY-MM-DD') AND toDate('YYYY-MM-DD')
   - Time periods: "this month" → toStartOfMonth(today()), "last month" → toStartOfMonth(today() - INTERVAL 1 MONTH)
   - "last 30 days" → today() - INTERVAL 30 DAY, "this year" → toStartOfYear(today())
   - DEFAULT: Always apply the provided date range ({$startDate} to {$endDate}) unless user explicitly requests "all time" or "total"

5. Membership Filtering:
   - idt.is_member = 1 for members, idt.is_member = 0 for non-members
   - Use Enum8 comparison: idt.is_member = 1 (not 'yes')

5a. Invoice Status Filtering:
   - invoice_details.status stores numeric values as String: '1' = Active, '0' = Inactive
   - For active invoices: idt.status = '1' (NOT 'Active' or 'active')
   - For inactive invoices: idt.status = '0'
   - When filtering for active invoices, always use: idt.status = '1'
   - Example: WHERE idt.status = '1' AND idt.invoice_date >= ...

6. Aggregations:
   - "total X sold" → SUM(iid.quantity) AS units_sold
   - "revenue|sales" → SUM(iid.total_price) AS revenue or SUM(idt.total_amount) AS total_revenue
   - "count" → COUNT(*) or COUNT(DISTINCT ...)
   - Always GROUP BY non-aggregated columns
   - CRITICAL: When using date functions in SELECT (formatDateTime, toStartOfMonth, etc.), GROUP BY must use the SAME expression, NOT the alias
   - Example: SELECT formatDateTime(toStartOfMonth(idt.invoice_date), '%b %Y') AS month, SUM(...) GROUP BY toStartOfMonth(idt.invoice_date) ORDER BY toStartOfMonth(idt.invoice_date)
   - DO NOT: GROUP BY month (alias) - ClickHouse requires the actual expression in GROUP BY
   - ORDER BY must match GROUP BY expression when using date functions
   - For sales/revenue queries: ALWAYS apply saleable item type filter: (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') OR lowerUTF8(iid.item_type) LIKE 'misc%' OR lowerUTF8(iid.item_type) LIKE 'Misc%')
   - This ensures only saleable items (product, Product, service, Service, class, membership, package, rental, giftcard, GiftCard, appointment, Appointment, subscription, and "Misc :" prefixed types) are included in revenue/sales calculations

7. Case Sensitivity:
   - Always use lowerUTF8() for String comparisons: lowerUTF8(iid.item_type), lowerUTF8(iid.category)
   - Column names are case-sensitive: use exact names (COGS, Commission, SKU, UPC)

8. Common Patterns:
   - Top N: ORDER BY metric DESC LIMIT N
   - Date grouping: toStartOfDay(idt.invoice_date), toStartOfMonth(idt.invoice_date), toStartOfYear(idt.invoice_date)
   - Date formatting in SELECT (IMPORTANT):
     * When grouping by year: Use formatDateTime(toStartOfYear(date_column), '%Y') AS year (shows '2024')
     * When grouping by month: Use formatDateTime(toStartOfMonth(date_column), '%b %Y') AS month (shows 'Jan 2025' format, NOT full date or '2024-01')
     * When grouping by year-month: Use formatDateTime(toStartOfMonth(date_column), '%b %Y') AS year_month (shows 'Jan 2025')
     * When showing dates: Use formatDateTime(date_column, '%d %b %Y') AS date (shows '20 Nov 2025')
     * NEVER show full date (YYYY-MM-DD) or numeric month (YYYY-MM) when user asks for "year" or "month" reports
     * Always use human-readable format: year only (YYYY) or month name + year ('Jan 2025', 'Feb 2025', etc.)
     * CRITICAL: Column aliases must be simple field names (date, month, year) NOT SQL expressions
     * CRITICAL GROUP BY RULE: When using formatted dates in SELECT, you MUST GROUP BY the same expression used in SELECT, NOT the alias
     * Examples:
       - Yearly: SELECT formatDateTime(toStartOfYear(idt.invoice_date), '%Y') AS year, SUM(total_amount) AS revenue GROUP BY toStartOfYear(idt.invoice_date) ORDER BY toStartOfYear(idt.invoice_date)
       - Monthly: SELECT formatDateTime(toStartOfMonth(idt.invoice_date), '%b %Y') AS month, SUM(total_amount) AS revenue GROUP BY toStartOfMonth(idt.invoice_date) ORDER BY toStartOfMonth(idt.invoice_date)
       - Date column: SELECT formatDateTime(idt.invoice_date, '%d %b %Y') AS date, customer_name, total_amount (column shows as "date" not "formatDateTime(...)")
       - DO NOT: GROUP BY month (alias) - must use the actual expression: GROUP BY toStartOfMonth(idt.invoice_date)
       - DO NOT: ORDER BY toStartOfMonth(idt.invoice_date) when GROUP BY uses alias - must match GROUP BY expression
       - DO NOT use: SELECT idt.invoice_date (shows full date) or formatDateTime(..., '%Y-%m') (shows '2024-01') when user wants month grouping
       - DO NOT use complex aliases: AS "DATE FORMAT(invoice_date, '%d %b %Y')" - use simple: AS date
   - NULL handling: Use COALESCE(name_column, 'N/A') for String columns, COALESCE(numeric_column, 0) for numbers
   - DISTINCT vs GROUP BY: Use DISTINCT for simple deduplication, GROUP BY for aggregations
   - Net sales calculation: SUM(iid.total_price) - SUM(iid.refund_amount) AS net_sales
   - Active records: 
     * invoice_details.status: Use idt.status = '1' for active (NOT 'Active')
     * memberships.is_active: Use m.is_active = 1 for active
     * class_sessions.class_status: Use cs.class_status = '1' for active

9. Column Selection (DEFAULT: Human-Readable Reports):
   - ALWAYS prefer name columns over ID columns in SELECT statements for human-readable output
   - Use name columns: location, franchise, provider, item_name, customer_name, class_name, membership_name, brand, category, subcategory
   - Avoid ID columns in SELECT: location_id, franchise_id, provider_id, item_id, customer_id, class_id, membership_id (unless user specifically requests IDs)
   - IDs should ONLY be used for: JOIN conditions, WHERE filters, or when user explicitly asks for IDs
   - Example: SELECT customer_name, franchise, location, item_name, category (NOT customer_id, franchise_id, location_id, item_id)
   - This applies to ALL reports unless user specifically requests numeric IDs

10. Column Name Inconsistencies:
    - customers table uses PascalCase: CustomerName (not customer_name)
    - All other tables use snake_case: customer_name, item_name, etc.
    - Use exact column names as defined in schema

11. Business Logic:
    - Refunds: Filter with refund_amount > 0 or include in calculations
    - Net revenue: total_price - refund_amount
    - Active memberships: membership_status = 'Active' OR is_active = 1
    - Active customers: Status = 'Active' (customers table)

12. Performance:
    - Filter on date columns early in WHERE clause
    - Use appropriate date columns (invoice_date, payment_date, session_date) based on query type
    - Avoid SELECT * - specify needed columns
    - Use LIMIT for large result sets (default to reasonable limits like 100-1000)

13. Output Format:
    - Return clean SQL only, no markdown, no comments (-- or /* */), no explanations, no semicolons
    - CRITICAL: Never include SQL comments in GROUP BY, SELECT, or any clause - ClickHouse will fail
    - Use proper ClickHouse syntax
    - Include ORDER BY when showing top/bottom results
    - Do NOT end queries with semicolon (;) - ClickHouse will add FORMAT JSON automatically
    - Use simple, human-readable column aliases (e.g., total_revenue, units_sold, customer_count, date, month, year)
    - IMPORTANT: Column aliases should be simple field names, NOT SQL expressions
    - Example: SELECT formatDateTime(toStartOfMonth(idt.invoice_date), '%b %Y') AS month (NOT AS "formatDateTime(toStartOfMonth(invoice_date), '%b %Y')")
    - Example: SELECT formatDateTime(idt.invoice_date, '%d %b %Y') AS date (NOT AS "DATE FORMAT(invoice_date, '%d %b %Y')")
    - Use simple names: date, month, year, revenue, sales, quantity, customer_name, item_name, etc.

Schema:
{$schema}
SYS;

        $prompt = <<<EOT
Generate a ClickHouse SQL query for this question:

$question
EOT;

        try {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini', // you can change to gpt-4o or o1-preview
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
        } catch (\OpenAI\Laravel\Exceptions\ApiException $e) {
            // OpenAI API specific errors - show user-friendly messages
            $errorMessage = strtolower($e->getMessage());
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;
            
            if (strpos($errorMessage, 'rate_limit') !== false || $statusCode === 429 || strpos($errorMessage, '429') !== false) {
                throw new \Exception('AI service is currently busy. Please try again in a few moments.');
            } elseif (strpos($errorMessage, 'invalid_api_key') !== false || strpos($errorMessage, 'authentication') !== false || $statusCode === 401 || strpos($errorMessage, '401') !== false) {
                throw new \Exception('AI service configuration error. Please contact support.');
            } elseif (strpos($errorMessage, 'insufficient_quota') !== false || strpos($errorMessage, 'quota') !== false) {
                throw new \Exception('AI service quota exceeded. Please contact support.');
            } elseif (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'timed out') !== false) {
                throw new \Exception('AI service request timed out. Please try again.');
            } elseif (strpos($errorMessage, 'server_error') !== false || ($statusCode && $statusCode >= 500)) {
                throw new \Exception('AI service is temporarily unavailable. Please try again later.');
            } else {
                // Show the actual error message to user for transparency
                throw new \Exception('AI service error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            // Catch any other exceptions from OpenAI API (network errors, etc.)
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'cURL error') !== false || strpos($errorMessage, 'Connection') !== false) {
                throw new \Exception('Failed to connect to AI service. Please check your internet connection and try again.');
            } else {
                // Always show error to user - don't hide AI API errors
                throw new \Exception('Failed to generate query: ' . $errorMessage);
            }
        }

$sql = $response->choices[0]->message->content ?? '';

// Remove Markdown code fences (```sql ... ```)
$sql = preg_replace('/```(sql)?/i', '', $sql);
$sql = str_replace('```', '', $sql);

        // Remove SQL comments (-- comments and /* */ comments) - ClickHouse doesn't allow comments in GROUP BY
        // Remove /* */ style comments first (block comments)
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        // Remove -- style comments (line comments) - handle both end-of-line and inline comments
        $sql = preg_replace('/\s*--[^\r\n]*/i', '', $sql);

        // Remove semicolons at the end (ClickHouse will add FORMAT JSON, semicolons cause syntax errors)
        $sql = rtrim($sql, ';');

        // Clean up multiple spaces and newlines, but preserve single spaces
        $sql = preg_replace('/\s+/', ' ', $sql);
        // Fix spacing around commas in GROUP BY and other clauses
        $sql = preg_replace('/\s*,\s*/', ', ', $sql);
        // Remove trailing commas that might result from comment removal
        $sql = preg_replace('/,\s*$/', '', $sql);
        $sql = preg_replace('/,\s*,/', ',', $sql); // Remove double commas

        // Convert MySQL DATE_FORMAT to ClickHouse formatDateTime (if AI mistakenly uses MySQL syntax)
        // DATE_FORMAT(date, '%Y-%m-%d') -> formatDateTime(date, '%Y-%m-%d')
        $sql = preg_replace('/DATE_FORMAT\s*\(/i', 'formatDateTime(', $sql);

// Trim whitespace
$sql = trim($sql);

        // Validate that SQL was generated
        if (empty($sql)) {
            throw new \Exception('Failed to generate SQL query. Please try rephrasing your question or contact support if the issue persists.');
        }

        // Basic validation - check if it looks like SQL
        if (!preg_match('/^\s*(SELECT|WITH|INSERT|UPDATE|DELETE)/i', $sql)) {
            throw new \Exception('Invalid SQL query generated. Please try rephrasing your question.');
        }

        return $sql;
    }
}

