<?php
require_once __DIR__ . '/Service_AnalyticService.php';

// Fetch dashboards list
$dashboardsResponse = Service_AnalyticService::apiRequest('dashboards');
$dashboards = isset($dashboardsResponse['data']) ? $dashboardsResponse['data'] : [];
$dashboardsError = null;

if (isset($dashboardsResponse['success']) && !$dashboardsResponse['success']) {
    $dashboardsError = isset($dashboardsResponse['error']) ? $dashboardsResponse['error'] : 'Failed to load dashboards';
}

// Fetch reports list
$reportsResponse = Service_AnalyticService::apiRequest('reports/predefined');
$reports = isset($reportsResponse['data']) ? $reportsResponse['data'] : [];
$reportsError = null;

if (isset($reportsResponse['success']) && !$reportsResponse['success']) {
    $reportsError = isset($reportsResponse['error']) ? $reportsResponse['error'] : 'Failed to load reports';
}

// Dashboard icons mapping
$dashboardIcons = [
    'classes-training' => ['icon' => 'graduation-cap', 'bg' => 'bg-purple', 'color' => 'text-purple'],
    'customer' => ['icon' => 'users', 'bg' => 'bg-primary', 'color' => 'text-primary'],
    'employee' => ['icon' => 'briefcase', 'bg' => 'bg-info', 'color' => 'text-info'],
    'financial-revenue' => ['icon' => 'dollar-sign', 'bg' => 'bg-success', 'color' => 'text-success'],
    'membership' => ['icon' => 'id-card', 'bg' => 'bg-warning', 'color' => 'text-warning'],
    'products-retail' => ['icon' => 'package', 'bg' => 'bg-danger', 'color' => 'text-danger'],
    'range-usage' => ['icon' => 'target', 'bg' => 'bg-danger', 'color' => 'text-danger'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboards - BizzAI Analytics</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --brand-primary: #33a1df;
            --brand-primary-rgb: 51, 161, 223;
        }
        .brand-primary { color: var(--brand-primary); }
        .bg-brand-primary { background-color: var(--brand-primary); }
        .bg-brand-primary-light { background-color: rgba(51, 161, 223, 0.1); }
        .border-brand-primary { border-color: var(--brand-primary); }
        body { background-color: #f5f5f5; }
        .container-custom { max-width: 1280px; margin: 0 auto; }
        .header-nav { display: flex; align-items: center; justify-content: space-between; }
        .header-nav > div { display: flex; align-items: center; }
        .logo-container { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .logo-container img { width: 24px; height: 24px; }
        .nav-links { display: flex; align-items: center; gap: 24px; }
        .nav-links a { padding: 8px 16px; font-size: 14px; font-weight: 500; border-radius: 4px; }
        .nav-links a.active { background-color: #e9ecef; color: #212529; }
        .nav-links a:hover { background-color: #e9ecef; text-decoration: none; }
        .dashboard-card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; border: 1px solid #dee2e6; margin-bottom: 24px; transition: box-shadow 0.2s; }
        .dashboard-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-decoration: none; }
        .dashboard-card-header { display: flex; align-items: center; margin-bottom: 12px; }
        .dashboard-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .dashboard-title { font-size: 20px; font-weight: 600; color: #212529; margin: 0; }
        .dashboard-description { color: #6c757d; font-size: 14px; margin-bottom: 16px; }
        .dashboard-link { display: flex; align-items: center; font-size: 14px; font-weight: 500; color: var(--brand-primary); margin-top: 16px; }
        .empty-state { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 48px; text-align: center; }
        .alert-custom { border-left: 4px solid; }
        .sticky-header { position: sticky; top: 0; z-index: 1000; background: white; border-bottom: 1px solid #dee2e6; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="sticky-header">
        <div class="container-custom">
            <div class="header-nav" style="padding: 16px 24px;">
                <div>
                    <div class="logo-container" style="margin-right: 12px;">
                        <img alt="Logo" src="https://88tactical.com/wp-content/uploads/2022/07/88-tactical-logo-vert-236x300.png">
                    </div>
                    <a href="/business/analytics" style="font-weight: 600; text-decoration: none;">
                        <h1 style="margin: 0; line-height: 1.2;">
                            <span style="color:#000; font-weight:bold;font-size:120%;">88 Tactical</span>
                            <span style="color:#33a1df; font-weight:bold;font-size:120%;">AI</span>
                            <span style="color:#000; font-weight:500; font-size:80%;"> Analytics</span>
                        </h1>
                    </a>
                </div>
                <nav class="nav-links">
                    <a href="/business/analytics/reports">Reports</a>
                    <a href="/business/analytics/dashboards" class="active">Home</a>
                </nav>
            </div>
        </div>
    </header>
    
    <div style="min-height: calc(100vh - 80px); padding: 24px;">
        <div class="container-custom">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: bold; color: #212529; margin-bottom: 8px;">Analytics Dashboard</h1>
                <p style="color: #6c757d;">Navigate to dashboards or reports to view analytics and insights</p>
            </div>

            <!-- Dashboards Section -->
            <div style="margin-bottom: 48px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: 600; color: #212529; margin: 0;">Dashboards</h2>
                    <a href="/business/analytics/dashboards" style="font-size: 14px; color: var(--brand-primary); text-decoration: none;">View All</a>
                </div>

                <?php if ($dashboardsError): ?>
                <div class="alert alert-danger alert-custom" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start;">
                        <div style="flex-shrink: 0; margin-right: 12px;">
                            <i data-lucide="alert-circle" style="width: 20px; height: 20px; color: #dc3545;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 500; color: #721c24; margin-top: 0; margin-bottom: 8px;">Error</h4>
                            <div style="font-size: 14px; color: #721c24;">
                                <p style="margin: 0;"><?php echo htmlspecialchars($dashboardsError); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach (array_slice($dashboards, 0, 6) as $dashboard): 
                        $iconConfig = isset($dashboardIcons[$dashboard['type']]) ? $dashboardIcons[$dashboard['type']] : ['icon' => 'layout-dashboard', 'bg' => 'bg-brand-primary-light', 'color' => 'brand-primary'];
                        $title = isset($dashboard['title']) ? $dashboard['title'] : ucfirst($dashboard['type']);
                        $description = isset($dashboard['description']) ? $dashboard['description'] : 'View dashboard analytics';
                    ?>
                    <div class="col-md-4 col-sm-6">
                        <a href="/business/analytics/dashboard?type=<?php echo htmlspecialchars($dashboard['type']); ?>" class="dashboard-card">
                            <div class="dashboard-card-header">
                                <div class="dashboard-icon <?php echo htmlspecialchars($iconConfig['bg']); ?>">
                                    <i data-lucide="<?php echo htmlspecialchars($iconConfig['icon']); ?>" style="width: 24px; height: 24px;" class="<?php echo htmlspecialchars($iconConfig['color']); ?>"></i>
                                </div>
                                <h3 class="dashboard-title"><?php echo htmlspecialchars($title); ?></h3>
                            </div>
                            <p class="dashboard-description"><?php echo htmlspecialchars($description); ?></p>
                            <div class="dashboard-link">
                                View Dashboard
                                <i data-lucide="arrow-right" style="width: 16px; height: 16px; margin-left: 8px;"></i>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($dashboards)): ?>
                <div class="empty-state">
                    <i data-lucide="inbox" style="width: 64px; height: 64px; color: #adb5bd; margin: 0 auto 16px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: #212529; margin-bottom: 8px;">No Dashboards Available</h3>
                    <p style="color: #6c757d;">Dashboard configurations will appear here once they are created.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Reports Section -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: 600; color: #212529; margin: 0;">Reports</h2>
                    <a href="/business/analytics/reports" style="font-size: 14px; color: var(--brand-primary); text-decoration: none;">View All Reports</a>
                </div>

                <?php if ($reportsError): ?>
                <div class="alert alert-danger alert-custom" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start;">
                        <div style="flex-shrink: 0; margin-right: 12px;">
                            <i data-lucide="alert-circle" style="width: 20px; height: 20px; color: #dc3545;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 500; color: #721c24; margin-top: 0; margin-bottom: 8px;">Error</h4>
                            <div style="font-size: 14px; color: #721c24;">
                                <p style="margin: 0;"><?php echo htmlspecialchars($reportsError); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach (array_slice($reports, 0, 6) as $report): 
                        $title = isset($report['title']) ? $report['title'] : ucfirst($report['type']);
                        $description = isset($report['description']) ? $report['description'] : 'View report';
                    ?>
                    <div class="col-md-4 col-sm-6">
                        <a href="/business/analytics/reports/predefined/<?php echo htmlspecialchars($report['type']); ?>?start_date=<?php echo htmlspecialchars(date('Y-m-d', strtotime('-1 month'))); ?>&end_date=<?php echo htmlspecialchars(date('Y-m-d')); ?>" class="dashboard-card">
                            <div class="dashboard-card-header">
                                <div class="dashboard-icon bg-brand-primary-light">
                                    <i data-lucide="file-text" style="width: 24px; height: 24px;" class="brand-primary"></i>
                                </div>
                                <h3 class="dashboard-title"><?php echo htmlspecialchars($title); ?></h3>
                            </div>
                            <p class="dashboard-description"><?php echo htmlspecialchars($description); ?></p>
                            <div class="dashboard-link">
                                View Report
                                <i data-lucide="arrow-right" style="width: 16px; height: 16px; margin-left: 8px;"></i>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($reports)): ?>
                <div class="empty-state">
                    <i data-lucide="inbox" style="width: 64px; height: 64px; color: #adb5bd; margin: 0 auto 16px;"></i>
                    <h3 style="font-size: 20px; font-weight: 600; color: #212529; margin-bottom: 8px;">No Reports Available</h3>
                    <p style="color: #6c757d;">Reports will appear here once they are created.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
