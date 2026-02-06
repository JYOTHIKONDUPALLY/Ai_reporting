<?php
// Include API helper if not already included
if (!class_exists('Service_AnalyticService')) {
    require_once __DIR__ . '/api-helper.php';
}

// Get parameters
$reportType = isset($_GET['type']) ? $_GET['type'] : 'daily-sales';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 100;

// Fetch predefined reports list
$reportsList = Service_AnalyticService::apiRequest('reports/predefined');
$availableReports = isset($reportsList['data']) ? $reportsList['data'] : [];

// Fetch current report data
$reportData = null;
$reportTitle = 'Select a report';
$error = null;

if ($reportType && $reportType !== '') {
    $endpoint = 'reports/predefined/' . urlencode($reportType) . '?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '&page=' . $page . '&per_page=' . $perPage;
    $response = Service_AnalyticService::apiRequest($endpoint);

    if (isset($response['success']) && $response['success']) {
        $reportData = $response;
        if (isset($response['meta']['title'])) {
            $reportTitle = $response['meta']['title'];
        }
    } else {
        $error = isset($response['error']) ? $response['error'] : 'Failed to load report';
        // Store full error response for debugging
        $errorDetails = $response;
    }
}
?>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<style>
    :root {
        --brand-primary: #33a1df;
        --brand-primary-rgb: 51, 161, 223;
    }

    .brand-primary {
        color: var(--brand-primary);
    }

    .bg-brand-primary {
        background-color: var(--brand-primary);
    }

    .border-brand-primary {
        border-color: var(--brand-primary);
    }

    .chart-container {
        position: relative;
        height: 400px;
        padding: 20px;
    }

    body {
        background-color: #f5f5f5;
    }

    .container-custom {
        max-width: 1280px;
        margin: 0 auto;
    }

    .header-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .header-nav>div {
        display: flex;
        align-items: center;
    }

    .logo-container {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logo-container img {
        width: 24px;
        height: 24px;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .nav-links a {
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 4px;
    }

    .nav-links a.active {
        background-color: #e9ecef;
        color: #212529;
    }

    .nav-links a:hover {
        background-color: #e9ecef;
        text-decoration: none;
    }

    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        border-bottom: 1px solid #dee2e6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .sidebar-results {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        padding: 16px;
        overflow-y: auto;
        max-height: calc(100vh - 120px);
    }

    .sidebar-results-title {
        text-transform: uppercase;
        font-size: 12px;
        margin-bottom: 16px;
        font-weight: 600;
        color: #6c757d;
    }

    .sidebar-results ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-results ul li {
        margin-bottom: 4px;
    }

    .sidebar-results ul li a {
        display: block;
        width: 100%;
        text-align: left;
        padding: 8px 12px;
        border-radius: 4px;
        text-decoration: none;
        color: #212529;
    }

    .sidebar-results ul li a:hover {
        background-color: #e9ecef;
    }

    .sidebar-results ul li a.active {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .report-header {
        background-color: var(--brand-primary);
        color: white;
        padding: 12px 24px;
        border-radius: 8px 8px 0 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .report-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .report-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-left: auto;
    }

    .date-form {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .date-form input[type="date"] {
        padding: 6px 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }

    .date-form button {
        padding: 6px 16px;
        background: white;
        color: #212529;
        border: none;
        border-radius: 4px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-form button:hover {
        background: #f8f9fa;
    }

    .table-container {
        background: white;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .table-container table {
        margin-bottom: 0;
    }

    .table-container table thead {
        background-color: #f8f9fa;
    }

    .table-container table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .table-container table tbody tr:hover {
        background-color: #e9ecef;
    }

    .pagination-container {
        padding: 16px 24px;
        border-top: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pagination-buttons {
        display: flex;
        gap: 8px;
    }

    .pagination-buttons a {
        padding: 8px 16px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
        color: #212529;
        font-size: 14px;
    }

    .pagination-buttons a:hover {
        background-color: #f8f9fa;
    }

    .chart-section {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        padding: 24px;
        margin-top: 16px;
    }

    .alert-custom {
        border-left: 4px solid;
    }

    .ai-chat-section {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        padding: 24px;
        margin-top: 16px;
    }

    .ai-chat-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }

    .ai-chat-form {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }

    .ai-chat-input {
        flex: 1;
        padding: 8px 16px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        font-size: 14px;
    }

    .ai-chat-button {
        padding: 8px 24px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }

    .ai-chat-button:hover {
        background: #218838;
    }

    .ai-chat-button:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }

    .ai-suggestions {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 12px;
    }

    .ai-suggestion-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .ai-suggestion-chip {
        padding: 6px 12px;
        background: #f8f9fa;
        color: #212529;
        border-radius: 16px;
        font-size: 12px;
        cursor: pointer;
        border: 1px solid #dee2e6;
    }

    .ai-suggestion-chip:hover {
        background: #e9ecef;
    }
</style>

<div style="padding: 16px;">
    <div class="container-custom">
        <div class="row">
            <!-- sidebar-results -->
            <aside class="col-md-2 col-sm-12 sidebar-results">
                <div class="sidebar-results-title">Predefined Reports</div>
                <ul>
                    <?php foreach ($availableReports as $report): ?>
                        <li>
                            <a href="/business/analytics/dashboard?type=<?php echo htmlspecialchars($report['type']); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                                class="rep-btn <?php echo ($reportType === $report['type']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($report['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="col-md-10 col-sm-12" style="padding: 0 16px;">
                <div class="container-custom" style="max-width: 100%;">
                    <!-- Header -->
                    <div class="report-header">
                        <div class="report-header-left">
                            <i data-lucide="database" style="width: 20px; height: 20px;"></i>
                            <span id="active-report-title"
                                style="font-weight: 600;"><?php echo htmlspecialchars($reportTitle); ?></span>
                        </div>
                        <div class="report-header-right">
                            <label
                                style="font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                <span>Date Range:</span>
                            </label>
                            <form method="GET" class="date-form">
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                                <input type="date" name="start_date"
                                    value="<?php echo htmlspecialchars($startDate); ?>">
                                <span>to</span>
                                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                                <button type="submit">
                                    <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
                                    Apply
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Error Display -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-custom" style="margin-top: 16px;">
                            <div style="display: flex; align-items: flex-start;">
                                <div style="flex-shrink: 0; margin-right: 12px;">
                                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; color: #dc3545;"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4
                                        style="font-size: 14px; font-weight: 500; color: #721c24; margin-top: 0; margin-bottom: 8px;">
                                        Error</h4>
                                    <div style="font-size: 14px; color: #721c24;">
                                        <p style="font-weight: 600; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($error); ?></p>
                                        <?php
                                        $errorInfo = isset($errorDetails) ? $errorDetails : (isset($reportsList['success']) && !$reportsList['success'] ? $reportsList : null);
                                        if ($errorInfo && (isset($errorInfo['url']) || isset($errorInfo['response_preview']))):
                                            ?>
                                            <div
                                                style="margin-top: 12px; padding: 12px; background-color: #f8d7da; border-radius: 4px; font-size: 12px;">
                                                <p style="font-weight: 600; margin-bottom: 4px;">Debug Information:</p>
                                                <?php if (isset($errorInfo['url'])): ?>
                                                    <p style="margin-bottom: 4px;"><strong>URL:</strong> <code
                                                            style="background-color: #f5c6cb; padding: 2px 4px; border-radius: 2px; word-break: break-all;"><?php echo htmlspecialchars($errorInfo['url']); ?></code>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (isset($errorInfo['http_code'])): ?>
                                                    <p style="margin-bottom: 4px;"><strong>HTTP Code:</strong>
                                                        <?php echo htmlspecialchars($errorInfo['http_code']); ?></p>
                                                <?php endif; ?>
                                                <?php if (isset($errorInfo['content_type'])): ?>
                                                    <p style="margin-bottom: 4px;"><strong>Content-Type:</strong>
                                                        <?php echo htmlspecialchars($errorInfo['content_type']); ?></p>
                                                <?php endif; ?>
                                                <?php if (isset($errorInfo['response_preview'])): ?>
                                                    <p style="margin-top: 8px; margin-bottom: 4px;"><strong>Response
                                                            Preview:</strong></p>
                                                    <pre
                                                        style="margin-top: 4px; padding: 8px; background-color: #f5c6cb; border-radius: 2px; overflow: auto; max-height: 160px; font-size: 12px; white-space: pre-wrap;"><?php echo htmlspecialchars($errorInfo['response_preview']); ?></pre>
                                                <?php endif; ?>
                                                <?php if (isset($errorInfo['json_error_code'])): ?>
                                                    <p style="margin-bottom: 4px;"><strong>JSON Error Code:</strong>
                                                        <?php echo htmlspecialchars($errorInfo['json_error_code']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Table View -->
                    <div class="table-container">
                        <div style="overflow-x: auto;">
                            <table id="dataTable" class="table table-bordered" style="font-size: 14px;">
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
                                            href="/business/analytics/dashboard?type=<?php echo htmlspecialchars($reportType); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>&page=<?php echo $pagination['page'] - 1; ?>">Previous</a>
                                    <?php endif; ?>
                                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                                        <a
                                            href="/business/analytics/dashboard?type=<?php echo htmlspecialchars($reportType); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>&page=<?php echo $pagination['page'] + 1; ?>">Next</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Chart View -->
                    <div class="chart-section">
                        <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Chart View</h3>
                        <div class="chart-container">
                            <canvas id="chartCanvas"></canvas>
                        </div>
                    </div>

                    <!-- AI Chat Section -->
                    <div class="ai-chat-section">
                        <div class="ai-chat-header">
                            <i data-lucide="bot" style="width: 20px; height: 20px; color: var(--brand-primary);"></i>
                            <h3 style="font-size: 16px; font-weight: 500; color: #212529; margin: 0;">Ask for a report in plain english</h3>
                        </div>
                        <form id="aiChatForm" class="ai-chat-form">
                            <input type="text" id="aiChatInput" name="question" 
                                placeholder="e.g., top 20 non-members by spend last quarter" 
                                class="ai-chat-input" required>
                            <button type="submit" id="aiChatButton" class="ai-chat-button">GET REPORT</button>
                            <input type="hidden" id="aiChatStartDate" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                            <input type="hidden" id="aiChatEndDate" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </form>
                        <div class="ai-suggestions">
                            <div style="font-size: 14px; color: #6c757d; margin-bottom: 8px;">Frequently asked reports:</div>
                            <div class="ai-suggestion-chips">
                                <button type="button" class="ai-suggestion-chip" data-question="Top 10 spending customers this month">Top 10 spending customers this month</button>
                                <button type="button" class="ai-suggestion-chip" data-question="Show me monthly sales trends for 2025">Show me monthly sales trends for 2025</button>
                                <button type="button" class="ai-suggestion-chip" data-question="High turnover products">High turnover products</button>
                                <button type="button" class="ai-suggestion-chip" data-question="Give me basic report for invoice list for this month">Give me basic report for invoice list for this month</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Report data from PHP
    const reportData = <?php echo json_encode($reportData); ?>;
    const allTableData = reportData && reportData.data ? reportData.data : [];

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
            return;
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
            return;
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

    // Initialize
    if (reportData && reportData.data) {
        renderTable(reportData.data);
        renderChart(reportData.data);
    }

    // AI Chat functionality
    const aiChatForm = document.getElementById('aiChatForm');
    const aiChatInput = document.getElementById('aiChatInput');
    const aiChatButton = document.getElementById('aiChatButton');
    const aiChatStartDate = document.getElementById('aiChatStartDate');
    const aiChatEndDate = document.getElementById('aiChatEndDate');
    const activeReportTitle = document.getElementById('active-report-title');

    // Update date inputs when date range form changes
    document.querySelector('.date-form').addEventListener('submit', function() {
        setTimeout(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('start_date')) {
                aiChatStartDate.value = urlParams.get('start_date');
            }
            if (urlParams.get('end_date')) {
                aiChatEndDate.value = urlParams.get('end_date');
            }
        }, 100);
    });

    // Handle suggestion chips
    document.querySelectorAll('.ai-suggestion-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            aiChatInput.value = this.getAttribute('data-question');
            aiChatForm.dispatchEvent(new Event('submit'));
        });
    });

    // Handle AI form submission
    aiChatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const question = aiChatInput.value.trim();
        if (!question) {
            alert('Please enter a question');
            return;
        }

        // Disable form
        aiChatButton.disabled = true;
        aiChatButton.textContent = 'Loading...';

        // Prepare request data
        const requestData = {
            question: question,
            start_date: aiChatStartDate.value,
            end_date: aiChatEndDate.value
        };

        // Get API base URL
        const apiBaseUrl = '<?php echo Service_AnalyticService::getApiBaseUrl(); ?>';
        const url = apiBaseUrl + '/reports/ask-ai';
        
        // For debugging
        console.log('API URL:', url);
        console.log('Request Data:', requestData);

        // Make API request
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            // Re-enable form
            aiChatButton.disabled = false;
            aiChatButton.textContent = 'GET REPORT';

            if (data.success && data.data) {
                // Update report title
                activeReportTitle.textContent = question;
                
                // Update allTableData
                allTableData = data.data;
                
                // Render table and chart
                renderTable(data.data);
                renderChart(data.data);
                
                // Clear input
                aiChatInput.value = '';
                
                // Scroll to top of results
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                // Show error
                const errorMsg = data.error || data.message || 'Failed to generate report';
                alert('Error: ' + errorMsg);
            }
        })
        .catch(error => {
            // Re-enable form
            aiChatButton.disabled = false;
            aiChatButton.textContent = 'GET REPORT';
            
            console.error('Error:', error);
            alert('Error: ' + (error.message || 'Failed to connect to API'));
        });
    });
</script>