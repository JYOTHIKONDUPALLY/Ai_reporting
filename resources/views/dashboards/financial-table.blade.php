<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Financial Dashboard - Table View - BizzAI Analytics</title>
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
                                   class="dashboard-tab px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ 'financial-table' === $dash['type'] ? '' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}"
                                   style="{{ 'financial-table' === $dash['type'] ? 'border-color: #A71930; color: #A71930;' : '' }}">
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
                <p class="text-gray-600">Monthly revenue breakdown by category for {{ $currentYear }}</p>
            </div>

            <!-- Helper function to format currency -->
            @php
                function formatCurrency($amount) {
                    return '$' . number_format($amount, 2);
                }
            @endphp

            <!-- Financial Table -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 border-gray-300">
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 border-r border-gray-300">Month</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-r border-gray-300">Memberships</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-r border-gray-300">Products</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-r border-gray-300">Services</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-r border-gray-300">Training</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-r border-gray-300">Packages</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 bg-gray-200">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($monthlyData as $month => $data)
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r border-gray-200">{{ $month }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">{{ formatCurrency($data['memberships']) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">{{ formatCurrency($data['products']) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">{{ formatCurrency($data['services']) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">{{ formatCurrency($data['training']) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">{{ formatCurrency($data['packages']) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right bg-gray-50">{{ formatCurrency($data['total']) }}</td>
                            </tr>
                            @endforeach
                            
                            <!-- Totals Row -->
                            <tr class="bg-gray-100 border-t-2 border-gray-400 font-semibold">
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 border-r border-gray-300">TOTAL</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right border-r border-gray-300">{{ formatCurrency($columnTotals['memberships']) }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right border-r border-gray-300">{{ formatCurrency($columnTotals['products']) }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right border-r border-gray-300">{{ formatCurrency($columnTotals['services']) }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right border-r border-gray-300">{{ formatCurrency($columnTotals['training']) }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right border-r border-gray-300">{{ formatCurrency($columnTotals['packages']) }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-brand-primary text-right bg-gray-200">{{ formatCurrency($columnTotals['total']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
                    <div class="text-sm text-gray-600 mb-1">Total Memberships</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency($columnTotals['memberships']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
                    <div class="text-sm text-gray-600 mb-1">Total Products</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency($columnTotals['products']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
                    <div class="text-sm text-gray-600 mb-1">Total Services</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency($columnTotals['services']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
                    <div class="text-sm text-gray-600 mb-1">Total Training</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency($columnTotals['training']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-md border-2 border-brand-primary p-4">
                    <div class="text-sm text-gray-600 mb-1">Grand Total</div>
                    <div class="text-2xl font-bold brand-primary">{{ formatCurrency($columnTotals['total']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>

