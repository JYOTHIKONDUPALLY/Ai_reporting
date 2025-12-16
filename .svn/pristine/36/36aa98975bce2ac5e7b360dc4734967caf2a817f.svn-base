<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Financial Dashboard - BizzAI Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .revenue-card {
            border-radius: 8px;
            padding: 20px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: left;
            min-height: 140px;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }
        
        .revenue-card-membership {
            background: linear-gradient(50deg, #9599a1 0%, #747683 80%);
        }
        
        .revenue-card-products {
            background: linear-gradient(50deg, #fd9d9e 0%, #f66d72 80%);
        }
        
        .revenue-card-training {
            background: linear-gradient(50deg, #89b4a0 0%, #548870 80%);
        }
        
        .revenue-card-services {
            background: linear-gradient(50deg, #3ba7b8 0%, #267081 80%);
        }
        
        .revenue-card-giftcards {
            background: linear-gradient(50deg, #faa524 0%, #ef820b 80%);
        }
        
        .revenue-card-total {
            background: linear-gradient(50deg, #d86bea 0%, #763881 80%);
        }
        
        .revenue-card-bg-icons {
            position: absolute;
            inset: 0;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            pointer-events: none;
            z-index: 0;
        }
        
        .revenue-card-bg-icon {
            position: absolute;
            right: 14px;
            font-size: 100px;
            color: rgba(255, 255, 255, 0.14);
            transform: translateY(5%);
            top: 5%;
        }
        
        .revenue-card-icon {
            display: none;
        }
        
        .revenue-card-amount {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            line-height: 1.2;
            z-index: 1;
            position: relative;
        }
        
        .revenue-card-separator {
            width: 100%;
            height: 1px;
            background-color: rgba(255, 255, 255, 0.3);
            margin-bottom: 12px;
            z-index: 1;
            position: relative;
        }
        
        .revenue-card-label {
            font-size: 22px;
            color: white;
            opacity: 0.95;
            z-index: 1;
            position: relative;
            line-height: 1.4;
        }
        
        .total-revenue-wrapper {
            display: flex;
            height: 100%;
        }
        
        .total-revenue-card {
            background-color: #f45b5b;
            border-radius: 8px;
            padding: 20px;
            min-height: 100%;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            box-sizing: border-box;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .total-revenue-title {
            font-size: 16px;
            font-weight: 600;
            color: white;
            margin-bottom: 12px;
        }
        
        .total-revenue-amount {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
        }
        
        .section-bg-light {
            background-color: #f5f5f5;
            border: 1px solid #d0d0d0;
        }
        
        .section-bg-dark {
            background-color: #424242;
            border: 1px solid #2d2d2d;
        }
        
        .section-header {
            background-color: #e0e0e0;
            padding: 12px 16px;
            margin: -24px -24px 16px -24px;
            border-radius: 6px 6px 0 0;
            border-bottom: 1px solid #d0d0d0;
        }
        
        .section-bg-dark .section-header {
            background-color: #2d2d2d;
            border-bottom: 0px solid #1a1a1a;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 0;
            text-align: center;
        }
        
        .section-bg-light .section-title {
            color: #333;
        }
        
        .section-bg-dark .section-title {
            color: #fff;
        }
        
        .revenue-grid {
            display: grid;
            grid-template-columns: 75% 25%;
            gap: 16px;
            width: 100%;
            align-items: stretch;
        }
        
        .revenue-cards-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, minmax(140px, 1fr));
            gap: 16px;
            width: 100%;
            align-items: stretch;
            height: 100%;
        }
        
        .revenue-card {
            width: 100%;
            box-sizing: border-box;
        }
        
        @media (max-width: 1400px) {
            .revenue-grid {
                grid-template-columns: 75% 25%;
            }
            .revenue-cards-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .revenue-grid {
                grid-template-columns: 1fr;
            }
            .total-revenue-card {
                margin-top: 16px;
                min-height: 300px;
            }
        }
        
        @media (max-width: 768px) {
            .revenue-cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
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
                    return ucfirst(date('M', $timestamp)) . ' ' . $day . $suffix . ' ' . date('Y', $timestamp);
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
                        'title' => 'Daily Sales - Sales Today ' . formatDate($today),
                        'data' => $todayData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Daily Sales - Sales Yesterday ' . formatDate($yesterday),
                        'data' => $yesterdayData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Week (to date) Sales - as of ' . formatDate($today),
                        'data' => $weekToDateData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Previous Week Sales - ' . formatDate($prevWeekStart) . ' - ' . formatDate($prevWeekEnd),
                        'data' => $prevWeekData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Month (to date) Sales - as of ' . formatDate($today),
                        'data' => $monthToDateData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Last Month Sales - ' . formatMonthYear($lastMonthStart),
                        'data' => $lastMonthData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Year (to date) Sales - as of ' . formatDate($today),
                        'data' => $yearToDateData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ],
                    [
                        'title' => 'Last Year Sales - ' . formatYear($lastYearStart),
                        'data' => $lastYearData,
                        'bgClass' => 'section-bg-dark',
                        'cardBg' => 'bg-gray-700'
                    ]
                ];
                
                // Revenue card configuration matching the design
                // Each card type has one relevant background icon based on the heading
                $revenueCards = [
                    [
                        'type' => 'membership',
                        'label' => 'Membership Revenue',
                        'icon' => 'fas fa-id-card',       // ID card
                        'bgColor' => '#70747f',
                        'textColor' => 'white'
                    ],
                    [
                        'type' => 'products',
                        'label' => 'Products Revenue',
                        'icon' => 'fas fa-box',           // Box
                        'bgColor' => '#f77678',
                        'textColor' => 'white'
                    ],
                    [
                        'type' => 'training',
                        'label' => 'Training Revenue',
                        'icon' => 'fas fa-graduation-cap',     // Graduation cap
                        'bgColor' => '#5b9073',
                        'textColor' => 'white'
                    ],
                    [
                        'type' => 'services',
                        'label' => 'Services Revenue',
                        'icon' => 'fas fa-concierge-bell', // Bell
                        'bgColor' => '#1d8a9c',
                        'textColor' => 'white'
                    ],
                    [
                        'type' => 'giftcards',
                        'label' => 'Gift Card Sales',
                        'icon' => 'fas fa-gift',          // Gift
                        'bgColor' => '#b150ba',
                        'textColor' => 'white'
                    ],
                    [
                        'type' => 'total',
                        'label' => 'Total Revenue',
                        'icon' => 'fas fa-calculator',    // Calculator
                        'bgColor' => '#f45b5b',
                        'textColor' => 'white'
                    ]
                ];
            @endphp

            <!-- Revenue Sections -->
            <div class="space-y-6">
                @foreach($sections as $index => $section)
                <div class="{{ $section['bgClass'] }} rounded-lg p-6">
                    <!-- Section Header -->
                    <div class="section-header">
                        <h2 class="section-title">{{ $section['title'] }}</h2>
                    </div>
                    
                    <!-- Revenue Cards Grid: 2x3 + Total Card -->
                    <div class="revenue-grid">
                        <!-- 6 Revenue Cards in 2x3 grid -->
                        <div class="revenue-cards-container">
                            @foreach($revenueCards as $card)
                            @php
                                if ($card['type'] === 'total') {
                                    $amount = $section['data']['total'] ?? 0;
                                } else {
                                    $amount = $section['data'][$card['type']] ?? 0;
                                }
                                $cardClass = 'revenue-card-' . $card['type'];
                            @endphp
                            <div class="revenue-card {{ $cardClass }}">
                                <div class="revenue-card-bg-icons">
                                    <i class="{{ $card['icon'] }} revenue-card-bg-icon"></i>
                                </div>
                                <div class="revenue-card-amount">
                                    {{ formatCurrency($amount) }}
                                </div>
                                <div class="revenue-card-separator"></div>
                                <div class="revenue-card-label">
                                    {{ $card['label'] }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <!-- Total Revenue Card (Large, on the right) -->
                        <div class="total-revenue-wrapper">
                            <div class="total-revenue-card">
                                <div class="total-revenue-title">Total revenue</div>
                                <div class="total-revenue-amount">
                                    {{ number_format($section['data']['total'] ?? 0, 0) }}
                                </div>
                                
                                <!-- Line Chart -->
                                <div style="height: 120px; margin-bottom: 20px;">
                                    <canvas id="chart-{{ $index }}" style="max-height: 120px;"></canvas>
                                </div>
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
        
        // Initialize charts for each section
        @foreach($sections as $index => $section)
        @php
            $trend = $section['data']['trend'] ?? ['labels' => [], 'data' => []];
            $labels = json_encode($trend['labels'] ?? []);
            $data = json_encode($trend['data'] ?? []);
        @endphp
        (function() {
            const ctx{{ $index }} = document.getElementById('chart-{{ $index }}');
            if (ctx{{ $index }}) {
                new Chart(ctx{{ $index }}, {
                    type: 'line',
                    data: {
                        labels: {!! $labels !!},
                        datasets: [{
                            label: 'Revenue',
                            data: {!! $data !!},
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#007bff',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: 'rgba(255, 255, 255, 0.7)',
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            y: {
                                display: false,
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        })();
        @endforeach
    </script>
</body>
</html>

