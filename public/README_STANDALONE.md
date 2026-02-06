# Standalone PHP Pages

These standalone PHP pages consume the REST API and provide the same functionality as the Laravel Blade templates, but can run independently on any PHP 7.1+ server.

## Files

- **`api-helper.php`** - Helper functions for making API requests
- **`reports.php`** - Reports listing and viewing page
- **`dashboards.php`** - Dashboards listing page
- **`dashboard-view.php`** - Individual dashboard view page

## Requirements

- PHP 7.1 or higher
- cURL extension enabled
- Access to the Laravel API endpoints (default: `/api/v1`)

## Setup

1. Ensure the Laravel application is running and accessible
2. The API helper automatically detects the base URL, but you can modify `getApiBaseUrl()` in `api-helper.php` if needed
3. Access the pages directly:
   - `http://your-domain.com/public/reports.php`
   - `http://your-domain.com/public/dashboards.php`
   - `http://your-domain.com/public/dashboard-view.php?type=employee`

## Features

### Reports Page (`reports.php`)
- Lists all predefined reports
- Displays report data in table format
- Supports date range filtering
- Pagination support
- Chart visualization
- Date formatting (20 Nov 2025 format)
- Item prefix removal (Product:, class:, etc.)

### Dashboards Page (`dashboards.php`)
- Lists all available dashboards
- Dashboard cards with icons
- Links to individual dashboard views

### Dashboard View Page (`dashboard-view.php`)
- Displays dashboard widgets (tables, charts, metrics)
- Period filters (This Month, Last Month, This Year, Last Year, All Time)
- Chart rendering (bar, line, pie charts)
- Table rendering with date formatting
- SQL debug display (if available in API response)

## API Endpoints Used

These pages consume the following API endpoints:

- `GET /api/v1/reports/predefined` - List predefined reports
- `GET /api/v1/reports/predefined/{type}` - Get specific report data
- `GET /api/v1/dashboards` - List all dashboards
- `GET /api/v1/dashboards/{type}` - Get dashboard data

## Customization

### Changing API Base URL

Edit `getApiBaseUrl()` in `api-helper.php`:

```php
function getApiBaseUrl() {
    // Custom base URL
    return 'https://api.yourdomain.com/api/v1';
}
```

### Styling

All pages use Tailwind CSS via CDN. You can customize colors by modifying the CSS variables in the `<style>` sections:

```css
:root {
    --brand-primary: #33a1df; /* Change to your brand color */
}
```

## PHP 7.1 Compatibility

These pages are fully compatible with PHP 7.1+ and use:
- Traditional array syntax `[]`
- `isset()` and `empty()` checks
- Basic string functions
- No PHP 7.2+ features (no typed properties, no null coalescing assignment, etc.)

## Error Handling

All pages include error handling:
- API connection errors
- Invalid responses
- Missing data
- Display user-friendly error messages

## Notes

- These pages are independent of Laravel and can be hosted separately
- They require network access to the Laravel API
- All data is fetched via REST API calls
- No authentication is implemented in these standalone pages (add if needed)

