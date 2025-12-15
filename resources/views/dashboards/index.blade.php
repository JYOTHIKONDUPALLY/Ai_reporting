<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboards - BizzAI Analytics</title>
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
    
    <div class="min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboards</h1>
                <p class="text-gray-600">Select a dashboard to view analytics and insights</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Financial Dashboard Card View -->
                <a href="{{ route('dashboards.financial') }}" 
                   class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-green-100">
                            <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Financial Dashboard</h3>
                    </div>
                    <p class="text-gray-600 text-sm">Revenue breakdown by type and time period (Today, Yesterday, Week, Month, Year)</p>
                    <div class="mt-4 flex items-center text-sm font-medium brand-primary">
                        View Dashboard
                        <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </div>
                </a>

                <!-- Financial Dashboard Table View -->
                <a href="{{ route('dashboards.financial-table') }}" 
                   class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-blue-100">
                            <i data-lucide="table" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Financial Table</h3>
                    </div>
                    <p class="text-gray-600 text-sm">Monthly revenue breakdown by category in table format</p>
                    <div class="mt-4 flex items-center text-sm font-medium brand-primary">
                        View Table
                        <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </div>
                </a>

                @php
                    // Map dashboard types to icons and colors
                    $dashboardIcons = [
                        'classes-training' => ['icon' => 'graduation-cap', 'bg' => 'bg-purple-100', 'color' => 'text-purple-600'],
                        'customer' => ['icon' => 'users', 'bg' => 'bg-blue-100', 'color' => 'text-blue-600'],
                        'employee' => ['icon' => 'briefcase', 'bg' => 'bg-indigo-100', 'color' => 'text-indigo-600'],
                        'financial-revenue' => ['icon' => 'dollar-sign', 'bg' => 'bg-green-100', 'color' => 'text-green-600'],
                        'membership' => ['icon' => 'id-card', 'bg' => 'bg-pink-100', 'color' => 'text-pink-600'],
                        'products-retail' => ['icon' => 'package', 'bg' => 'bg-amber-100', 'color' => 'text-amber-600'],
                        'range-usage' => ['icon' => 'target', 'bg' => 'bg-red-100', 'color' => 'text-red-600'],
                    ];
                @endphp

                @foreach($dashboards as $dashboard)
                    @php
                        $iconConfig = $dashboardIcons[$dashboard['type']] ?? ['icon' => 'layout-dashboard', 'bg' => 'bg-brand-primary-light', 'color' => 'brand-primary'];
                    @endphp
                    <a href="{{ route('dashboards.show', $dashboard['type']) }}" 
                       class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center {{ $iconConfig['bg'] }}">
                                <i data-lucide="{{ $iconConfig['icon'] }}" class="w-6 h-6 {{ $iconConfig['color'] }}"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900">{{ $dashboard['title'] }}</h3>
                        </div>
                        <p class="text-gray-600 text-sm">{{ $dashboard['description'] ?? 'View dashboard analytics' }}</p>
                        <div class="mt-4 flex items-center text-sm font-medium brand-primary">
                            View Dashboard
                            <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                        </div>
                    </a>
                @endforeach
            </div>

            @if(empty($dashboards))
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i data-lucide="inbox" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Dashboards Available</h3>
                <p class="text-gray-600">Dashboard configurations will appear here once they are created.</p>
            </div>
            @endif
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

