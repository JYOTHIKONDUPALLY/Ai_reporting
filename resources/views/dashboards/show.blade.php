<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $dashboard['title'] }} - BizzAI Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --brand-primary: #A71930;
            --brand-primary-rgb: 167, 25, 48;
            --brand-primary-light: rgba(167, 25, 48, 0.1);
        }
        
        .brand-primary {
            color: var(--brand-primary);
        }
        
        .bg-brand-primary {
            background-color: var(--brand-primary);
        }
        
        .bg-brand-primary-light {
            background-color: var(--brand-primary-light);
        }
        
        .border-brand-primary {
            border-color: var(--brand-primary);
        }
        
        .section-header {
            background: var(--brand-primary);
            color: white;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .chart-container {
            position: relative;
            height: 400px;
            padding: 20px;
        }
        .dashboard-tab {
            transition: all 0.2s ease;
            position: relative;
        }
        .dashboard-tab:hover {
            background-color: #f9fafb;
        }
        .dashboard-tab.border-blue-600 {
            background-color: var(--brand-primary-light);
        }
        nav.flex.space-x-1 {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        nav.flex.space-x-1::-webkit-scrollbar {
            height: 6px;
        }
        nav.flex.space-x-1::-webkit-scrollbar-track {
            background: transparent;
        }
        nav.flex.space-x-1::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl grid place-items-center">
                        <img alt="Logo" class="w-6 h-6" src="https://88tactical.com/wp-content/uploads/2022/07/88-tactical-logo-vert-236x300.png">
                    </div>
                    <a href="/dashboards" class="font-semibold">
                        <h1>
                            <span style="color:#000; font-weight:bold;font-size:120%;">88 Tactical</span>
                            <span style="color:#A71930; font-weight:bold;font-size:120%;">AI</span>
                            <span style="color:#000; font-weight:500; font-size:80%;"> Analytics</span>
                        </h1>
                    </a>

                </div>
                
                <!-- Navigation Menu -->
                <nav class="flex items-center gap-6">
                    <a href="{{ route('reports.index') }}" 
                       class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                        Reports
                    </a>
                    <a href="{{ route('dashboards.index') }}" 
                       class="px-4 py-2 text-sm font-medium rounded-lg font-semibold brand-primary bg-brand-primary-light">
                        Dashboards
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors flex items-center gap-2">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Logout
                        </button>
                    </form>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="min-h-screen">
        <!-- Dashboard Tabs Section -->
        <div class="bg-white border-b border-gray-200 sticky top-[73px] z-10 shadow-sm">
            <div class="max-w-7xl mx-auto">
                <!-- Top Bar -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Dashboards</h1>
                        </div>
                        <!-- Period Filters -->
                        <div class="flex items-center gap-2 flex-wrap">
                            @php
                                $currentPeriod = $currentPeriod ?? 'all_time';
                                $periods = [
                                    'all_time' => 'All Time',
                                    'this_month' => 'This Month',
                                    'last_month' => 'Last Month',
                                    'year' => 'This Year',
                                    'last_year' => 'Last Year'
                                ];
                            @endphp
                            @foreach($periods as $periodKey => $periodLabel)
                                <a href="{{ route('dashboards.show', $dashboardType) }}?period={{ $periodKey }}"
                                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $currentPeriod === $periodKey ? 'text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' }}"
                                   style="{{ $currentPeriod === $periodKey ? 'background-color: #A71930;' : '' }}"
                                   onmouseover="{{ $currentPeriod !== $periodKey ? "this.style.backgroundColor='#e5e7eb'" : "this.style.backgroundColor='#8b1424'" }}"
                                   onmouseout="{{ $currentPeriod !== $periodKey ? "this.style.backgroundColor='#f3f4f6'" : "this.style.backgroundColor='#A71930'" }}">
                                    {{ $periodLabel }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Horizontal Tabs Menu -->
                <div class="px-6">
                    <nav class="flex space-x-1 overflow-x-auto" aria-label="Dashboard Tabs">
                        @foreach($allDashboards ?? [] as $dash)
                        <a href="{{ route('dashboards.show', $dash['type']) }}?period={{ $currentPeriod ?? 'all_time' }}"
                           class="dashboard-tab px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $dashboardType === $dash['type'] ? 'border-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}"
                           style="{{ $dashboardType === $dash['type'] ? 'border-color: #A71930; color: #A71930;' : '' }}">
                            {{ $dash['title'] }}
                        </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Title and Description -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <h2 class="text-xl font-semibold text-gray-900">{{ $dashboard['title'] }}</h2>
                @if(!empty($dashboard['description']))
                <p class="text-gray-600 text-sm mt-1">{{ $dashboard['description'] }}</p>
                @endif
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="max-w-7xl mx-auto px-6 py-6">
            @if(isset($dashboard['error']))
            <div class="rounded-lg p-4 mb-6" style="background-color: rgba(167, 25, 48, 0.1); border: 1px solid rgba(167, 25, 48, 0.3);">
                <p style="color: #1e6fa8;">{{ $dashboard['error'] }}</p>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="dashboard-widgets">
                @foreach($dashboard['widgets'] ?? [] as $widget)
                <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden widget-container" 
                     data-widget-id="{{ $widget['id'] }}">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">{{ $widget['title'] }}</h3>
                    </div>
                    <div class="p-4">
                        @if(isset($widget['error']))
                        <div class="text-sm brand-primary">{{ $widget['error'] }}</div>
                        @elseif($widget['type'] === 'metric')
                        <div class="grid grid-cols-{{ count($widget['config']['metrics'] ?? []) }} gap-4">
                            @foreach($widget['config']['metrics'] ?? [] as $metric)
                            @php
                                $value = $widget['data'][0][$metric['field']] ?? 0;
                                $rawValue = $value;
                                if($metric['format'] === 'currency') {
                                    if($value >= 1000000) {
                                        $formatted = number_format($value / 1000000, 1);
                                        $value = '$' . rtrim(rtrim($formatted, '0'), '.') . 'M';
                                    } elseif($value >= 1000) {
                                        $formatted = number_format($value / 1000, 1);
                                        $value = '$' . rtrim(rtrim($formatted, '0'), '.') . 'K';
                                    } else {
                                        $value = '$' . number_format($value, 2);
                                    }
                                } elseif($metric['format'] === 'number') {
                                    if($value >= 1000000) {
                                        $formatted = number_format($value / 1000000, 1);
                                        $value = rtrim(rtrim($formatted, '0'), '.') . 'M';
                                    } elseif($value >= 1000) {
                                        $formatted = number_format($value / 1000, 1);
                                        $value = rtrim(rtrim($formatted, '0'), '.') . 'K';
                                    } else {
                                        $value = number_format($value);
                                    }
                                }
                            @endphp
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900">{{ $value }}</div>
                                <div class="text-xs text-gray-600 mt-1">{{ $metric['label'] }}</div>
                            </div>
                            @endforeach
                        </div>
                        @elseif($widget['type'] === 'table')
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        @foreach($widget['config']['columns'] ?? (count($widget['data']) > 0 ? array_keys($widget['data'][0]) : []) as $col)
                                        @php
                                            $headerText = str_replace('_', ' ', $col);
                                        @endphp
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase truncate whitespace-nowrap overflow-hidden max-w-[200px]" title="{{ $headerText }}">{{ $headerText }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach(array_slice($widget['data'], 0, 10) as $row)
                                    <tr>
                                        @foreach($widget['config']['columns'] ?? array_keys($row) as $col)
                                        <td class="px-3 py-2 text-gray-900">
                                            @php
                                                $value = $row[$col] ?? '';
                                                $isDateColumn = stripos($col, 'date') !== false || stripos($col, 'time') !== false || 
                                                               (is_string($value) && (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) || preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value)));
                                            @endphp
                                            @if($isDateColumn && $value)
                                                @php
                                                    try {
                                                        $date = new DateTime($value);
                                                        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                                        $formattedDate = $date->format('j') . ' ' . $months[(int)$date->format('n') - 1] . ' ' . $date->format('Y');
                                                    } catch (Exception $e) {
                                                        $formattedDate = $value;
                                                    }
                                                @endphp
                                                {{ $formattedDate }}
                                            @elseif(is_numeric($value))
                                                {{ number_format($value, is_float($value) ? 2 : 0) }}
                                            @else
                                                @php
                                                    // Remove prefixes like "Product:", "class:", "Membership:", "Service:", "Appointment:"
                                                    $displayValue = $value;
                                                    if (is_string($value)) {
                                                        // Replace N/A with Others for employee_name column
                                                        if (stripos($col, 'employee_name') !== false && (strtoupper(trim($value)) === 'N/A' || strtoupper(trim($value)) === 'NA')) {
                                                            $displayValue = 'Others';
                                                        } else {
                                                            $prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
                                                            foreach ($prefixes as $prefix) {
                                                                if (stripos($value, $prefix) === 0) {
                                                                    $displayValue = trim(substr($value, strlen($prefix)));
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                {{ $displayValue }}
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if(count($widget['data']) > 10)
                            <div class="text-xs text-gray-500 mt-2 text-center">Showing 10 of {{ count($widget['data']) }} rows</div>
                            @endif
                        </div>
                        @elseif(in_array($widget['type'], ['bar', 'line', 'pie']))
                        <div class="chart-container">
                            <canvas id="chart-{{ $widget['id'] }}"></canvas>
                        </div>
                        @endif
                        
                        <!-- Debug SQL Display -->
                        @if(isset($widget['sql']))
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <details class="group">
                                <summary class="cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700 flex items-center gap-2">
                                    <i data-lucide="code" class="w-3 h-3"></i>
                                    <span>View SQL (Debug)</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="mt-2 p-3 bg-gray-50 rounded border border-gray-200">
                                    <pre class="text-xs text-gray-700 whitespace-pre-wrap break-words font-mono overflow-x-auto">{{ $widget['sql'] }}</pre>
                                </div>
                            </details>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            @if(empty($dashboard['widgets']))
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i data-lucide="inbox" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Widgets Available</h3>
                <p class="text-gray-600">This dashboard doesn't have any widgets configured.</p>
            </div>
            @endif
        </div>
    </div>

    <script>
        // Render charts
        @foreach($dashboard['widgets'] ?? [] as $widget)
        @if(in_array($widget['type'], ['bar', 'line', 'pie']) && !empty($widget['data']))
        (function() {
            const ctx = document.getElementById('chart-{{ $widget['id'] }}');
            if (!ctx) return;
            
            // Number formatting function
            function formatNumber(num) {
                if (typeof num !== 'number' || isNaN(num)) return num;
                if (num >= 1000000) {
                    const millions = num / 1000000;
                    return (millions % 1 === 0 ? millions.toString() : millions.toFixed(1)) + 'M';
                }
                if (num >= 1000) {
                    const thousands = num / 1000;
                    return (thousands % 1 === 0 ? thousands.toString() : thousands.toFixed(1)) + 'K';
                }
                return num.toString();
            }
            
            // Remove prefixes from item names
            function removeItemPrefix(label) {
                if (!label) return label;
                const str = String(label).trim();
                
                // Replace N/A with Others for employee names
                if (str.toUpperCase() === 'N/A' || str.toUpperCase() === 'NA') {
                    return 'Others';
                }
                
                const prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];
                for (const prefix of prefixes) {
                    if (str.toLowerCase().startsWith(prefix.toLowerCase())) {
                        return str.substring(prefix.length).trim();
                    }
                }
                return str;
            }
            
            // Label truncation function
            function truncateLabel(label, maxLength = 20) {
                if (!label) return label;
                const str = String(label);
                if (str.length <= maxLength) return str;
                return str.substring(0, maxLength - 3) + '...';
            }
            
            // Format date function - converts dates to "20 Nov 2025" format
            function formatDate(value) {
                if (!value) return value;
                const dateStr = String(value).trim();
                let date;
                try {
                    if (dateStr.match(/^\d{4}-\d{2}-\d{2}/)) {
                        date = new Date(dateStr);
                    } else if (dateStr.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/)) {
                        date = new Date(dateStr);
                    } else if (dateStr.match(/^\d{2}\/\d{2}\/\d{4}/)) {
                        const parts = dateStr.split('/');
                        date = new Date(parts[2], parts[0] - 1, parts[1]);
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
            
            // Check if a value looks like a date
            function isDateValue(value) {
                if (!value) return false;
                const str = String(value).trim();
                return str.match(/^\d{4}-\d{2}-\d{2}/) || str.match(/^\d{2}\/\d{2}\/\d{4}/) || str.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/);
            }
            
            const data = @json($widget['data']);
            const config = @json($widget['config'] ?? []);
            const type = '{{ $widget['type'] }}';
            
            let labels = [];
            let originalLabels = []; // Store original labels for tooltips
            let values = [];
            
            // Determine max label length based on chart type
            const maxLabelLength = type === 'pie' ? 25 : (type === 'bar' ? 15 : 20);
            
            if (data.length > 0) {
                const keys = Object.keys(data[0]);
                if (keys.length >= 2) {
                    // Check if the first column contains dates
                    const firstValue = data[0][keys[0]];
                    const isDateColumn = isDateValue(firstValue) || keys[0].toLowerCase().includes('date') || keys[0].toLowerCase().includes('time');
                    
                    originalLabels = data.map(row => {
                        const val = row[keys[0]];
                        if (isDateColumn) {
                            return formatDate(val);
                        }
                        return removeItemPrefix(val);
                    });
                    labels = data.map(row => {
                        const val = row[keys[0]];
                        if (isDateColumn) {
                            const formatted = formatDate(val);
                            return truncateLabel(formatted, maxLabelLength);
                        }
                        const cleaned = removeItemPrefix(val);
                        return truncateLabel(cleaned, maxLabelLength);
                    });
                    values = data.map(row => {
                        const val = row[keys[1]];
                        return typeof val === 'number' ? val : 0;
                    });
                }
            }
            
            const chartConfig = {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: config.yAxis || 'Value',
                        data: values,
                        backgroundColor: type === 'line' ? 'rgba(167, 25, 48, 0.1)' : [
                            '#A71930', '#ea580c', '#d97706', '#ca8a04', '#65a30d', 
                            '#16a34a', '#059669', '#0891b2', '#0284c7', '#2563eb'
                        ],
                        borderColor: type === 'line' ? '#A71930' : '#ffffff',
                        borderWidth: 2,
                        fill: type === 'line',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: type === 'pie',
                            position: 'right',
                            labels: type === 'pie' ? {
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            // Truncate label for pie chart legend (max 30 chars)
                                            const truncatedLabel = truncateLabel(label, 30);
                                            return {
                                                text: truncatedLabel + ': ' + formatNumber(value),
                                                fillStyle: data.datasets[0].backgroundColor[i] || data.datasets[0].backgroundColor,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            } : {}
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    // Show full original label in tooltip title (not truncated)
                                    if (context.length > 0) {
                                        const index = context[0].dataIndex;
                                        if (originalLabels[index]) {
                                            return originalLabels[index];
                                        }
                                        return context[0].label || '';
                                    }
                                    return '';
                                },
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += formatNumber(context.parsed.y);
                                    } else if (context.parsed !== null) {
                                        label += formatNumber(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: type !== 'pie' ? {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value);
                                }
                            }
                        },
                        x: {
                            ticks: {
                                callback: function(value, index) {
                                    const label = this.getLabelForValue(value);
                                    // Truncate labels for x-axis (max 15 chars for bar, 20 for line)
                                    return truncateLabel(label, type === 'bar' ? 15 : 20);
                                },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    } : undefined
                }
            };
            
            new Chart(ctx, chartConfig);
        })();
        @endif
        @endforeach
        
        function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const url = new URL(window.location.href);
            
            if (startDate) url.searchParams.set('start_date', startDate);
            else url.searchParams.delete('start_date');
            
            if (endDate) url.searchParams.set('end_date', endDate);
            else url.searchParams.delete('end_date');
            
            // Preserve the current dashboard type
            window.location.href = url.toString();
        }
        
        // Initialize icons after page load
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            // Recreate icons when details elements are toggled
            document.querySelectorAll('details').forEach(details => {
                details.addEventListener('toggle', function() {
                    lucide.createIcons();
                });
            });
        });
    </script>
</body>
</html>

