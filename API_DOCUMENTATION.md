# BizzAI Analytics REST API Documentation

## Base URL
All API endpoints are prefixed with `/api/v1`

Example: `http://your-domain.com/api/v1/reports/predefined`

## Authentication
Currently, the API is open and does not require authentication. For production use, consider implementing API key authentication.

## Reports API

### 1. Test Connection
**GET** `/api/v1/reports/test`

Test the API connection and database connectivity.

**Response:**
```json
{
  "success": true,
  "message": "API connection successful",
  "data": [{"server_time": "2025-12-29 12:00:00"}]
}
```

### 2. List Predefined Reports
**GET** `/api/v1/reports/predefined`

Get a list of all available predefined reports.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "type": "daily-sales",
      "title": "Daily Sales",
      "description": "Daily sales breakdown by date"
    },
    ...
  ]
}
```

### 3. Get Predefined Report
**GET** `/api/v1/reports/predefined/{type}`

Get data for a specific predefined report.

**Parameters:**
- `type` (path): Report type (`daily-sales`, `top-items`, `revenue-by-franchise`, `payments-by-method`, `refunds`)
- `start_date` (query, optional): Start date in `Y-m-d` format (default: 1 month ago)
- `end_date` (query, optional): End date in `Y-m-d` format (default: today)
- `page` (query, optional): Page number (default: 1)
- `per_page` (query, optional): Items per page (default: 100)

**Example:**
```
GET /api/v1/reports/predefined/daily-sales?start_date=2025-01-01&end_date=2025-12-31&page=1&per_page=50
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "invoice_date_formatted": "01 Jan 2025",
      "daily_sales": 15000.50
    },
    ...
  ],
  "meta": {
    "type": "daily-sales",
    "title": "Daily Sales",
    "start_date": "2025-01-01",
    "end_date": "2025-12-31",
    "pagination": {
      "page": 1,
      "per_page": 50,
      "total": 365,
      "total_pages": 8
    },
    "sql": "SELECT ..."
  }
}
```

### 4. Run Custom SQL Query
**POST** `/api/v1/reports/run`

Execute a custom SQL query.

**Request Body:**
```json
{
  "sql": "SELECT customer_name, SUM(total_amount) as total FROM invoice_details GROUP BY customer_name LIMIT 10",
  "page": 1,
  "per_page": 100,
  "start_date": "2025-01-01",
  "end_date": "2025-12-31"
}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "customer_name": "John Doe",
      "total": 5000.00
    },
    ...
  ],
  "meta": {
    "page": 1,
    "per_page": 100,
    "start_date": "2025-01-01",
    "end_date": "2025-12-31",
    "sql": "SELECT ..."
  }
}
```

### 5. Ask AI Question
**POST** `/api/v1/reports/ask-ai`

Generate and execute a SQL query from a natural language question.

**Request Body:**
```json
{
  "question": "Show me top 10 customers by sales this month",
  "start_date": "2025-12-01",
  "end_date": "2025-12-31"
}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "customer_name": "John Doe",
      "total_sales": 15000.00
    },
    ...
  ],
  "meta": {
    "question": "Show me top 10 customers by sales this month",
    "sql": "SELECT ...",
    "start_date": "2025-12-01",
    "end_date": "2025-12-31"
  }
}
```

### 6. Export Report as CSV
**POST** `/api/v1/reports/export`

Export report data as CSV file.

**Request Body:**
```json
{
  "sql": "SELECT * FROM invoice_details LIMIT 100"
}
```

**Response:** CSV file download

---

## Dashboards API

### 1. List All Dashboards
**GET** `/api/v1/dashboards`

Get a list of all available dashboards.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "type": "employee",
      "title": "Employee Dashboard",
      "route": "dashboards.employee"
    },
    ...
  ],
  "count": 5
}
```

### 2. Get Dashboard Data
**GET** `/api/v1/dashboards/{type}`

Get complete dashboard data with all widgets.

**Parameters:**
- `type` (path): Dashboard type (e.g., `employee`, `financial`, `sales`)
- `period` (query, optional): Time period (`this_month`, `last_month`, `year`, `last_year`, `all_time` - default: `all_time`)
- `start_date` (query, optional): Start date in `Y-m-d` format
- `end_date` (query, optional): End date in `Y-m-d` format
- `franchise` (query, optional): Filter by franchise

**Example:**
```
GET /api/v1/dashboards/employee?period=this_month
```

**Response:**
```json
{
  "success": true,
  "data": {
    "title": "Employee Dashboard",
    "description": "Employee performance metrics",
    "layout": "grid",
    "widgets": [
      {
        "id": "employee_performance",
        "title": "Employee Performance Details",
        "type": "table",
        "config": {
          "columns": ["employee_name", "transactions", "total_sales"]
        },
        "data": [
          {
            "employee_name": "John Doe",
            "transactions": 150,
            "total_sales": 25000.00
          },
          ...
        ],
        "sql": "SELECT ..."
      },
      ...
    ]
  },
  "meta": {
    "type": "employee",
    "filters": {
      "period": "this_month"
    },
    "period": "this_month"
  }
}
```

### 3. Get Financial Dashboard Data
**GET** `/api/v1/dashboards/financial/data`

Get financial dashboard data with revenue breakdowns for multiple time periods.

**Response:**
```json
{
  "success": true,
  "data": {
    "today": {
      "membership": 1000.00,
      "products": 2000.00,
      "training": 1500.00,
      "services": 800.00,
      "giftcards": 500.00,
      "total": 5800.00,
      "trend": {
        "labels": ["Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
        "data": [10000, 12000, 15000, 18000, 20000, 22000]
      },
      "locations": {
        "Warehouse": 2000.00,
        "Transport": 1500.00,
        "Retail": 2300.00
      }
    },
    "yesterday": {...},
    "week_to_date": {...},
    "prev_week": {...},
    "month_to_date": {...},
    "last_month": {...},
    "year_to_date": {...},
    "last_year": {...}
  },
  "meta": {
    "date_ranges": {
      "today": {"start": "2025-12-29", "end": "2025-12-29"},
      ...
    }
  }
}
```

### 4. Get Financial Table View Data
**GET** `/api/v1/dashboards/financial-table/data`

Get financial data in table format with monthly breakdowns.

**Response:**
```json
{
  "success": true,
  "data": {
    "monthly_data": {
      "Jan": {
        "memberships": 10000.00,
        "products": 20000.00,
        "services": 15000.00,
        "training": 12000.00,
        "packages": 8000.00,
        "total": 65000.00
      },
      ...
    },
    "column_totals": {
      "memberships": 120000.00,
      "products": 240000.00,
      "services": 180000.00,
      "training": 144000.00,
      "packages": 96000.00,
      "total": 780000.00
    }
  },
  "meta": {
    "year": 2025,
    "year_start": "2025-01-01",
    "year_end": "2025-12-31"
  }
}
```

---

## Error Responses

All endpoints return errors in the following format:

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (invalid parameters)
- `500` - Internal Server Error

---

## Examples

### JavaScript (Fetch API)
```javascript
// Get daily sales report
fetch('http://your-domain.com/api/v1/reports/predefined/daily-sales?start_date=2025-01-01&end_date=2025-12-31')
  .then(response => response.json())
  .then(data => {
    console.log(data);
  });

// Ask AI question
fetch('http://your-domain.com/api/v1/reports/ask-ai', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    question: 'Show me top 10 customers by sales',
    start_date: '2025-12-01',
    end_date: '2025-12-31'
  })
})
  .then(response => response.json())
  .then(data => {
    console.log(data);
  });
```

### cURL
```bash
# Get dashboard data
curl "http://your-domain.com/api/v1/dashboards/employee?period=this_month"

# Run custom query
curl -X POST "http://your-domain.com/api/v1/reports/run" \
  -H "Content-Type: application/json" \
  -d '{"sql": "SELECT * FROM invoice_details LIMIT 10"}'
```

### PHP
```php
// Get predefined report
$response = file_get_contents('http://your-domain.com/api/v1/reports/predefined/daily-sales');
$data = json_decode($response, true);

// Ask AI question
$ch = curl_init('http://your-domain.com/api/v1/reports/ask-ai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'question' => 'Show me top customers',
    'start_date' => '2025-12-01',
    'end_date' => '2025-12-31'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$data = json_decode($response, true);
```

---

## Notes

1. All dates should be in `Y-m-d` format (e.g., `2025-12-29`)
2. SQL queries should use ClickHouse syntax, not MySQL
3. The API returns paginated results for large datasets
4. All monetary values are returned as floats
5. Date columns are formatted consistently across all endpoints

