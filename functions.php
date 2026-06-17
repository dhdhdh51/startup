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
 * Calculate audit score from website, Google, and social audit data.
 *
 * Scoring breakdown (100 total):
 * - Website presence: 15 pts
 * - Website SEO basics: 20 pts
 * - Mobile readiness: 10 pts
 * - Local conversion elements: 20 pts
 * - Google profile strength: 20 pts
 * - Social presence: 10 pts
 * - Lead readiness: 5 pts
 *
 * @param array $websiteAudit Website audit data
 * @param array $googleAudit Google profile audit data
 * @param array $socialAudit Social media audit data
 * @return array ['total' => int, 'breakdown' => array]
 */
function calculateAuditScore(array $websiteAudit, array $googleAudit = [], array $socialAudit = []): array
{
    $breakdown = [
        'website_presence' => 0,
        'website_seo' => 0,
        'mobile_readiness' => 0,
        'local_conversion' => 0,
        'google_profile' => 0,
        'social_presence' => 0,
        'lead_readiness' => 0,
    ];

    // Website presence (15 pts)
    if (!empty($websiteAudit['is_reachable'])) {
        $breakdown['website_presence'] += 5;
        if (!empty($websiteAudit['has_ssl'])) $breakdown['website_presence'] += 5;
        if (!empty($websiteAudit['http_status']) && $websiteAudit['http_status'] === 200) $breakdown['website_presence'] += 3;
        if (!empty($websiteAudit['load_time']) && $websiteAudit['load_time'] < 5) $breakdown['website_presence'] += 2;
    }
    $breakdown['website_presence'] = min(15, $breakdown['website_presence']);

    // Website SEO basics (20 pts)
    if (!empty($websiteAudit['is_reachable'])) {
        if (!empty($websiteAudit['has_title'])) $breakdown['website_seo'] += 3;
        if (!empty($websiteAudit['has_meta_description'])) $breakdown['website_seo'] += 3;
        if (!empty($websiteAudit['has_h1'])) $breakdown['website_seo'] += 2;
        if (!empty($websiteAudit['has_h2'])) $breakdown['website_seo'] += 1;
        if (!empty($websiteAudit['has_canonical'])) $breakdown['website_seo'] += 2;
        if (!empty($websiteAudit['has_robots_meta'])) $breakdown['website_seo'] += 1;
        if (!empty($websiteAudit['has_og_tags'])) $breakdown['website_seo'] += 2;
        if (!empty($websiteAudit['has_twitter_cards'])) $breakdown['website_seo'] += 1;
        if (!empty($websiteAudit['has_json_ld'])) $breakdown['website_seo'] += 3;
        if (!empty($websiteAudit['has_local_business_schema'])) $breakdown['website_seo'] += 2;
    }
    $breakdown['website_seo'] = min(20, $breakdown['website_seo']);

    // Mobile readiness (10 pts)
    if (!empty($websiteAudit['is_reachable'])) {
        if (!empty($websiteAudit['has_viewport'])) $breakdown['mobile_readiness'] += 5;
        if (!empty($websiteAudit['page_size']) && $websiteAudit['page_size'] < 3000000) $breakdown['mobile_readiness'] += 3;
        if (!empty($websiteAudit['has_image_alt_tags'])) $breakdown['mobile_readiness'] += 2;
    }
    $breakdown['mobile_readiness'] = min(10, $breakdown['mobile_readiness']);

    // Local conversion elements (20 pts)
    if (!empty($websiteAudit['is_reachable'])) {
        if (!empty($websiteAudit['has_contact_form'])) $breakdown['local_conversion'] += 4;
        if (!empty($websiteAudit['has_whatsapp_link'])) $breakdown['local_conversion'] += 4;
        if (!empty($websiteAudit['has_phone_number'])) $breakdown['local_conversion'] += 4;
        if (!empty($websiteAudit['has_email'])) $breakdown['local_conversion'] += 3;
        if (!empty($websiteAudit['has_google_map'])) $breakdown['local_conversion'] += 5;
    }
    $breakdown['local_conversion'] = min(20, $breakdown['local_conversion']);

    // Google profile strength (20 pts)
    if (!empty($googleAudit['has_listing'])) {
        $breakdown['google_profile'] += 4;
        if (!empty($googleAudit['has_phone'])) $breakdown['google_profile'] += 3;
        if (!empty($googleAudit['has_website'])) $breakdown['google_profile'] += 3;
        if (!empty($googleAudit['has_address'])) $breakdown['google_profile'] += 2;
        if (!empty($googleAudit['has_category'])) $breakdown['google_profile'] += 2;
        if (!empty($googleAudit['rating']) && $googleAudit['rating'] >= 4.0) {
            $breakdown['google_profile'] += 3;
        } elseif (!empty($googleAudit['rating']) && $googleAudit['rating'] >= 3.0) {
            $breakdown['google_profile'] += 1;
        }
        if (!empty($googleAudit['review_count']) && $googleAudit['review_count'] >= 10) {
            $breakdown['google_profile'] += 3;
        } elseif (!empty($googleAudit['review_count']) && $googleAudit['review_count'] >= 3) {
            $breakdown['google_profile'] += 1;
        }
    }
    $breakdown['google_profile'] = min(20, $breakdown['google_profile']);

    // Social presence (10 pts)
    $socialCount = 0;
    if (!empty($socialAudit['instagram'])) $socialCount++;
    if (!empty($socialAudit['facebook'])) $socialCount++;
    if (!empty($socialAudit['youtube'])) $socialCount++;
    if (!empty($socialAudit['linkedin'])) $socialCount++;
    if (!empty($socialAudit['twitter'])) $socialCount++;
    if ($socialCount >= 4) {
        $breakdown['social_presence'] = 10;
    } elseif ($socialCount === 3) {
        $breakdown['social_presence'] = 7;
    } elseif ($socialCount === 2) {
        $breakdown['social_presence'] = 5;
    } elseif ($socialCount === 1) {
        $breakdown['social_presence'] = 3;
    }

    // Lead readiness (5 pts)
    $readiness = 0;
    if (!empty($googleAudit['has_phone'])) $readiness += 2;
    if (!empty($websiteAudit['has_phone_number']) || !empty($websiteAudit['has_whatsapp_link'])) $readiness += 2;
    if (!empty($websiteAudit['has_email']) || !empty($websiteAudit['has_contact_form'])) $readiness += 1;
    $breakdown['lead_readiness'] = min(5, $readiness);

    $total = array_sum($breakdown);
    $total = min(100, max(0, $total));

    return ['total' => $total, 'breakdown' => $breakdown];
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

/**
 * Audit a website by fetching its homepage and analyzing HTML content.
 *
 * Checks 30 audit points including SEO basics, mobile readiness,
 * local conversion elements, and technical aspects.
 *
 * @param string $url The website URL to audit
 * @return array Structured audit findings
 */
function auditWebsite(string $url): array
{
    $result = [
        'url' => $url,
        'is_reachable' => false,
        'http_status' => 0,
        'has_ssl' => false,
        'has_title' => false,
        'title' => '',
        'has_meta_description' => false,
        'meta_description' => '',
        'has_meta_keywords' => false,
        'has_h1' => false,
        'h1_count' => 0,
        'has_h2' => false,
        'h2_count' => 0,
        'has_viewport' => false,
        'has_canonical' => false,
        'has_robots_meta' => false,
        'has_og_tags' => false,
        'has_twitter_cards' => false,
        'has_json_ld' => false,
        'has_local_business_schema' => false,
        'has_contact_form' => false,
        'has_whatsapp_link' => false,
        'has_phone_number' => false,
        'has_email' => false,
        'has_google_map' => false,
        'has_image_alt_tags' => false,
        'page_size' => 0,
        'load_time' => 0,
        'internal_links' => 0,
        'external_links' => 0,
        'social_links' => [],
        'error' => '',
    ];

    // Normalize URL
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    // Check SSL
    $result['has_ssl'] = (stripos($url, 'https://') === 0);

    $timeout = (int)(getSetting('curl_timeout', 10) ?: 10);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);

    $startTime = microtime(true);
    $html = curl_exec($ch);
    $endTime = microtime(true);

    $result['load_time'] = round($endTime - $startTime, 2);
    $result['http_status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($html === false || $result['http_status'] === 0) {
        $result['error'] = 'Could not reach website: ' . $curlError;
        return $result;
    }

    $result['is_reachable'] = ($result['http_status'] >= 200 && $result['http_status'] < 400);

    if (!$result['is_reachable']) {
        $result['error'] = 'Website returned HTTP ' . $result['http_status'];
        return $result;
    }

    // Check if final URL is HTTPS
    if (stripos($finalUrl, 'https://') === 0) {
        $result['has_ssl'] = true;
    }

    $result['page_size'] = strlen($html);
    $htmlLower = strtolower($html);

    // Title tag
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $result['has_title'] = !empty(trim(strip_tags($m[1])));
        $result['title'] = trim(strip_tags($m[1]));
    }

    // Meta description
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/is', $html, $m)
        || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>/is', $html, $m)) {
        $result['has_meta_description'] = !empty(trim($m[1]));
        $result['meta_description'] = trim($m[1]);
    }

    // Meta keywords
    if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]*>/is', $html)) {
        $result['has_meta_keywords'] = true;
    }

    // H1 tags
    preg_match_all('/<h1[^>]*>/is', $html, $h1Matches);
    $result['h1_count'] = count($h1Matches[0]);
    $result['has_h1'] = $result['h1_count'] > 0;

    // H2 tags
    preg_match_all('/<h2[^>]*>/is', $html, $h2Matches);
    $result['h2_count'] = count($h2Matches[0]);
    $result['has_h2'] = $result['h2_count'] > 0;

    // Viewport meta tag (mobile)
    $result['has_viewport'] = (bool)preg_match('/<meta[^>]+name=["\']viewport["\'][^>]*>/is', $html);

    // Canonical tag
    $result['has_canonical'] = (bool)preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*>/is', $html);

    // Robots meta
    $result['has_robots_meta'] = (bool)preg_match('/<meta[^>]+name=["\']robots["\'][^>]*>/is', $html);

    // Open Graph tags
    $result['has_og_tags'] = (bool)preg_match('/<meta[^>]+property=["\']og:/is', $html);

    // Twitter Card tags
    $result['has_twitter_cards'] = (bool)preg_match('/<meta[^>]+name=["\']twitter:/is', $html);

    // JSON-LD schema
    $result['has_json_ld'] = (bool)preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>/is', $html);

    // Local Business schema
    $result['has_local_business_schema'] = (bool)preg_match('/LocalBusiness|local_business/i', $html);

    // Contact form detection
    $result['has_contact_form'] = (bool)preg_match('/<form[^>]*>/is', $html);

    // WhatsApp link
    $result['has_whatsapp_link'] = (bool)preg_match('/wa\.me|whatsapp\.com|whatsapp/i', $html);

    // Phone number detection
    $result['has_phone_number'] = (bool)preg_match('/tel:|(\+91|0)\s*[\d\s\-]{9,}/i', $html);

    // Email detection
    $result['has_email'] = (bool)preg_match('/mailto:|[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/i', $html);

    // Google Map embed
    $result['has_google_map'] = (bool)preg_match('/maps\.google\.com|google\.com\/maps|maps\.googleapis/i', $html);

    // Image alt tags check
    preg_match_all('/<img[^>]*>/is', $html, $imgMatches);
    $totalImages = count($imgMatches[0]);
    $imagesWithAlt = 0;
    if ($totalImages > 0) {
        foreach ($imgMatches[0] as $img) {
            if (preg_match('/alt=["\'][^"\']+["\']/', $img)) {
                $imagesWithAlt++;
            }
        }
        $result['has_image_alt_tags'] = ($imagesWithAlt >= ($totalImages * 0.5));
    } else {
        $result['has_image_alt_tags'] = true;
    }

    // Links analysis
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/is', $html, $linkMatches);
    $parsedBase = parse_url($url);
    $baseDomain = $parsedBase['host'] ?? '';
    foreach ($linkMatches[1] as $link) {
        $parsed = parse_url($link);
        if (empty($parsed['host']) || $parsed['host'] === $baseDomain || str_ends_with($parsed['host'], '.' . $baseDomain)) {
            $result['internal_links']++;
        } else {
            $result['external_links']++;
        }
    }

    // Social links extraction (also handled by auditSocial, but captured here too)
    $socialPatterns = [
        'instagram' => '/https?:\/\/(www\.)?instagram\.com\/[^\s"\'<>]+/i',
        'facebook' => '/https?:\/\/(www\.)?facebook\.com\/[^\s"\'<>]+/i',
        'youtube' => '/https?:\/\/(www\.)?youtube\.com\/[^\s"\'<>]+/i',
        'linkedin' => '/https?:\/\/(www\.)?linkedin\.com\/[^\s"\'<>]+/i',
        'twitter' => '/https?:\/\/(www\.)?(twitter\.com|x\.com)\/[^\s"\'<>]+/i',
    ];
    foreach ($socialPatterns as $platform => $pattern) {
        if (preg_match($pattern, $html, $socialMatch)) {
            $result['social_links'][$platform] = $socialMatch[0];
        }
    }

    return $result;
}

/**
 * Audit a lead's Google Business Profile based on available lead data.
 *
 * @param array $leadData The lead record from database
 * @return array Google profile audit findings
 */
function auditGoogleProfile(array $leadData): array
{
    $result = [
        'has_listing' => false,
        'has_phone' => false,
        'has_website' => false,
        'has_address' => false,
        'has_category' => false,
        'rating' => 0,
        'review_count' => 0,
        'has_hours' => false,
    ];

    // Check if Google Maps URL exists (indicates a listing)
    if (!empty($leadData['google_maps_url'])) {
        $result['has_listing'] = true;
    }

    // Check phone
    if (!empty($leadData['phone'])) {
        $result['has_phone'] = true;
    }

    // Check website
    if (!empty($leadData['website_url'])) {
        $result['has_website'] = true;
    }

    // Check address
    if (!empty($leadData['address'])) {
        $result['has_address'] = true;
    }

    // Check category
    if (!empty($leadData['category'])) {
        $result['has_category'] = true;
    }

    // Rating
    if (!empty($leadData['rating'])) {
        $result['rating'] = (float)$leadData['rating'];
    }

    // Review count
    if (!empty($leadData['review_count'])) {
        $result['review_count'] = (int)$leadData['review_count'];
    }

    return $result;
}

/**
 * Find social media links in website HTML content.
 *
 * @param string $websiteHtml The HTML content of the website
 * @return array Found social media links keyed by platform
 */
function auditSocial(string $websiteHtml): array
{
    $result = [
        'instagram' => '',
        'facebook' => '',
        'youtube' => '',
        'linkedin' => '',
        'twitter' => '',
    ];

    if (empty($websiteHtml)) {
        return $result;
    }

    $patterns = [
        'instagram' => '/https?:\/\/(www\.)?instagram\.com\/[a-zA-Z0-9._\-\/]+/i',
        'facebook' => '/https?:\/\/(www\.)?facebook\.com\/[a-zA-Z0-9._\-\/]+/i',
        'youtube' => '/https?:\/\/(www\.)?youtube\.com\/(channel|c|user|@)[a-zA-Z0-9._\-\/]+/i',
        'linkedin' => '/https?:\/\/(www\.)?linkedin\.com\/(company|in)\/[a-zA-Z0-9._\-\/]+/i',
        'twitter' => '/https?:\/\/(www\.)?(twitter\.com|x\.com)\/[a-zA-Z0-9._\-\/]+/i',
    ];

    foreach ($patterns as $platform => $pattern) {
        if (preg_match($pattern, $websiteHtml, $match)) {
            $result[$platform] = rtrim($match[0], '/');
        }
    }

    return $result;
}

/**
 * Get opportunity level label based on audit score.
 *
 * @param int $score Audit score (0-100)
 * @return string Opportunity level label
 */
function getOpportunityLevel(int $score): string
{
    if ($score <= 30) {
        return 'Very High Opportunity';
    } elseif ($score <= 50) {
        return 'High Opportunity';
    } elseif ($score <= 70) {
        return 'Medium Opportunity';
    } elseif ($score <= 85) {
        return 'Low Opportunity';
    } else {
        return 'Strong Online Presence';
    }
}

/**
 * Generate human-readable problem list based on audit findings.
 *
 * @param array $auditData Combined audit data (website + google + social)
 * @return array List of problem strings
 */
function getProblems(array $auditData): array
{
    $problems = [];
    $website = $auditData['website'] ?? [];
    $google = $auditData['google'] ?? [];
    $social = $auditData['social'] ?? [];

    // Website problems
    if (empty($website['is_reachable'])) {
        $problems[] = 'No website found or website is not reachable';
    } else {
        if (empty($website['has_ssl'])) {
            $problems[] = 'Website does not use HTTPS (no SSL certificate)';
        }
        if (empty($website['has_title'])) {
            $problems[] = 'Missing page title tag';
        }
        if (empty($website['has_meta_description'])) {
            $problems[] = 'Missing meta description';
        }
        if (empty($website['has_h1'])) {
            $problems[] = 'Missing H1 heading tag';
        }
        if (empty($website['has_viewport'])) {
            $problems[] = 'Website is not mobile-friendly (no viewport meta tag)';
        }
        if (empty($website['has_canonical'])) {
            $problems[] = 'Missing canonical tag';
        }
        if (empty($website['has_og_tags'])) {
            $problems[] = 'Missing Open Graph tags for social sharing';
        }
        if (empty($website['has_json_ld'])) {
            $problems[] = 'No structured data (JSON-LD schema) found';
        }
        if (empty($website['has_local_business_schema'])) {
            $problems[] = 'No Local Business schema markup';
        }
        if (empty($website['has_contact_form'])) {
            $problems[] = 'No contact form found on website';
        }
        if (empty($website['has_whatsapp_link'])) {
            $problems[] = 'No WhatsApp link for instant communication';
        }
        if (empty($website['has_phone_number'])) {
            $problems[] = 'No phone number displayed on website';
        }
        if (empty($website['has_email'])) {
            $problems[] = 'No email address found on website';
        }
        if (empty($website['has_google_map'])) {
            $problems[] = 'No Google Map embed for location visibility';
        }
        if (empty($website['has_image_alt_tags'])) {
            $problems[] = 'Images missing alt text for accessibility and SEO';
        }
        if (!empty($website['load_time']) && $website['load_time'] > 5) {
            $problems[] = 'Website loads slowly (over 5 seconds)';
        }
        if (!empty($website['page_size']) && $website['page_size'] > 3000000) {
            $problems[] = 'Website page size is too large (over 3MB)';
        }
    }

    // Google profile problems
    if (empty($google['has_listing'])) {
        $problems[] = 'No Google Business listing found';
    } else {
        if (empty($google['has_phone'])) {
            $problems[] = 'Google listing missing phone number';
        }
        if (empty($google['has_website'])) {
            $problems[] = 'Google listing missing website link';
        }
        if (empty($google['has_address'])) {
            $problems[] = 'Google listing missing address';
        }
        if (empty($google['rating']) || $google['rating'] < 3.0) {
            $problems[] = 'Google rating is below 3.0 or has no rating';
        }
        if (empty($google['review_count']) || $google['review_count'] < 3) {
            $problems[] = 'Very few Google reviews (less than 3)';
        }
    }

    // Social presence problems
    $socialCount = 0;
    if (!empty($social['instagram'])) $socialCount++;
    if (!empty($social['facebook'])) $socialCount++;
    if (!empty($social['youtube'])) $socialCount++;
    if (!empty($social['linkedin'])) $socialCount++;
    if (!empty($social['twitter'])) $socialCount++;

    if ($socialCount === 0) {
        $problems[] = 'No social media presence detected';
    } elseif ($socialCount < 3) {
        $problems[] = 'Limited social media presence (only ' . $socialCount . ' platform' . ($socialCount > 1 ? 's' : '') . ')';
    }

    return $problems;
}

/**
 * Generate recommendations based on audit findings.
 *
 * @param array $auditData Combined audit data (website + google + social)
 * @return array List of recommendation strings
 */
function getRecommendations(array $auditData): array
{
    $recommendations = [];
    $website = $auditData['website'] ?? [];
    $google = $auditData['google'] ?? [];
    $social = $auditData['social'] ?? [];

    // Website recommendations
    if (empty($website['is_reachable'])) {
        $recommendations[] = 'Create a professional website with SEO-optimized content and local business information';
        $recommendations[] = 'Register a domain name that includes your business name or location keywords';
    } else {
        if (empty($website['has_ssl'])) {
            $recommendations[] = 'Install an SSL certificate to enable HTTPS and improve trust signals';
        }
        if (empty($website['has_title']) || empty($website['has_meta_description'])) {
            $recommendations[] = 'Optimize meta tags (title and description) with target keywords and location';
        }
        if (empty($website['has_h1'])) {
            $recommendations[] = 'Add a clear H1 heading with your primary keyword on each page';
        }
        if (empty($website['has_viewport'])) {
            $recommendations[] = 'Make website mobile-responsive with proper viewport configuration';
        }
        if (empty($website['has_json_ld']) || empty($website['has_local_business_schema'])) {
            $recommendations[] = 'Add Local Business structured data (JSON-LD schema) for rich search results';
        }
        if (empty($website['has_og_tags'])) {
            $recommendations[] = 'Add Open Graph meta tags for better social media sharing previews';
        }
        if (empty($website['has_contact_form'])) {
            $recommendations[] = 'Add a contact form for easy customer inquiries';
        }
        if (empty($website['has_whatsapp_link'])) {
            $recommendations[] = 'Add a WhatsApp click-to-chat button for instant customer communication';
        }
        if (empty($website['has_phone_number'])) {
            $recommendations[] = 'Display phone number prominently with click-to-call functionality';
        }
        if (empty($website['has_google_map'])) {
            $recommendations[] = 'Embed Google Map showing your business location for local SEO';
        }
        if (empty($website['has_image_alt_tags'])) {
            $recommendations[] = 'Add descriptive alt text to all images for SEO and accessibility';
        }
        if (!empty($website['load_time']) && $website['load_time'] > 5) {
            $recommendations[] = 'Optimize website speed by compressing images and enabling caching';
        }
    }

    // Google profile recommendations
    if (empty($google['has_listing'])) {
        $recommendations[] = 'Create and verify a Google Business Profile for local search visibility';
    } else {
        if (empty($google['has_phone'])) {
            $recommendations[] = 'Add phone number to Google Business Profile';
        }
        if (empty($google['has_website'])) {
            $recommendations[] = 'Add website URL to Google Business Profile';
        }
        if (empty($google['review_count']) || $google['review_count'] < 10) {
            $recommendations[] = 'Actively request Google reviews from satisfied customers (aim for 10+)';
        }
        if (empty($google['rating']) || $google['rating'] < 4.0) {
            $recommendations[] = 'Focus on customer satisfaction to improve Google rating above 4.0';
        }
    }

    // Social recommendations
    $socialCount = 0;
    if (!empty($social['instagram'])) $socialCount++;
    if (!empty($social['facebook'])) $socialCount++;
    if (!empty($social['youtube'])) $socialCount++;
    if (!empty($social['linkedin'])) $socialCount++;
    if (!empty($social['twitter'])) $socialCount++;

    if ($socialCount === 0) {
        $recommendations[] = 'Create business profiles on Instagram, Facebook, and YouTube';
    } elseif ($socialCount < 3) {
        $missing = [];
        if (empty($social['instagram'])) $missing[] = 'Instagram';
        if (empty($social['facebook'])) $missing[] = 'Facebook';
        if (empty($social['youtube'])) $missing[] = 'YouTube';
        if (!empty($missing)) {
            $recommendations[] = 'Expand social media presence to: ' . implode(', ', array_slice($missing, 0, 3));
        }
    }

    return $recommendations;
}

/**
 * Get package recommendation based on score and website presence.
 *
 * @param int $score Audit score (0-100)
 * @param bool $hasWebsite Whether the business has a website
 * @return array Package recommendation with name, price, and description
 */
function getPackageRecommendation(int $score, bool $hasWebsite): array
{
    if (!$hasWebsite) {
        return [
            'name' => 'Bharat Starter Package',
            'price' => 'Rs.2,999',
            'recurring' => '',
            'description' => 'Professional website creation with SEO foundation, Google Business setup, and basic online presence.',
        ];
    }

    if ($score < 50) {
        return [
            'name' => 'Bharat Growth Package',
            'price' => 'Rs.6,999',
            'recurring' => 'Rs.999/month',
            'description' => 'Complete website overhaul, advanced SEO optimization, Google Maps SEO, social media setup, and monthly maintenance.',
        ];
    }

    if ($score <= 70) {
        return [
            'name' => 'Bharat Starter SEO Fix Package',
            'price' => 'Rs.2,999',
            'recurring' => '',
            'description' => 'Targeted SEO fixes, meta optimization, schema markup, and local SEO improvements.',
        ];
    }

    return [
        'name' => 'Bharat Pro Package',
        'price' => 'Rs.14,999',
        'recurring' => 'Rs.2,999/month',
        'description' => 'Premium SEO services, content marketing, advanced analytics, competitor monitoring, and dedicated account management.',
    ];
}

/**
 * Perform a full audit on a lead: website audit, Google profile audit,
 * social media audit, score calculation, and save results to database.
 *
 * @param int $leadId The lead ID to audit
 * @return array ['success' => bool, 'score' => int, 'error' => string]
 */
function performFullAudit(int $leadId): array
{
    $pdo = db();

    // Fetch lead
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $leadId]);
    $lead = $stmt->fetch();

    if (!$lead) {
        return ['success' => false, 'score' => 0, 'error' => 'Lead not found'];
    }

    $websiteAudit = [];
    $websiteHtml = '';
    $googleAudit = [];
    $socialAudit = ['instagram' => '', 'facebook' => '', 'youtube' => '', 'linkedin' => '', 'twitter' => ''];

    // Run website audit if URL exists
    if (!empty($lead['website_url'])) {
        try {
            $websiteAudit = auditWebsite($lead['website_url']);
        } catch (\Throwable $e) {
            $websiteAudit = ['is_reachable' => false, 'error' => $e->getMessage()];
        }

        // If website was reachable, fetch HTML for social audit
        if (!empty($websiteAudit['is_reachable'])) {
            // Use social links found during website audit if available
            if (!empty($websiteAudit['social_links'])) {
                $socialAudit = array_merge($socialAudit, $websiteAudit['social_links']);
            } else {
                // Re-fetch for social audit (lightweight)
                $timeout = (int)(getSetting('curl_timeout', 10) ?: 10);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $lead['website_url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BharatSEO Audit Bot)',
                ]);
                $websiteHtml = curl_exec($ch);
                curl_close($ch);
                if ($websiteHtml) {
                    $socialAudit = auditSocial($websiteHtml);
                }
            }
        }
    }

    // Run Google profile audit
    try {
        $googleAudit = auditGoogleProfile($lead);
    } catch (\Throwable $e) {
        $googleAudit = ['has_listing' => false];
    }

    // Calculate score
    $scoreResult = calculateAuditScore($websiteAudit, $googleAudit, $socialAudit);
    $score = $scoreResult['total'];
    $breakdown = $scoreResult['breakdown'];

    // Get opportunity level
    $opportunityLevel = getOpportunityLevel($score);

    // Map opportunity level to ENUM value
    $opportunityEnum = 'medium';
    if ($score <= 30) {
        $opportunityEnum = 'very_high';
    } elseif ($score <= 50) {
        $opportunityEnum = 'high';
    } elseif ($score <= 70) {
        $opportunityEnum = 'medium';
    } else {
        $opportunityEnum = 'low';
    }

    // Generate problems and recommendations
    $auditData = ['website' => $websiteAudit, 'google' => $googleAudit, 'social' => $socialAudit];
    $problems = getProblems($auditData);
    $recommendations = getRecommendations($auditData);

    // Get package recommendation
    $hasWebsite = !empty($websiteAudit['is_reachable']);
    $package = getPackageRecommendation($score, $hasWebsite);

    // Determine social presence
    $hasSocial = !empty($socialAudit['instagram']) || !empty($socialAudit['facebook'])
        || !empty($socialAudit['youtube']) || !empty($socialAudit['linkedin'])
        || !empty($socialAudit['twitter']);

    // Save to lead_audits table
    $rawData = json_encode([
        'website' => $websiteAudit,
        'google' => $googleAudit,
        'social' => $socialAudit,
        'score_breakdown' => $breakdown,
        'problems' => $problems,
        'recommendations' => $recommendations,
        'package' => $package,
    ]);

    $stmt = $pdo->prepare(
        "INSERT INTO lead_audits (lead_id, has_website, website_url, is_mobile_friendly, has_ssl, has_meta_title, has_meta_description, has_h1_tag, has_schema_markup, has_gmb_listing, gmb_has_phone, gmb_has_website_link, gmb_rating, gmb_review_count, has_social_media, social_facebook, social_instagram, social_twitter, social_linkedin, social_youtube, overall_score, recommendations, raw_data, audited_at)
         VALUES (:lead_id, :has_website, :website_url, :is_mobile, :has_ssl, :has_title, :has_desc, :has_h1, :has_schema, :has_gmb, :gmb_phone, :gmb_website, :gmb_rating, :gmb_reviews, :has_social, :fb, :ig, :tw, :li, :yt, :score, :recs, :raw, NOW())"
    );
    $stmt->execute([
        ':lead_id' => $leadId,
        ':has_website' => !empty($websiteAudit['is_reachable']) ? 1 : 0,
        ':website_url' => $lead['website_url'],
        ':is_mobile' => !empty($websiteAudit['has_viewport']) ? 1 : 0,
        ':has_ssl' => !empty($websiteAudit['has_ssl']) ? 1 : 0,
        ':has_title' => !empty($websiteAudit['has_title']) ? 1 : 0,
        ':has_desc' => !empty($websiteAudit['has_meta_description']) ? 1 : 0,
        ':has_h1' => !empty($websiteAudit['has_h1']) ? 1 : 0,
        ':has_schema' => !empty($websiteAudit['has_json_ld']) ? 1 : 0,
        ':has_gmb' => !empty($googleAudit['has_listing']) ? 1 : 0,
        ':gmb_phone' => !empty($googleAudit['has_phone']) ? 1 : 0,
        ':gmb_website' => !empty($googleAudit['has_website']) ? 1 : 0,
        ':gmb_rating' => $googleAudit['rating'] ?? null,
        ':gmb_reviews' => $googleAudit['review_count'] ?? 0,
        ':has_social' => $hasSocial ? 1 : 0,
        ':fb' => $socialAudit['facebook'] ?: null,
        ':ig' => $socialAudit['instagram'] ?: null,
        ':tw' => $socialAudit['twitter'] ?: null,
        ':li' => $socialAudit['linkedin'] ?: null,
        ':yt' => $socialAudit['youtube'] ?: null,
        ':score' => $score,
        ':recs' => json_encode(['problems' => $problems, 'recommendations' => $recommendations]),
        ':raw' => $rawData,
    ]);

    // Update lead record
    $packageName = $package['name'] . ' ' . $package['price'];
    if (!empty($package['recurring'])) {
        $packageName .= ' + ' . $package['recurring'];
    }

    $stmt = $pdo->prepare(
        "UPDATE leads SET audit_score = :score, opportunity_level = :opp, recommended_package = :pkg, last_audited_at = NOW(), updated_at = NOW() WHERE id = :id"
    );
    $stmt->execute([
        ':score' => $score,
        ':opp' => $opportunityEnum,
        ':pkg' => $packageName,
        ':id' => $leadId,
    ]);

    return [
        'success' => true,
        'score' => $score,
        'opportunity' => $opportunityLevel,
        'problems' => $problems,
        'recommendations' => $recommendations,
        'package' => $package,
        'breakdown' => $breakdown,
        'error' => '',
    ];
}
