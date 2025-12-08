  document.addEventListener('DOMContentLoaded', function() {
    const savedTitle = localStorage.getItem('activeReportTitle');
    if (savedTitle) {
        document.getElementById('active-report-title').textContent = savedTitle;
    }
});
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
    };


    const SQLS = {
      top_spenders: ({
        audience,
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `where invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }

        return `/* Top ${N} ${audience?.replace('_', '-')} customers by spend */ 
SELECT  customer_name, SUM(total_amount) AS total_spent
                 FROM invoice_details ${dateFilter}
                 GROUP BY customer_id, customer_name
                 ORDER BY total_spent DESC
                 LIMIT ${N};`
      },
      all_customers: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `where invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* All customers who purchased in range */
SELECT distinct customer_name as CustomerName
FROM invoice_details
${dateFilter}`
      },
      members: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `SELECT distinct customer_name FROM invoice_details WHERE is_member=1 ${dateFilter}`
      },
      non_members: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `SELECT distinct customer_name FROM invoice_details WHERE is_member=0 ${dateFilter}`
      },
      non_member_savings: ({
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        let dateFilter1 = '';
        if (start && end) {
          dateFilter = `AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
          dateFilter1 = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* Potential savings for top non-members (assumes :discount_pct and :membership_price) */
WITH ranked AS (
  SELECT customer_id, sum(total) AS spend
  FROM invoice_details
  WHERE is_member_snapshot=0 ${dateFilter}
  GROUP BY customer_id
  ORDER BY spend DESC
  LIMIT ${N}
), eligible AS (
  SELECT o.customer_name, sum(oi.total_price) AS eligible_total
  FROM invoice_details o
  INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
  WHERE o.customer_id IN (SELECT customer_id FROM ranked)
    ${dateFilter1}
    AND oi.item_type IN ('product','service','class')
  GROUP BY o.customer_id
)
SELECT customer_name, customer_id,
       eligible_total,
       round(eligible_total * :discount_pct, 2) AS discount_value,
       :membership_price AS membership_cost,
       round(eligible_total * :discount_pct - :membership_price, 2) AS net_savings
FROM eligible
ORDER BY net_savings DESC;`
      },
      product_performance: ({
        start,
        end,
        N
      }) => `/* Top ${N} products spend by their top spending customers*/
WITH top_customers AS (
    SELECT customer_id, customer_name, SUM(total_amount) AS total_spent
    FROM invoice_details
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
    GROUP BY tc.customer_id, tc.customer_name, iid.item_name
) select 	tc.customer_name AS customer, rp.item_name AS ITEM, sum(rp.total_spent) AS total_spent 
 from ranked_products rp where rn<=${N} GROUP BY customer, ITEM , rn ORDER BY total_spent DESC,  rn ASC;`,

      repeat_behavior: ({
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `WHERE o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        } else {
          dateFilter = `WHERE o.invoice_date BETWEEN toDate('2023-01-01') AND toDate('2025-09-01')`;
        }
        return `/* Customers with >1 instance of same item/category in period */
SELECT customer_id, item_type, anyHeavy(item_name) AS sample_item, count() AS occurrences
FROM invoice_details o
INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
${dateFilter}
GROUP BY customer_id, item_type, item_name
HAVING occurrences > 1
ORDER BY occurrences DESC
LIMIT ${N};`
      },
      svc_top_new: ({
        start,
        end,
        N
      }) => {
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
   ${dateFilter} -- Filter first purchase date
GROUP BY service_name
ORDER BY new_customers DESC
LIMIT ${N};`
      },
      svc_first_service_customers: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND o.invoice_date
BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* Customers whose first purchase was a service */
WITH first_dt AS (
  SELECT customer_id, min(invoice_date) AS first_date FROM invoice_details GROUP BY customer_id
)
SELECT distinct o.customer_name as CustomerName , oi.item_name as Service , DATE_FORMAT(ft.first_date, '%d %b %Y') as First_Purchase_Date
FROM first_dt ft
INNER JOIN invoice_details o ON o.customer_id=ft.customer_id 
AND o.invoice_date=ft.first_date
INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='service' `
      },
      range_busiest_month: ({
        start,
        end,
        audience
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = ` AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* Range busiest month by distinct customers */
SELECT formatDateTime(invoice_date, '%Y-%m') AS yyyymm, countDistinct(customer_id) AS customers
FROM invoice_details o INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='service' AND oi.category='Gun Ranges & Instruction'
 ${dateFilter}
  ${audience==='members' ? 'AND is_member_snapshot=1' : audience==='non_members' ? 'AND is_member_snapshot=0' : ''}
GROUP BY yyyymm
ORDER BY customers DESC
LIMIT 1;`
      },
      range_busiest_dow: ({
        audience,
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `  AND invoice_date >= addMonths(${start}, -12) AND invoice_date <= addMonths(${end}), -12)`;
        }
        return `/* Busiest day of week (avg last 12 months) */
SELECT formatDateTime(invoice_date, '%d') AS dow,
       countDistinct(customer_id) / countDistinct(toDate(invoice_date)) AS avg_customers
FROM invoice_details o INNER JOIN invoice_items_detail oi ON oi.invoice_id=o.id
WHERE oi.item_type='service' AND oi.category='Gun Ranges & Instruction'
 ${dateFilter}
  ${audience==='members' ? 'AND is_member_snapshot=1' : audience==='non_members' ? 'AND is_member_snapshot=0' : ''}
GROUP BY dow
ORDER BY avg_customers DESC`
      },
      cls_popular: ({
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        } else {
          dateFilter = `AND o.invoice_date BETWEEN toDate('2023-01-01') AND toDate('2025-09-01')`;
        }
        return `/* Most popular classes */
SELECT oi.item_name AS class_name, sum(oi.quantity) AS seats_sold, countDistinct(o.id) AS invoice_Count
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='class' ${dateFilter}
GROUP BY class_name
ORDER BY seats_sold DESC
LIMIT ${N};`
      },
      cls_new_customers: ({
        start,
        end,
        N
      }) => {
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
LIMIT ${N};`
      },
      cls_top_spenders: ({
        start,
        end
      }) => {
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
LIMIT 100;`
      },
      prd_top_sold: ({
        start,
        end,
        N,
        audience
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* Top products sold with sample exclusions (:exclude_skus, :exclude_categories) */
SELECT oi.item_name, sum(oi.quantity) AS units, sum(oi.total_price) AS revenue
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='product' ${dateFilter}
  ${audience==='members' ? 'AND is_member_snapshot=1' : audience==='non_members' ? 'AND is_member_snapshot=0' : ''}
  AND oi.SKU NOT IN ('')
AND oi.category NOT IN ('')
GROUP BY oi.item_name
ORDER BY units DESC
LIMIT ${N};`
      },
      prd_turnover: ({
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* High turnover products (line count) */
SELECT oi.item_name, count() AS lines, sum(oi.quantity) AS units
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='product' ${dateFilter}
GROUP BY oi.item_name
ORDER BY lines DESC
LIMIT ${N};`
      },
      prd_least: ({
        start,
        end,
        N
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND o.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        return `/* Least purchased products by units */
SELECT oi.item_name, sum(oi.quantity) AS units
FROM invoice_items_detail oi INNER JOIN invoice_details o ON o.id=oi.invoice_id
WHERE oi.item_type='product' 
GROUP BY oi.item_name
ORDER BY units ASC
LIMIT ${N};`
      },
      prd_slow_movers: ({
        N
      }) => `/* Slow movers (inventory-based) */
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
LIMIT ${N};

`,
      cln_sale_report_dly: () => `/* Clean Sales Report - Daily */
    SELECT 
    DATE_FORMAT(id.invoice_date, '%d %b %Y'),
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
GROUP BY id.invoice_date, iid.item_name
ORDER BY id.invoice_date DESC, total_sales DESC`,
      cln_sale_report_wkly: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        } else {
          dateFilter = `AND id.invoice_date BETWEEN '2001-01-01' AND '2024-12-31'`;
        }
        return `/* Clean Sales Report - Weekly */
SELECT 
    DATE_FORMAT(toStartOfWeek(id.invoice_date), '%d %b %Y') AS week_start,
    DATE_FORMAT(toStartOfWeek(id.invoice_date) + INTERVAL 6 DAY, '%d %b %Y') AS week_end,
    iid.item_name,
    SUM(iid.total_price) AS total_sales,
    COUNT(DISTINCT id.id) AS invoice_count
FROM invoice_details AS id
INNER JOIN invoice_items_detail AS iid 
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
    ${dateFilter}
GROUP BY 
    toStartOfWeek(id.invoice_date),  -- use the raw expression, not alias
    iid.item_name
ORDER BY 
    toStartOfWeek(id.invoice_date) desc, 
    total_sales DESC;`
      },
      cln_sale_report_mnthly: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = ` AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        // else{
        //   dateFilter=` AND invoice_date BETWEEN toDate('2023-01-01') AND toDate('2024-12-31')`
        // }
        return `/* Clean Sales Report - Monthly */
        SELECT
    formatDateTime(toStartOfMonth(invoice_date), '%M %Y') AS month_name,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
 ${dateFilter}
GROUP BY  id.invoice_date,iid.item_name
ORDER BY id.invoice_date   ASC, total_sales DESC
`
      },
      sale_by_category: ({
        start,
        end
      }) => {
        return `
  SELECT
    iid.category,
    iid.item_name,
    COUNT(DISTINCT id.id) as invoice_count,
    sum(iid.quantity) as qty_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
GROUP BY iid.category, iid.item_name
ORDER BY iid.category, total_sales DESC`
      },
      sale_by_subCategory: () => {
        return `
  SELECT
iid.item_name,
    iid.subcategory,
    COUNT(DISTINCT id.id) as invoice_count,
    COUNT(iid.quantity ) as item_count,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
GROUP BY  iid.item_name, iid.subcategory
ORDER BY iid.subcategory, total_sales DESC`
      },
      Trans_count_products: ({
        start,
        end
      }) => {
        return `
SELECT
    iid.item_name,
    COUNT(DISTINCT id.id) as transaction_count,
    sum(iid.quantity) as Quantity_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'product'
GROUP BY iid.item_name
ORDER BY transaction_count DESC`
      },
      cln_sale_mem_report_dly: () => `/* Clean Sales Report - Daily */
SELECT 
    id.invoice_date,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
GROUP BY id.invoice_date, iid.item_name
ORDER BY id.invoice_date DESC, total_sales DESC`,
      cln_sale_mem_report_wkly: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = `AND id.invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        } else {
          dateFilter = `AND id.invoice_date BETWEEN '2001-01-01' AND '2024-12-31'`;
        }
        return `/* Clean Sales Report - Weekly */
SELECT 
toStartOfWeek(id.invoice_date) AS week_start,
    toStartOfWeek(id.invoice_date) + 6 AS week_end,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid 
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
    ${dateFilter}
GROUP BY week_start,iid.item_name
ORDER BY week_start Asc, total_sales DESC`
      },
      cln_sale_mem_report_mnthly: ({
        start,
        end
      }) => {
        let dateFilter = '';
        if (start && end) {
          dateFilter = ` AND invoice_date BETWEEN toDate('${start}') AND toDate('${end}')`;
        }
        // else{
        //   dateFilter=` AND invoice_date BETWEEN toDate('2023-01-01') AND toDate('2024-12-31')`
        // }
        return `/* Clean Sales Report - Monthly */
        SELECT
    formatDateTime(toStartOfMonth(invoice_date), '%M %Y') AS month_name,
    iid.item_name,
    SUM(iid.total_price) as total_sales,
    COUNT(DISTINCT id.id) as invoice_count
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
 ${dateFilter}
GROUP BY  id.invoice_date,iid.item_name
ORDER BY id.invoice_date   ASC, total_sales DESC
`
      },
      mem_sale_by_category: ({
        start,
        end
      }) => {
        return `
  SELECT
    iid.category,
    TRIM(iid.item_name) as item_name,
    COUNT(DISTINCT id.id) as invoice_count,
    sum(iid.quantity) as qty_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
GROUP BY iid.category,  TRIM(iid.item_name)
ORDER BY iid.category, total_sales DESC`
      },
      mem_Trans_count: ({
        start,
        end
      }) => {
        return `
SELECT
   TRIM(iid.item_name) as item_name,
    COUNT(DISTINCT id.id) as transaction_count,
    sum(iid.quantity) as times_sold,
    SUM(iid.total_price) as total_sales
FROM invoice_details id
INNER JOIN invoice_items_detail iid
    ON id.id = iid.invoice_id
WHERE iid.item_type = 'membership'
GROUP BY TRIM(iid.item_name)
ORDER BY transaction_count DESC`
      }
    };


    lucide.createIcons();

// Get data from server (passed from Laravel Blade)
const serverData = @json($results ?? []);
const serverReportKey = @json($reportKey ?? null);
const serverSql = @json($sql ?? null);
let currentData = serverData || [];
let currentChart = null;
let currentChartType = 'pie';
let currentReportKey = serverReportKey || null;
let selectedQuery = '';

// Pagination variables
let currentPage = 1;
let rowsPerPage = 10;
let allTableData = [];

// Format number helper
function formatNumber(value, decimals = 0) {
  if (typeof value !== 'number') return value;
  return value.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
}

// Render pagination controls
function renderPagination(totalRows) {
  const totalPages = Math.ceil(totalRows / rowsPerPage);
  const paginationContainer = document.getElementById('paginationControls');
  
  if (!paginationContainer || totalPages <= 1) {
    if (paginationContainer) paginationContainer.innerHTML = '';
    return;
  }

  // Calculate display range
  const startRow = (currentPage - 1) * rowsPerPage + 1;
  const endRow = Math.min(currentPage * rowsPerPage, totalRows);

  // Main container
  let paginationHTML = `
    <div class=" p-3 mt-4 flex justify-between items-center text-sm text-gray-700 border-t pt-3">
      
      <!-- Previous Button -->
      <button onclick="changePage(${currentPage - 1})"
        class="px-3 py-1.5 bg-gray-300 rounded disabled:opacity-50 hover:bg-gray-400 transition"
        ${currentPage === 1 ? 'disabled' : ''}>
        ← Previous
      </button>

      <!-- Page Info + Per Page Selector -->
      <div class="flex items-center space-x-3">
        <span>Page ${currentPage} of ${totalPages}</span>
        <select id="perPageSelect" class="border rounded px-2 py-1 text-sm">
          <option value="10" ${rowsPerPage === 10 ? 'selected' : ''}>10</option>
          <option value="25" ${rowsPerPage === 25 ? 'selected' : ''}>25</option>
          <option value="50" ${rowsPerPage === 50 ? 'selected' : ''}>50</option>
          <option value="100" ${rowsPerPage === 100 ? 'selected' : ''}>100</option>
        </select>
        <span class="text-gray-500">rows per page</span>
      </div>

      <!-- Next Button -->
      <button onclick="changePage(${currentPage + 1})"
        class="px-3 py-1.5 bg-gray-300 rounded disabled:opacity-50 hover:bg-gray-400 transition"
        ${currentPage === totalPages ? 'disabled' : ''}>
        Next →
      </button>
    </div>

    <div class="text-xs text-gray-500 mt-2 text-center">
      Showing ${startRow}-${endRow} of ${totalRows} rows
    </div>
  `;

  paginationContainer.innerHTML = paginationHTML;

  // Handle rows-per-page changes
  document.getElementById('perPageSelect').addEventListener('change', e => {
    const newPerPage = parseInt(e.target.value);
    rowsPerPage = newPerPage;
    currentPage = 1; // Reset to first page
    renderTable(allTableData);
  });
}

// Global function for changing pages
window.changePage = function (page) {
  const totalPages = Math.ceil(allTableData.length / rowsPerPage);
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  renderTable(allTableData);
};


// Change page function (global scope for onclick)
window.changePage = function(page) {
  const totalPages = Math.ceil(allTableData.length / rowsPerPage);
  if (page < 1 || page > totalPages) return;
  
  currentPage = page;
  renderTable(allTableData);
}

// Render table with pagination and alternating row colors
function renderTable(data) {
  if (!data || data.length === 0) {
    document.getElementById('tableBody').innerHTML = '<tr><td colspan="100" class="text-center text-slate-500 py-8">No data available</td></tr>';
    document.getElementById('tableHead').innerHTML = '';
    if (document.getElementById('rowsCount')) {
      document.getElementById('rowsCount').textContent = '';
    }
    const paginationContainer = document.getElementById('paginationControls');
    if (paginationContainer) paginationContainer.innerHTML = '';
    return;
  }

  // Store all data for pagination
  allTableData = data;
  
  const headers = Object.keys(data[0]);

  // Render table header
  const headerHTML = headers.map(col =>
    `<th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider border border-gray-300 bg-gray-700 text-white">${col.replace(/_/g, ' ')}</th>`
  ).join('');
  document.getElementById('tableHead').innerHTML = `<tr>${headerHTML}</tr>`;

  // Calculate pagination
  const startIndex = (currentPage - 1) * rowsPerPage;
  const endIndex = Math.min(startIndex + rowsPerPage, data.length);
  const paginatedData = data.slice(startIndex, endIndex);

  // Render table body with alternating row colors
  const rowsHTML = paginatedData.map((row, index) => {
    const rowClass = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
    const cells = headers.map(key => {
      let val = row[key];
      if (typeof val === 'number') {
        val = formatNumber(val, val % 1 ? 2 : 0);
      }
      return `<td class="px-4 py-3 text-sm border border-gray-300 text-gray-700">${val ?? ''}</td>`;
    }).join('');
    return `<tr class="hover:bg-gray-100 ${rowClass}">${cells}</tr>`;
  }).join('');

  document.getElementById('tableBody').innerHTML = rowsHTML;
  
  if (document.getElementById('rowsCount'))
    document.getElementById('rowsCount').textContent = `${data.length} rows`;
  
  // Render pagination
  renderPagination(data.length);
}

// Render chart - IMPROVED to handle AI queries
function renderChart(data, reportKey, chartType = 'pie') {
  console.log("chartdata", data);
  if (!data || data.length === 0) {
    showChartMessage('No data available to create chart.');
    return;
  }

  const canvas = document.getElementById('dataChart');
  if (!canvas) {
    console.warn('Chart canvas not found.');
    return;
  }

  const ctx = canvas.getContext('2d');

  if (currentChart) {
    currentChart.destroy();
  }

  let labels = [];
  let values = [];
  let chartTitle = '';
  let valueLabel = '';

  // --- GENERIC CHART LOGIC for AI queries ---
  const keys = Object.keys(data[0]);
  if (keys.length >= 2) {
    labels = data.map(row => row[keys[0]]);
    values = data.map(row => {
      const val = row[keys[1]];
      return typeof val === 'number' ? val : 0;
    });
    valueLabel = keys[1].replace(/_/g, ' ');
  } else {
    showChartMessage('Not enough columns to create a chart.');
    return;
  }

  // --- If all values are zero or invalid, also skip ---
  if (values.every(v => v === 0)) {
    showChartMessage('Chart cannot be created because all values are zero or invalid.');
    return;
  }

  // --- Chart colors and config ---
  const colors = ['#dc2626', '#ea580c', '#d97706', '#ca8a04', '#65a30d', '#16a34a', '#059669', '#0891b2'];

  const chartConfig = {
    type: chartType,
    data: {
      labels,
      datasets: [{
        label: valueLabel,
        data: values,
        backgroundColor: chartType === 'line' ? 'rgba(220, 38, 38, 0.1)' : colors,
        borderColor: chartType === 'line' ? '#dc2626' : '#ffffff',
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
        },
        title: {
          display: true,
          text: chartTitle,
          font: { size: 16, weight: 'bold' },
          padding: 20
        }
      },
      scales: chartType !== 'pie' ? {
        y: { beginAtZero: true }
      } : undefined
    }
  };

  currentChart = new Chart(ctx, chartConfig);
  currentChartType = chartType;
}

// Helper function to show fallback message
function showChartMessage(message) {
  const container = document.getElementById('chartContainer') || document.body;
  const msgElement = document.createElement('div');
  msgElement.textContent = message;
  msgElement.style.textAlign = 'center';
  msgElement.style.color = '#6b7280';
  msgElement.style.padding = '2rem';
  msgElement.style.fontStyle = 'italic';
  msgElement.style.fontSize = '1rem';

  const canvas = document.getElementById('dataChart');
  if (canvas && canvas.parentNode) {
    canvas.parentNode.replaceChild(msgElement, canvas);
  } else {
    container.appendChild(msgElement);
  }
}


// Load report - submit SQL to backend
function loadReport(reportKey) {
  currentReportKey = reportKey;
  
  // Store report key in sessionStorage to persist across page reload
  sessionStorage.setItem('currentReportKey', reportKey);

  const reportTitle = TITLES[reportKey] || 'Report';
  document.getElementById('active-report-title').textContent = reportTitle;
  localStorage.setItem('activeReportTitle', reportTitle);

  // Generate SQL for this report
  const audience = 'all';
  const N = 100;
  const start = null;
  const end = null;
  const fn = SQLS[reportKey];

const AvoidAlert = ['refunds', 'payments-by-method', 'revenue-by-franchise', 'top-items', 'daily-sales'];

          if (!fn && !AvoidAlert.includes(Key)) {
            alert('SQL template not available for this report');
            return;
          }

  const sql = fn({
    audience,
    N,
    start,
    end
  });

  // Submit form to backend to execute SQL
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/reports/run';


  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (csrfToken) {
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken.content;
    form.appendChild(csrfInput);
  }

  const sqlInput = document.createElement('input');
  sqlInput.type = 'hidden';
  sqlInput.name = 'sql';
  sqlInput.value = sql;
  form.appendChild(sqlInput);

  const reportKeyInput = document.createElement('input');
  reportKeyInput.type = 'hidden';
  reportKeyInput.name = 'report_key';
  reportKeyInput.value = reportKey;
  form.appendChild(reportKeyInput);

  document.body.appendChild(form);
  form.submit();
  setTimeout(() => hidePageLoader(), 10000);
}

// Search functionality
document.getElementById('report-search').addEventListener('input', (e) => {
  const searchTerm = e.target.value.toLowerCase().trim();

  const sectionHeaders = document.querySelectorAll('.section-header');
  const reportButtons = document.querySelectorAll('.rep-btn');

  // 🔹 If search term is empty → show section headers again
  if (searchTerm === '') {
    sectionHeaders.forEach(el => el.style.display = '');
  } else {
    sectionHeaders.forEach(el => el.style.display = 'none');
  }

  // 🔹 Filter report buttons
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


// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('border-red-600', 'text-red-600', 'active');
      b.classList.add('border-transparent', 'text-gray-600');
    });

    this.classList.remove('border-transparent', 'text-gray-600');
    this.classList.add('border-red-600', 'text-red-600', 'active');

    document.querySelectorAll('.tab-content').forEach(content => {
      content.classList.add('hidden');
    });

    document.getElementById('tab-' + this.dataset.tab).classList.remove('hidden');
  });
});

// Report buttons
document.querySelectorAll('.rep-btn').forEach(btn => {
  btn.addEventListener('click', function() {
      event.preventDefault();
    loadReport(this.dataset.report);
  });
});

// Chart type switching
document.querySelectorAll('.chart-type-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.chart-type-btn').forEach(b => {
      b.classList.remove('active', 'bg-gray-200');
      b.classList.add('bg-gray-100');
    });

    this.classList.add('active', 'bg-gray-200');
    this.classList.remove('bg-gray-100');

    if (currentData.length > 0) {
      renderChart(currentData, currentReportKey, this.dataset.chartType);
    }
  });
});

// Query chip buttons - auto-fill and submit form
document.querySelectorAll('.chip').forEach(chip => {
  chip.addEventListener('click', function() {
    const query = this.textContent.trim();
    const chatInput = document.getElementById('chatInput');
    const chatForm = document.getElementById('chatForm');
    
    if (chatInput && chatForm) {
      chatInput.value = query;
      chatForm.submit();
    }
  });
});

// Popular query pills - FIXED
let selectedPopularQuery = '';
document.querySelectorAll('.popular-query-pill').forEach(pill => {
  pill.addEventListener('click', function() {
    document.querySelectorAll('.popular-query-pill').forEach(p => {
      p.classList.remove('bg-gray-800', 'text-white');
      p.classList.add('bg-gray-100', 'text-gray-700');
    });

    this.classList.remove('bg-gray-100', 'text-gray-700');
    this.classList.add('bg-gray-800', 'text-white');
    selectedPopularQuery = this.dataset.query;
    
    // Auto-fill the chat input
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
      chatInput.value = selectedPopularQuery;
    }
  });
});

// Remove the runPopularQuery button listener if it exists
const runPopularBtn = document.getElementById('runPopularQuery');
if (runPopularBtn) {
  runPopularBtn.addEventListener('click', function() {
    if (!selectedPopularQuery) {
      alert('Please select a query first');
      return;
    }
    const chatInput = document.getElementById('chatInput');
    const chatForm = document.getElementById('chatForm');
    if (chatInput && chatForm) {
      chatInput.value = selectedPopularQuery;
      chatForm.submit();
    }
  });
}

// Last query pills - FIXED
let selectedLastQuery = '';
document.querySelectorAll('.last-query-pill').forEach(pill => {
  pill.addEventListener('click', function() {
    document.querySelectorAll('.last-query-pill').forEach(p => {
      p.classList.remove('bg-gray-800', 'text-white');
      p.classList.add('bg-gray-100', 'text-gray-700');
    });

    this.classList.remove('bg-gray-100', 'text-gray-700');
    this.classList.add('bg-gray-800', 'text-white');
    selectedLastQuery = this.dataset.query;
    
    // Auto-fill the chat input
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
      chatInput.value = selectedLastQuery;
    }
  });
});

// Remove the runLastQuery button listener if it exists
const runLastBtn = document.getElementById('runLastQuery');
if (runLastBtn) {
  runLastBtn.addEventListener('click', function() {
    if (!selectedLastQuery) {
      alert('Please select a query first');
      return;
    }
    const chatInput = document.getElementById('chatInput');
    const chatForm = document.getElementById('chatForm');
    if (chatInput && chatForm) {
      chatInput.value = selectedLastQuery;
      chatForm.submit();
    }
  });
}

function showPageLoader() {

  const loader = document.createElement('div');
  loader.id = 'page-loader';
  loader.innerHTML = `
    <div style="
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, 0.7);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
    ">
      <div style="
        border: 4px solid #e5e7eb;
        border-top: 4px solid #16a34a;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        animation: spin 1s linear infinite;
      "></div>
    </div>
  `;

  // Add CSS animation keyframes if not already added
  if (!document.getElementById('loader-style')) {
    const style = document.createElement('style');
    style.id = 'loader-style';
    style.textContent = `
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  }

  document.body.appendChild(loader);
}

function hidePageLoader() {
  const loader = document.getElementById('page-loader');
  if (loader) loader.remove();
}
document.getElementById('chatForm').addEventListener('submit', function () {
  showPageLoader(); // 🔹 Show your existing page loader
});


// Initialize page with server data if available
if (serverData && serverData.length > 0) {
  currentData = serverData;
  renderTable(serverData);
  // Render chart for any data (not just specific report keys)
  renderChart(serverData, serverReportKey, currentChartType);
}
  function exportTableToCSV(filename) {
    const rows = document.querySelectorAll("#dataTable tr");
    if (!rows.length) return alert("No data to export.");
    const csv = [];
    rows.forEach(row => {
      const cols = row.querySelectorAll("th, td");
      const rowData = Array.from(cols)
        .map(col => `"${col.innerText.replace(/"/g, '""')}"`)
        .join(",");
      csv.push(rowData);
    });
    const blob = new Blob([csv.join("\n")], { type: "text/csv" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
  }
  // Toggle SQL visibility
    $('#toggleSql').addEventListener('click', () => {
      const container = $('#sqlContainer');
      const icon = $('#toggleSql i');
      
      if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        icon.setAttribute('data-lucide', 'eye-off');
      } else {
        container.classList.add('hidden');
        icon.setAttribute('data-lucide', 'eye');
      }
    });


  // Bind export buttons
  document.getElementById("exportCSV").addEventListener("click", () => exportTableToCSV("report.csv"));
  
