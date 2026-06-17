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

/**
 * Search Google Places API for local businesses.
 * 
 * @param array $params Search parameters: niche, city, state, country, radius, max_results, min_rating, max_rating, has_website, has_phone
 * @return array Results with 'success' boolean, 'data' array of normalized leads, 'error' string if failed
 */
function searchGooglePlaces(array $params): array
{
    $apiKey = getSetting('google_maps_api_key');
    if (empty($apiKey)) {
        return ['success' => false, 'data' => [], 'error' => 'Google Maps API key not configured. Go to Settings to add it.'];
    }

    $query = trim(($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ', ' . ($params['state'] ?? '') . ', ' . ($params['country'] ?? 'India'));
    $radius = (int)($params['radius'] ?? 5000);
    $maxResults = min((int)($params['max_results'] ?? 20), 60);

    $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?'
        . http_build_query([
            'query' => $query,
            'radius' => $radius,
            'key' => $apiKey,
        ]);

    $results = [];
    $nextPageToken = null;
    $fetched = 0;

    do {
        $fetchUrl = $url;
        if ($nextPageToken) {
            $fetchUrl .= '&pagetoken=' . urlencode($nextPageToken);
            // Google requires a short delay before using next_page_token
            sleep(2);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fetchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'data' => $results, 'error' => 'cURL error: ' . $curlError];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'data' => $results, 'error' => 'API returned HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data || ($data['status'] ?? '') === 'REQUEST_DENIED') {
            return ['success' => false, 'data' => $results, 'error' => 'API error: ' . ($data['error_message'] ?? 'Unknown error')];
        }

        foreach (($data['results'] ?? []) as $place) {
            if ($fetched >= $maxResults) break;

            $rating = (float)($place['rating'] ?? 0);
            $minRating = (float)($params['min_rating'] ?? 0);
            $maxRating = (float)($params['max_rating'] ?? 5);
            if ($rating < $minRating || $rating > $maxRating) continue;

            $lead = [
                'business_name' => $place['name'] ?? '',
                'category' => implode(', ', array_slice($place['types'] ?? [], 0, 3)),
                'phone' => '',
                'email' => '',
                'website_url' => '',
                'google_maps_url' => '',
                'address' => $place['formatted_address'] ?? '',
                'city' => $params['city'] ?? '',
                'state' => $params['state'] ?? '',
                'country' => $params['country'] ?? 'India',
                'rating' => $rating,
                'review_count' => (int)($place['user_ratings_total'] ?? 0),
                'latitude' => $place['geometry']['location']['lat'] ?? null,
                'longitude' => $place['geometry']['location']['lng'] ?? null,
                'source' => 'google_places',
                'source_id' => $place['place_id'] ?? '',
            ];

            $results[] = $lead;
            $fetched++;
        }

        $nextPageToken = $data['next_page_token'] ?? null;
    } while ($nextPageToken && $fetched < $maxResults);

    return ['success' => true, 'data' => $results, 'error' => ''];
}

/**
 * Search SerpAPI Google Maps endpoint for local businesses.
 * 
 * @param array $params Search parameters
 * @return array Results with 'success', 'data', 'error'
 */
function searchSerpAPI(array $params): array
{
    $apiKey = getSetting('serpapi_key');
    if (empty($apiKey)) {
        return ['success' => false, 'data' => [], 'error' => 'SerpAPI key not configured. Go to Settings to add it.'];
    }

    $query = trim(($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ', ' . ($params['state'] ?? '') . ', ' . ($params['country'] ?? 'India'));
    $maxResults = min((int)($params['max_results'] ?? 20), 100);

    $url = 'https://serpapi.com/search.json?' . http_build_query([
        'engine' => 'google_maps',
        'q' => $query,
        'type' => 'search',
        'api_key' => $apiKey,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'data' => [], 'error' => 'cURL error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'data' => [], 'error' => 'SerpAPI returned HTTP ' . $httpCode];
    }

    $data = json_decode($response, true);
    if (!$data) {
        return ['success' => false, 'data' => [], 'error' => 'Invalid JSON response from SerpAPI'];
    }

    if (isset($data['error'])) {
        return ['success' => false, 'data' => [], 'error' => 'SerpAPI error: ' . $data['error']];
    }

    $results = [];
    $places = $data['local_results'] ?? [];
    $fetched = 0;

    foreach ($places as $place) {
        if ($fetched >= $maxResults) break;

        $rating = (float)($place['rating'] ?? 0);
        $minRating = (float)($params['min_rating'] ?? 0);
        $maxRating = (float)($params['max_rating'] ?? 5);
        if ($rating < $minRating || $rating > $maxRating) continue;

        $lead = [
            'business_name' => $place['title'] ?? '',
            'category' => $place['type'] ?? '',
            'phone' => $place['phone'] ?? '',
            'email' => '',
            'website_url' => $place['website'] ?? '',
            'google_maps_url' => $place['link'] ?? '',
            'address' => $place['address'] ?? '',
            'city' => $params['city'] ?? '',
            'state' => $params['state'] ?? '',
            'country' => $params['country'] ?? 'India',
            'rating' => $rating,
            'review_count' => (int)($place['reviews'] ?? 0),
            'latitude' => $place['gps_coordinates']['latitude'] ?? null,
            'longitude' => $place['gps_coordinates']['longitude'] ?? null,
            'source' => 'serpapi',
            'source_id' => $place['place_id'] ?? ($place['data_id'] ?? ''),
        ];

        $results[] = $lead;
        $fetched++;
    }

    return ['success' => true, 'data' => $results, 'error' => ''];
}

/**
 * Search Apify actor API for business data.
 * 
 * @param array $params Search parameters
 * @return array Results with 'success', 'data', 'error'
 */
function searchApify(array $params): array
{
    $apiToken = getSetting('apify_token');
    if (empty($apiToken)) {
        return ['success' => false, 'data' => [], 'error' => 'Apify token not configured. Go to Settings to add it.'];
    }

    $actorId = getSetting('apify_actor_id') ?: 'compass/crawler-google-places';
    $query = trim(($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ', ' . ($params['state'] ?? '') . ', ' . ($params['country'] ?? 'India'));
    $maxResults = min((int)($params['max_results'] ?? 20), 100);

    $url = 'https://api.apify.com/v2/acts/' . urlencode($actorId) . '/run-sync-get-dataset-items?token=' . urlencode($apiToken);

    $payload = json_encode([
        'searchStringsArray' => [$query],
        'maxCrawledPlacesPerSearch' => $maxResults,
        'language' => 'en',
        'maxImages' => 0,
        'maxReviews' => 0,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'data' => [], 'error' => 'cURL error: ' . $curlError];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['success' => false, 'data' => [], 'error' => 'Apify returned HTTP ' . $httpCode];
    }

    $data = json_decode($response, true);
    if (!$data || !is_array($data)) {
        return ['success' => false, 'data' => [], 'error' => 'Invalid response from Apify'];
    }

    $results = [];
    foreach ($data as $place) {
        $rating = (float)($place['totalScore'] ?? $place['rating'] ?? 0);
        $minRating = (float)($params['min_rating'] ?? 0);
        $maxRating = (float)($params['max_rating'] ?? 5);
        if ($rating < $minRating || $rating > $maxRating) continue;

        $lead = [
            'business_name' => $place['title'] ?? $place['name'] ?? '',
            'category' => $place['categoryName'] ?? $place['category'] ?? '',
            'phone' => $place['phone'] ?? '',
            'email' => $place['email'] ?? '',
            'website_url' => $place['website'] ?? '',
            'google_maps_url' => $place['url'] ?? '',
            'address' => $place['address'] ?? $place['street'] ?? '',
            'city' => $place['city'] ?? $params['city'] ?? '',
            'state' => $place['state'] ?? $params['state'] ?? '',
            'country' => $place['countryCode'] ?? $params['country'] ?? 'India',
            'rating' => $rating,
            'review_count' => (int)($place['reviewsCount'] ?? $place['reviews'] ?? 0),
            'latitude' => $place['location']['lat'] ?? $place['latitude'] ?? null,
            'longitude' => $place['location']['lng'] ?? $place['longitude'] ?? null,
            'source' => 'apify',
            'source_id' => $place['placeId'] ?? ($place['cid'] ?? ''),
        ];

        if (!empty($lead['business_name'])) {
            $results[] = $lead;
        }
    }

    return ['success' => true, 'data' => $results, 'error' => ''];
}

/**
 * Check for duplicate lead in the database.
 * 
 * Checks by: phone number, website URL, business_name + city, source_id.
 * If duplicate found, updates missing fields where new data is better.
 * 
 * @param array $leadData Lead data to check
 * @return array ['is_duplicate' => bool, 'lead_id' => int|null, 'match_type' => string]
 */
function deduplicateLead(array $leadData): array
{
    $pdo = db();
    $result = ['is_duplicate' => false, 'lead_id' => null, 'match_type' => ''];

    // Check by source_id (most reliable - e.g., Google place_id)
    if (!empty($leadData['source_id']) && !empty($leadData['source'])) {
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE source = :source AND source_id = :source_id LIMIT 1");
        $stmt->execute([':source' => $leadData['source'], ':source_id' => $leadData['source_id']]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return ['is_duplicate' => true, 'lead_id' => (int)$existing, 'match_type' => 'source_id'];
        }
    }

    // Check by phone number
    if (!empty($leadData['phone'])) {
        $phone = preg_replace('/[^0-9+]/', '', $leadData['phone']);
        if (strlen($phone) >= 7) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', '') LIKE :phone LIMIT 1");
            $stmt->execute([':phone' => '%' . substr($phone, -10) . '%']);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                return ['is_duplicate' => true, 'lead_id' => (int)$existing, 'match_type' => 'phone'];
            }
        }
    }

    // Check by website URL
    if (!empty($leadData['website_url'])) {
        $website = rtrim(preg_replace('#^https?://(www\.)?#i', '', $leadData['website_url']), '/');
        if (!empty($website)) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE website_url LIKE :website LIMIT 1");
            $stmt->execute([':website' => '%' . $website . '%']);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                return ['is_duplicate' => true, 'lead_id' => (int)$existing, 'match_type' => 'website_url'];
            }
        }
    }

    // Check by business_name + city
    if (!empty($leadData['business_name']) && !empty($leadData['city'])) {
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE LOWER(business_name) = LOWER(:name) AND LOWER(city) = LOWER(:city) LIMIT 1");
        $stmt->execute([':name' => trim($leadData['business_name']), ':city' => trim($leadData['city'])]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return ['is_duplicate' => true, 'lead_id' => (int)$existing, 'match_type' => 'business_name_city'];
        }
    }

    return $result;
}

/**
 * Save a lead to the database with deduplication.
 * 
 * Calls deduplicateLead first, then either inserts new or updates existing.
 * 
 * @param array $leadData Lead data to save
 * @return array ['action' => 'inserted'|'updated'|'skipped', 'lead_id' => int|null]
 */
function saveLead(array $leadData): array
{
    $pdo = db();
    $dupCheck = deduplicateLead($leadData);

    if ($dupCheck['is_duplicate'] && $dupCheck['lead_id']) {
        // Update missing fields on existing lead
        $existingStmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
        $existingStmt->execute([':id' => $dupCheck['lead_id']]);
        $existing = $existingStmt->fetch();

        if (!$existing) {
            return ['action' => 'skipped', 'lead_id' => null];
        }

        $updates = [];
        $params = [':id' => $dupCheck['lead_id']];
        $fieldsToUpdate = ['phone', 'email', 'website_url', 'google_maps_url', 'address', 'category', 'rating', 'review_count', 'latitude', 'longitude'];

        foreach ($fieldsToUpdate as $field) {
            if (!empty($leadData[$field]) && empty($existing[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $leadData[$field];
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['action' => 'updated', 'lead_id' => $dupCheck['lead_id']];
        }

        return ['action' => 'skipped', 'lead_id' => $dupCheck['lead_id']];
    }

    // Insert new lead
    $stmt = $pdo->prepare(
        "INSERT INTO leads (business_name, category, phone, email, website_url, google_maps_url, address, city, state, country, rating, review_count, latitude, longitude, source, source_id, status, created_at, updated_at)
         VALUES (:business_name, :category, :phone, :email, :website_url, :google_maps_url, :address, :city, :state, :country, :rating, :review_count, :latitude, :longitude, :source, :source_id, 'new', NOW(), NOW())"
    );
    $stmt->execute([
        ':business_name' => $leadData['business_name'] ?? '',
        ':category' => $leadData['category'] ?? null,
        ':phone' => $leadData['phone'] ?? null,
        ':email' => $leadData['email'] ?? null,
        ':website_url' => $leadData['website_url'] ?? null,
        ':google_maps_url' => $leadData['google_maps_url'] ?? null,
        ':address' => $leadData['address'] ?? null,
        ':city' => $leadData['city'] ?? null,
        ':state' => $leadData['state'] ?? null,
        ':country' => $leadData['country'] ?? 'India',
        ':rating' => !empty($leadData['rating']) ? (float)$leadData['rating'] : null,
        ':review_count' => (int)($leadData['review_count'] ?? 0),
        ':latitude' => $leadData['latitude'] ?? null,
        ':longitude' => $leadData['longitude'] ?? null,
        ':source' => $leadData['source'] ?? null,
        ':source_id' => $leadData['source_id'] ?? null,
    ]);

    return ['action' => 'inserted', 'lead_id' => (int)$pdo->lastInsertId()];
}

/**
 * Log a search operation to the lead_searches table.
 * 
 * @param array $searchParams The search parameters used
 * @param array $results Summary with total_found, total_saved, total_duplicates
 */
function saveSearchLog(array $searchParams, array $results): void
{
    $stmt = db()->prepare(
        "INSERT INTO lead_searches (niche, city, state, country, source, max_results, total_found, total_saved, total_duplicates, created_at)
         VALUES (:niche, :city, :state, :country, :source, :max_results, :total_found, :total_saved, :total_duplicates, NOW())"
    );
    $stmt->execute([
        ':niche' => $searchParams['niche'] ?? '',
        ':city' => $searchParams['city'] ?? '',
        ':state' => $searchParams['state'] ?? '',
        ':country' => $searchParams['country'] ?? 'India',
        ':source' => $searchParams['source'] ?? '',
        ':max_results' => (int)($searchParams['max_results'] ?? 20),
        ':total_found' => (int)($results['total_found'] ?? 0),
        ':total_saved' => (int)($results['total_saved'] ?? 0),
        ':total_duplicates' => (int)($results['total_duplicates'] ?? 0),
    ]);
}
