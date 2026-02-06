<?php
/**
 * API Test Page - Debug API connectivity
 * Compatible with PHP 7.1+
 */

require_once __DIR__ . '/api-helper.php';

// Test API connection
$testResult = apiRequest('reports/test');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test - BizzAI Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        pre {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            font-size: 0.875rem;
        }
        code {
            background: #e5e7eb;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-slate-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">API Connection Test</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">API Base URL Configuration</h2>
            <p class="text-gray-700 mb-2">Current API Base URL:</p>
            <code class="block p-3 bg-gray-100 rounded mb-3"><?php echo htmlspecialchars(getApiBaseUrl()); ?></code>
            <?php if (defined('API_BASE_URL') && !empty(API_BASE_URL)): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded mb-3">
                <p class="text-sm text-blue-800">
                    <strong>Note:</strong> Using custom API URL from <code>api-helper.php</code> configuration.
                </p>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded mb-3">
                <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> Auto-detected URL. To use a custom API host, edit <code>api-helper.php</code> and set the <code>API_BASE_URL</code> constant.
                </p>
            </div>
            <?php endif; ?>
            <div class="mt-3 p-3 bg-gray-50 rounded text-sm">
                <p class="font-semibold mb-2">To change the API URL:</p>
                <ol class="list-decimal list-inside space-y-1 text-gray-700">
                    <li>Open <code>public/api-helper.php</code></li>
                    <li>Find the line: <code>define('API_BASE_URL', '');</code></li>
                    <li>Set it to your API URL, e.g.: <code>define('API_BASE_URL', 'http://54.71.56.201/api/v1');</code></li>
                </ol>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Endpoint: <code>/api/v1/reports/test</code></h2>
            
            <?php if (isset($testResult['success']) && $testResult['success']): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Connection Successful!</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>The API is responding correctly.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <h3 class="font-semibold mb-2">Response:</h3>
                <pre><?php echo htmlspecialchars(json_encode($testResult, JSON_PRETTY_PRINT)); ?></pre>
            </div>
            <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-red-800">Connection Failed</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p class="font-semibold"><?php echo htmlspecialchars(isset($testResult['error']) ? $testResult['error'] : 'Unknown error'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <h3 class="font-semibold mb-2">Error Details:</h3>
                <pre><?php echo htmlspecialchars(json_encode($testResult, JSON_PRETTY_PRINT)); ?></pre>
            </div>
            <?php if (isset($testResult['url'])): ?>
            <div class="mb-4">
                <h3 class="font-semibold mb-2">Requested URL:</h3>
                <code class="block p-3 bg-gray-100 rounded break-all"><?php echo htmlspecialchars($testResult['url']); ?></code>
            </div>
            <?php endif; ?>
            <?php if (isset($testResult['response_preview'])): ?>
            <div class="mb-4">
                <h3 class="font-semibold mb-2">Response Preview:</h3>
                <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars($testResult['response_preview']); ?></pre>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Troubleshooting</h2>
            <ul class="list-disc list-inside space-y-2 text-gray-700">
                <li>Ensure the Laravel application is running</li>
                <li>Check that the API routes are accessible at <code><?php echo htmlspecialchars(getApiBaseUrl()); ?></code></li>
                <li>Verify that cURL is enabled in PHP</li>
                <li>Check for PHP errors in Laravel logs</li>
                <li>Ensure the API base URL is correct (modify <code>getApiBaseUrl()</code> in <code>api-helper.php</code> if needed)</li>
                <li>If you see HTML instead of JSON, check Laravel error pages</li>
            </ul>
        </div>

        <div class="mt-6">
            <a href="reports.php" class="text-blue-600 hover:text-blue-800 underline">← Back to Reports</a>
        </div>
    </div>
</body>
</html>

