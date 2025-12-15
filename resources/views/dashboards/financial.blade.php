<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Financial Dashboard - BizzAI Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .dashboard-tab {
            transition: all 0.2s ease;
            position: relative;
        }
        .dashboard-tab:hover {
            background-color: #f9fafb;
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
                            <h1 class="text-2xl font-bold text-gray-900">Financial Dashboard</h1>
                        </div>
                    </div>
                </div>
                
                <!-- Horizontal Tabs Menu -->
                <div class="px-6">
                    <nav class="flex space-x-1 overflow-x-auto" aria-label="Dashboard Tabs">
                        @foreach($allDashboards ?? [] as $dash)
                            @if(isset($dash['route']))
                                <a href="{{ route($dash['route']) }}"
                                   class="dashboard-tab px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ 'financial' === $dash['type'] ? '' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}"
                                   style="{{ 'financial' === $dash['type'] ? 'border-color: #A71930; color: #A71930;' : '' }}">
                                    {{ $dash['title'] }}
                                </a>
                            @else
                                <a href="{{ route('dashboards.show', $dash['type']) }}"
                                   class="dashboard-tab px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                                    {{ $dash['title'] }}
                                </a>
                            @endif
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="max-w-7xl mx-auto px-6 py-6">
            <div class="mb-6">
                <p class="text-gray-600">Revenue breakdown by type and time period</p>
            </div>

            <!-- Helper function to format currency -->
            @php
                function formatCurrency($amount) {
                    return '$' . number_format($amount, 2);
                }
                
                function formatDate($date) {
                    $timestamp = strtotime($date);
                    $day = date('j', $timestamp);
                    $suffix = date('S', $timestamp); // Gets 'st', 'nd', 'rd', 'th'
                    return strtoupper(date('M', $timestamp)) . ' ' . $day . $suffix . ' ' . date('Y', $timestamp);
                }
                
                function formatMonthYear($date) {
                    $timestamp = strtotime($date);
                    return strtoupper(date('M', $timestamp)) . ' ' . date('Y', $timestamp);
                }
                
                function formatYear($date) {
                    return date('Y', strtotime($date));
                }
            @endphp

            <!-- Revenue Section Component -->
            @php
                $sections = [
                    [
                        'title' => 'DAILY SALES - SALES TODAY ' . formatDate($today),
                        'data' => $todayData,
                        'headerColor' => 'bg-yellow-500',
                        'headerText' => 'text-white'
                    ],
                    [
                        'title' => 'DAILY SALES - SALES YESTERDAY ' . formatDate($yesterday),
                        'data' => $yesterdayData,
                        'headerColor' => 'bg-yellow-300',
                        'headerText' => 'text-gray-900'
                    ],
                    [
                        'title' => 'WEEK (to date) SALES - as of ' . formatDate($today),
                        'data' => $weekToDateData,
                        'headerColor' => 'bg-green-500',
                        'headerText' => 'text-white'
                    ],
                    [
                        'title' => 'PREVIOUS WEEK SALES - ' . formatDate($prevWeekStart) . ' - ' . formatDate($prevWeekEnd),
                        'data' => $prevWeekData,
                        'headerColor' => 'bg-green-300',
                        'headerText' => 'text-gray-900'
                    ],
                    [
                        'title' => 'MONTH (to date) SALES - as of ' . formatDate($today),
                        'data' => $monthToDateData,
                        'headerColor' => 'bg-blue-500',
                        'headerText' => 'text-white'
                    ],
                    [
                        'title' => 'LAST MONTH SALES - ' . formatMonthYear($lastMonthStart),
                        'data' => $lastMonthData,
                        'headerColor' => 'bg-blue-300',
                        'headerText' => 'text-gray-900'
                    ],
                    [
                        'title' => 'YEAR (to date) SALES - as of ' . formatDate($today),
                        'data' => $yearToDateData,
                        'headerColor' => 'bg-purple-500',
                        'headerText' => 'text-white'
                    ],
                    [
                        'title' => 'LAST YEAR SALES - ' . formatYear($lastYearStart),
                        'data' => $lastYearData,
                        'headerColor' => 'bg-purple-300',
                        'headerText' => 'text-gray-900'
                    ]
                ];
                
                $revenueTypes = [
                    'membership' => [
                        'label' => 'Membership Revenue',
                        'icon' => 'id-card',
                        'iconColor' => 'text-purple-600'
                    ],
                    'products' => [
                        'label' => 'Products Revenue',
                        'icon' => 'package',
                        'iconColor' => 'text-amber-700'
                    ],
                    'training' => [
                        'label' => 'Training Revenue',
                        'icon' => 'user',
                        'iconColor' => 'text-gray-600'
                    ],
                    'services' => [
                        'label' => 'Services Revenue',
                        'icon' => 'settings',
                        'iconColor' => 'text-green-600'
                    ],
                    'giftcards' => [
                        'label' => 'Gift Card Sales',
                        'icon' => 'gift',
                        'iconColor' => 'text-red-600'
                    ]
                ];
            @endphp

            <!-- Revenue Sections -->
            <div class="space-y-6">
                @foreach($sections as $section)
                <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                    <!-- Section Header -->
                    <div class="{{ $section['headerColor'] }} {{ $section['headerText'] }} px-4 py-2 font-semibold text-sm">
                        {{ $section['title'] }}
                    </div>
                    
                    <!-- Revenue Boxes -->
                    <div class="p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        @foreach($revenueTypes as $type => $config)
                        <div class="bg-white border border-gray-300 rounded p-4 flex flex-col items-center text-center">
                            <i data-lucide="{{ $config['icon'] }}" class="w-8 h-8 {{ $config['iconColor'] }} mb-2"></i>
                            <div class="text-2xl font-bold text-gray-900 mb-1">
                                {{ formatCurrency($section['data'][$type] ?? 0) }}
                            </div>
                            <div class="text-xs text-gray-600">
                                {{ $config['label'] }}
                            </div>
                        </div>
                        @endforeach
                        
                        <!-- Total Revenue Box -->
                        <div class="bg-white border-2 border-gray-400 rounded p-4 flex flex-col items-center text-center">
                            <i data-lucide="trending-up" class="w-8 h-8 text-purple-600 mb-2"></i>
                            <div class="text-2xl font-bold text-green-600 mb-1">
                                {{ formatCurrency($section['data']['total'] ?? 0) }}
                            </div>
                            <div class="text-xs font-semibold text-green-600">
                                Total Revenue
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>

