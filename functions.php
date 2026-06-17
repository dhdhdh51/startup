<?php
/**
 * Bharat SEO CRM - Core Functions
 * 
 * Shared helper functions used across the application.
 */

require_once __DIR__ . '/config.php';

/**
 * Get PDO database connection (singleton pattern)
 */
function db(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    
    return $pdo;
}

/**
 * Require user to be logged in. Redirect to login if not authenticated.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        redirect('login.php');
    }
}

/**
 * Generate and store a CSRF token in the session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the session token.
 */
function verify_csrf(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output for HTML display.
 */
function sanitize(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get a setting value from the database.
 */
function getSetting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

/**
 * Set a setting value in the database.
 */
function setSetting(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
         ON DUPLICATE KEY UPDATE setting_value = :value2"
    );
    $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
}

/**
 * Log an activity to the activity_logs table.
 */
function logActivity(string $action, ?string $details = null): void
{
    $stmt = db()->prepare("INSERT INTO activity_logs (action, details, created_at) VALUES (:action, :details, NOW())");
    $stmt->execute([':action' => $action, ':details' => $details]);
}

/**
 * Redirect to a URL.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Format a date for display.
 */
function formatDate(?string $date, string $format = 'd M Y, h:i A'): string
{
    if (empty($date)) {
        return '-';
    }
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Get the HTML badge for a lead status.
 */
function getLeadStatusBadge(string $status): string
{
    $badges = [
        'new' => '<span class="badge badge-info">New</span>',
        'contacted' => '<span class="badge badge-primary">Contacted</span>',
        'interested' => '<span class="badge badge-warning">Interested</span>',
        'proposal_sent' => '<span class="badge badge-secondary">Proposal Sent</span>',
        'converted' => '<span class="badge badge-success">Converted</span>',
        'not_interested' => '<span class="badge badge-danger">Not Interested</span>',
        'follow_up' => '<span class="badge badge-dark">Follow Up</span>',
    ];
    return $badges[$status] ?? '<span class="badge badge-light">' . sanitize($status) . '</span>';
}

/**
 * Get the HTML badge for an opportunity level.
 */
function getOpportunityBadge(?string $level): string
{
    if (empty($level)) {
        return '<span class="badge badge-light">Not Assessed</span>';
    }
    $badges = [
        'low' => '<span class="badge badge-secondary">Low</span>',
        'medium' => '<span class="badge badge-info">Medium</span>',
        'high' => '<span class="badge badge-warning">High</span>',
        'very_high' => '<span class="badge badge-success">Very High</span>',
    ];
    return $badges[$level] ?? '<span class="badge badge-light">' . sanitize($level) . '</span>';
}

/**
 * Calculate audit score from audit data (stub - to be implemented with actual logic).
 */
function calculateAuditScore(array $auditData): int
{
    $score = 0;
    $maxScore = 100;
    $checks = 0;
    $passed = 0;

    $booleanFields = [
        'has_website', 'is_mobile_friendly', 'has_ssl', 'has_meta_title',
        'has_meta_description', 'has_h1_tag', 'has_schema_markup', 'has_sitemap',
        'has_robots_txt', 'has_analytics', 'has_google_tag_manager',
        'has_gmb_listing', 'gmb_is_verified', 'gmb_has_posts', 'gmb_has_products',
        'gmb_has_services', 'gmb_has_reviews', 'gmb_has_website_link',
        'gmb_has_phone', 'gmb_has_hours', 'gmb_has_description', 'has_social_media'
    ];

    foreach ($booleanFields as $field) {
        if (isset($auditData[$field])) {
            $checks++;
            if ($auditData[$field]) {
                $passed++;
            }
        }
    }

    if ($checks > 0) {
        $score = (int) round(($passed / $checks) * $maxScore);
    }

    return $score;
}

/**
 * Generate pagination HTML and data.
 */
function paginate(int $totalItems, int $currentPage, int $perPage = ITEMS_PER_PAGE, string $baseUrl = '?'): array
{
    $totalPages = (int) ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    $html = '';
    if ($totalPages > 1) {
        $html .= '<nav class="pagination-nav"><ul class="pagination">';
        
        // Previous
        if ($currentPage > 1) {
            $html .= '<li><a href="' . $baseUrl . 'page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
        }
        
        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        if ($start > 1) {
            $html .= '<li><a href="' . $baseUrl . 'page=1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
            $html .= '<li><a href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
        }
        
        // Next
        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . $baseUrl . 'page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
        }
        
        $html .= '</ul></nav>';
    }

    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'offset' => $offset,
        'html' => $html,
    ];
}
