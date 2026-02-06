<?php

define('ANALYTICS_API_BASE_URL', 'http://54.71.56.201/api/v1');

class Service_AnalyticService
{
    public static function getApiBaseUrl()
    {
        if (defined('ANALYTICS_API_BASE_URL') && !empty(ANALYTICS_API_BASE_URL)) {
            $apiUrl = rtrim(ANALYTICS_API_BASE_URL, '/');
            
            // If current page is HTTPS, convert API URL to HTTPS to avoid mixed content issues
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                       (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                       (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                       (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            
            if ($isHttps && strpos($apiUrl, 'http://') === 0) {
                $apiUrl = str_replace('http://', 'https://', $apiUrl);
            }
            
            return $apiUrl;
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname($script);

        // If we're in public directory, go up one level for Laravel
        if (basename($basePath) === 'public') {
            $basePath = dirname($basePath);
        }

        return $protocol . '://' . $host . $basePath . '/api/v1';
    }


    public static function apiRequest($endpoint, $method = 'GET', $data = null)
    {
        $baseUrl = self::getApiBaseUrl();
        $url = $baseUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ]);
            }
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error,
                'url' => $url
            ];
        }

        // Check if response is empty
        if (empty($response)) {
            return [
                'success' => false,
                'error' => 'Empty response from API',
                'url' => $url,
                'http_code' => $httpCode
            ];
        }

        // Trim response to remove any whitespace
        $response = trim($response);

        // Check if response is HTML (error page)
        if (stripos($response, '<!DOCTYPE') === 0 || stripos($response, '<html') === 0) {
            // Try to extract error message from HTML
            $errorMsg = 'API returned HTML instead of JSON';
            if (preg_match('/<title>(.*?)<\/title>/i', $response, $matches)) {
                $errorMsg .= ': ' . html_entity_decode($matches[1]);
            }
            return [
                'success' => false,
                'error' => $errorMsg,
                'url' => $url,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'response_preview' => substr($response, 0, 500)
            ];
        }

        // Check Content-Type header
        if ($contentType && stripos($contentType, 'application/json') === false) {
            return [
                'success' => false,
                'error' => 'API returned non-JSON content type: ' . $contentType,
                'url' => $url,
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 500)
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Get more details about JSON error
            $jsonError = json_last_error_msg();
            $responsePreview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;

            // Check for common issues
            $hint = '';
            if (stripos($response, 'error') !== false && stripos($response, 'exception') !== false) {
                $hint = ' (Possible PHP error in API response)';
            } elseif (preg_match('/^[^[{]/', $response)) {
                $hint = ' (Response does not start with JSON object/array)';
            }

            return [
                'success' => false,
                'error' => 'JSON Decode Error: ' . $jsonError . $hint,
                'url' => $url,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'response_length' => strlen($response),
                'response_preview' => $responsePreview,
                'json_error_code' => json_last_error()
            ];
        }

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'error' => isset($decoded['message']) ? $decoded['message'] : (isset($decoded['error']) ? $decoded['error'] : 'HTTP Error ' . $httpCode),
                'http_code' => $httpCode,
                'url' => $url,
                'data' => $decoded
            ];
        }

        return $decoded;
    }

    /**
     * Format date to "20 Nov 2025" format
     */
    public static function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }

        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return $date;
            }

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $day = date('j', $timestamp);
            $month = $months[(int) date('n', $timestamp) - 1];
            $year = date('Y', $timestamp);

            return $day . ' ' . $month . ' ' . $year;
        } catch (Exception $e) {
            return $date;
        }
    }

    /**
     * Check if a column name is a date column
     */
    public static function isDateColumn($columnName)
    {
        $dateColumns = ['date', 'invoice_date', 'payment_date', 'created_at', 'updated_at', 'last_sale', 'month'];
        $columnLower = strtolower($columnName);

        foreach ($dateColumns as $dateCol) {
            if (strpos($columnLower, $dateCol) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove item prefix (Product:, class:, etc.)
     */
    public static function removeItemPrefix($label)
    {
        if (empty($label)) {
            return $label;
        }

        $str = trim($label);
        $prefixes = ['Product:', 'class:', 'Membership:', 'Service:', 'Appointment:'];

        foreach ($prefixes as $prefix) {
            if (stripos($str, $prefix) === 0) {
                return trim(substr($str, strlen($prefix)));
            }
        }

        // Handle N/A or NA for employee names
        if (strtolower($str) === 'n/a' || strtolower($str) === 'na') {
            return 'Others';
        }

        return $str;
    }

    /**
     * Format number with appropriate decimals
     */
    public static function formatNumber($number, $decimals = 2)
    {
        if (!is_numeric($number)) {
            return $number;
        }

        return number_format((float) $number, $decimals);
    }


}
