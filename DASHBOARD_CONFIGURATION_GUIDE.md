# Dashboard Configuration Guide

This guide explains how to configure dynamic dashboards using JSON files.

## 📁 Dashboard Files Location

All dashboard configuration files are located in:
```
storage/app/dashboards/
```

## 🎯 Quick Start

### 1. **Edit an Existing Dashboard**

1. Navigate to `storage/app/dashboards/`
2. Open any JSON file (e.g., `membership.json`)
3. Modify widgets, SQL queries, or configurations
4. Save the file
5. Refresh the dashboard page in your browser

### 2. **Create a New Dashboard**

1. Create a new JSON file in `storage/app/dashboards/` (e.g., `my-dashboard.json`)
2. Use the template structure below
3. Access it at: `/dashboards/my-dashboard`

## 📋 Dashboard Structure Template

```json
{
  "title": "Your Dashboard Title",
  "description": "Description of what this dashboard shows",
  "layout": "grid",
  "widgets": [
    // Add widgets here
  ]
}
```

## 🧩 Widget Types & Configuration

### 1. **Metric Widget** - Display Key Numbers

Use this to show important metrics like totals, counts, averages.

```json
{
  "id": "unique_widget_id",
  "title": "Sales Summary",
  "type": "metric",
  "sql": "SELECT SUM(total_amount) as revenue, COUNT(*) as orders, AVG(total_amount) as avg_order FROM invoice_details",
  "config": {
    "metrics": [
      {
        "label": "Total Revenue",
        "field": "revenue",
        "format": "currency"
      },
      {
        "label": "Total Orders",
        "field": "orders",
        "format": "number"
      },
      {
        "label": "Avg Order",
        "field": "avg_order",
        "format": "currency"
      }
    ]
  }
}
```

**Format Options:**
- `"currency"` - Formats as currency (e.g., $1,234.56)
- `"number"` - Formats as number (e.g., 1,234)

**SQL Requirements:**
- Must return a single row
- Field names in SQL must match the `field` names in config

---

### 2. **Table Widget** - Display Data Tables

Use this to show detailed data in a table format.

```json
{
  "id": "customer_table",
  "title": "Top Customers",
  "type": "table",
  "sql": "SELECT customer_name, SUM(total_amount) as total_spent, COUNT(*) as orders FROM invoice_details GROUP BY customer_name ORDER BY total_spent DESC LIMIT 50",
  "config": {
    "columns": ["customer_name", "total_spent", "orders"]
  }
}
```

**Config Options:**
- `columns` - Array of column names to display (must match SQL field names)

**SQL Requirements:**
- Can return multiple rows
- Field names must match the `columns` array

---

### 3. **Bar Chart Widget** - Vertical/Horizontal Bars

Use this for comparing values across categories.

```json
{
  "id": "sales_by_category",
  "title": "Sales by Category",
  "type": "bar",
  "sql": "SELECT category, SUM(total_price) as sales FROM invoice_items_detail GROUP BY category ORDER BY sales DESC LIMIT 10",
  "config": {
    "xAxis": "category",
    "yAxis": "sales"
  }
}
```

**Config Options:**
- `xAxis` - Field name for X-axis (categories)
- `yAxis` - Field name for Y-axis (values)

**SQL Requirements:**
- Must return at least 2 columns
- First column = X-axis labels
- Second column = Y-axis values

---

### 4. **Line Chart Widget** - Trend Lines

Use this to show trends over time.

```json
{
  "id": "revenue_trend",
  "title": "Revenue Trend",
  "type": "line",
  "sql": "SELECT formatDateTime(toStartOfMonth(invoice_date), '%Y-%m') as month, SUM(total_amount) as revenue FROM invoice_details GROUP BY month ORDER BY month DESC LIMIT 12",
  "config": {
    "xAxis": "month",
    "yAxis": "revenue"
  }
}
```

**Config Options:**
- `xAxis` - Field name for X-axis (time periods)
- `yAxis` - Field name for Y-axis (values)
- `secondaryAxis` - (Optional) Field name for second line

**SQL Requirements:**
- Must return at least 2 columns
- X-axis should be time-based (dates, months, etc.)

---

### 5. **Pie Chart Widget** - Proportional Data

Use this to show proportions or distributions.

```json
{
  "id": "payment_methods",
  "title": "Payment Methods",
  "type": "pie",
  "sql": "SELECT payment_method, SUM(amount_paid) as total FROM paymentDetails GROUP BY payment_method",
  "config": {
    "labelField": "payment_method",
    "valueField": "total"
  }
}
```

**Config Options:**
- `labelField` - Field name for pie slice labels
- `valueField` - Field name for pie slice values

**SQL Requirements:**
- Must return at least 2 columns
- Values should be numeric

---

## 📅 Adding Date Filters

To make your dashboard support date filtering:

1. **In your SQL query**, use this pattern:

```sql
SELECT ...
FROM invoice_details
WHERE invoice_date BETWEEN toDate('{start_date}') AND toDate('{end_date}')
```

2. **The dashboard will automatically:**
   - Add date filter inputs at the top
   - Inject selected dates into your SQL queries
   - Apply filters to all widgets

**Example with date filter:**

```json
{
  "id": "filtered_sales",
  "title": "Sales by Date Range",
  "type": "line",
  "sql": "SELECT formatDateTime(toStartOfWeek(invoice_date), '%Y-%m-%d') as week, SUM(total_amount) as revenue FROM invoice_details WHERE invoice_date BETWEEN toDate('{start_date}') AND toDate('{end_date}') GROUP BY week ORDER BY week",
  "config": {
    "xAxis": "week",
    "yAxis": "revenue"
  }
}
```

---

## 🔧 Complete Example: Full Dashboard

Here's a complete dashboard example:

```json
{
  "title": "Sales Performance Dashboard",
  "description": "Overview of sales metrics and trends",
  "layout": "grid",
  "widgets": [
    {
      "id": "summary_metrics",
      "title": "Key Metrics",
      "type": "metric",
      "sql": "SELECT SUM(total_amount) as revenue, COUNT(*) as transactions, AVG(total_amount) as avg_transaction FROM invoice_details",
      "config": {
        "metrics": [
          {"label": "Total Revenue", "field": "revenue", "format": "currency"},
          {"label": "Transactions", "field": "transactions", "format": "number"},
          {"label": "Avg Transaction", "field": "avg_transaction", "format": "currency"}
        ]
      }
    },
    {
      "id": "monthly_trend",
      "title": "Monthly Revenue Trend",
      "type": "line",
      "sql": "SELECT formatDateTime(toStartOfMonth(invoice_date), '%Y-%m') as month, SUM(total_amount) as revenue FROM invoice_details GROUP BY month ORDER BY month DESC LIMIT 12",
      "config": {
        "xAxis": "month",
        "yAxis": "revenue"
      }
    },
    {
      "id": "top_products",
      "title": "Top 10 Products",
      "type": "bar",
      "sql": "SELECT item_name, SUM(total_price) as sales FROM invoice_items_detail WHERE item_type = 'product' GROUP BY item_name ORDER BY sales DESC LIMIT 10",
      "config": {
        "xAxis": "item_name",
        "yAxis": "sales"
      }
    },
    {
      "id": "product_categories",
      "title": "Sales by Category",
      "type": "pie",
      "sql": "SELECT category, SUM(total_price) as sales FROM invoice_items_detail WHERE item_type = 'product' GROUP BY category",
      "config": {
        "labelField": "category",
        "valueField": "sales"
      }
    },
    {
      "id": "recent_transactions",
      "title": "Recent Transactions",
      "type": "table",
      "sql": "SELECT invoice_date, customer_name, total_amount, franchise FROM invoice_details ORDER BY invoice_date DESC LIMIT 50",
      "config": {
        "columns": ["invoice_date", "customer_name", "total_amount", "franchise"]
      }
    }
  ]
}
```

---

## ✅ Best Practices

1. **Test SQL Queries First**
   - Test your SQL in the Reports section before adding to dashboards
   - Ensure queries return expected data

2. **Use Descriptive IDs**
   - Widget IDs should be unique and descriptive
   - Example: `membership_sales_summary` not `widget1`

3. **Optimize Queries**
   - Use `LIMIT` for large datasets
   - Add appropriate `ORDER BY` clauses
   - Use indexes where possible

4. **Field Naming**
   - Use clear, descriptive field names in SQL
   - Match field names exactly in config

5. **Error Handling**
   - If a widget fails, check the SQL syntax
   - Verify field names match between SQL and config
   - Check ClickHouse logs for detailed errors

---

## 🐛 Troubleshooting

### Widget shows "No data available"
- Check if SQL query returns data
- Verify SQL syntax is correct
- Test query in Reports section first

### Chart not rendering
- Ensure SQL returns at least 2 columns
- Verify `xAxis`/`yAxis` field names match SQL column names
- Check that values are numeric for charts

### Date filters not working
- Ensure SQL includes: `WHERE invoice_date BETWEEN toDate('{start_date}') AND toDate('{end_date}')`
- Check date format matches your database format

### Metric widget shows wrong values
- Verify field names in `config.metrics` match SQL column names
- Check format type (currency vs number)

---

## 📝 Quick Reference

| Widget Type | Use For | Required Config |
|------------|---------|----------------|
| `metric` | Key numbers/KPIs | `metrics` array with `label`, `field`, `format` |
| `table` | Detailed data lists | `columns` array |
| `bar` | Comparing categories | `xAxis`, `yAxis` |
| `line` | Trends over time | `xAxis`, `yAxis` |
| `pie` | Proportions | `labelField`, `valueField` |

---

## 🚀 Next Steps

1. **Edit existing dashboards** - Start by modifying widgets in existing dashboards
2. **Create custom dashboards** - Build dashboards specific to your needs
3. **Share configurations** - JSON files can be easily shared and version controlled
4. **Iterate** - Dashboards update automatically when you save JSON files

Need help? Check the example dashboard files in `storage/app/dashboards/` for more patterns!

