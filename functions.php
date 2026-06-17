<?php
/**
 * Bharat SEO Lead Finder & Auto Audit CRM Tool
 * Core Functions
 */

require_once __DIR__ . '/config.php';

// ============================================================
// AUTHENTICATION FUNCTIONS
// ============================================================

function isLoggedIn() {
    initSession();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminLogin($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        initSession();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        logActivity('login', "Admin logged in: {$username}");
        return true;
    }
    return false;
}

function adminLogout() {
    initSession();
    logActivity('logout', "Admin logged out");
    session_destroy();
    header('Location: login.php');
    exit;
}


// ============================================================
// CSRF PROTECTION
// ============================================================

function generateCSRFToken() {
    initSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    initSession();
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

// ============================================================
// LEAD MANAGEMENT FUNCTIONS
// ============================================================

function getLeadById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getLeadAudit($leadId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM lead_audits WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$leadId]);
    return $stmt->fetch();
}


function checkDuplicate($data) {
    $db = getDB();
    
    // Check by source_id
    if (!empty($data['source_id'])) {
        $stmt = $db->prepare("SELECT id FROM leads WHERE source_id = ? LIMIT 1");
        $stmt->execute([$data['source_id']]);
        if ($row = $stmt->fetch()) return $row['id'];
    }
    
    // Check by phone
    if (!empty($data['phone'])) {
        $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
        $stmt = $db->prepare("SELECT id FROM leads WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($row = $stmt->fetch()) return $row['id'];
    }
    
    // Check by website URL
    if (!empty($data['website_url'])) {
        $url = rtrim(strtolower($data['website_url']), '/');
        $stmt = $db->prepare("SELECT id FROM leads WHERE LOWER(TRIM(TRAILING '/' FROM website_url)) = ? LIMIT 1");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) return $row['id'];
    }
    
    // Check by business name + city
    if (!empty($data['business_name']) && !empty($data['city'])) {
        $stmt = $db->prepare("SELECT id FROM leads WHERE LOWER(business_name) = ? AND LOWER(city) = ? LIMIT 1");
        $stmt->execute([strtolower($data['business_name']), strtolower($data['city'])]);
        if ($row = $stmt->fetch()) return $row['id'];
    }
    
    return false;
}


function saveLead($data) {
    $duplicateId = checkDuplicate($data);
    
    if ($duplicateId) {
        // Update missing fields
        updateLeadMissingFields($duplicateId, $data);
        return ['status' => 'duplicate', 'id' => $duplicateId];
    }
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO leads (business_name, category, phone, email, website_url, google_maps_url, address, city, state, country, rating, review_count, latitude, longitude, source, source_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New')");
    
    $stmt->execute([
        $data['business_name'] ?? '',
        $data['category'] ?? null,
        $data['phone'] ?? null,
        $data['email'] ?? null,
        $data['website_url'] ?? null,
        $data['google_maps_url'] ?? null,
        $data['address'] ?? null,
        $data['city'] ?? null,
        $data['state'] ?? null,
        $data['country'] ?? 'India',
        $data['rating'] ?? null,
        $data['review_count'] ?? 0,
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        $data['source'] ?? null,
        $data['source_id'] ?? null
    ]);
    
    return ['status' => 'saved', 'id' => $db->lastInsertId()];
}

function updateLeadMissingFields($leadId, $newData) {
    $db = getDB();
    $lead = getLeadById($leadId);
    if (!$lead) return;
    
    $updates = [];
    $params = [];
    $fields = ['phone', 'email', 'website_url', 'google_maps_url', 'address', 'category', 'rating', 'review_count', 'latitude', 'longitude'];
    
    foreach ($fields as $field) {
        if (empty($lead[$field]) && !empty($newData[$field])) {
            $updates[] = "$field = ?";
            $params[] = $newData[$field];
        }
    }
    
    if (!empty($updates)) {
        $params[] = $leadId;
        $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
}


function updateLeadStatus($leadId, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE leads SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $leadId]);
}

function updateLeadNotes($leadId, $notes, $followUpDate = null) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE leads SET notes = ?, follow_up_date = ? WHERE id = ?");
    return $stmt->execute([$notes, $followUpDate, $leadId]);
}

function deleteLead($leadId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
    return $stmt->execute([$leadId]);
}

// ============================================================
// DASHBOARD FUNCTIONS
// ============================================================

function getDashboardStats() {
    $db = getDB();
    $stats = [];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads");
    $stats['total_leads'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE status = 'New'");
    $stats['new_leads'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE status = 'Audited'");
    $stats['audited_leads'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE opportunity_level IN ('Very High Opportunity', 'High Opportunity')");
    $stats['high_opportunity'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE status = 'Contacted'");
    $stats['contacted_leads'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE status = 'Converted'");
    $stats['converted_leads'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT AVG(audit_score) as avg_score FROM leads WHERE audit_score IS NOT NULL");
    $stats['avg_score'] = round($stmt->fetch()['avg_score'] ?? 0);
    
    $stmt = $db->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10");
    $stats['recent_leads'] = $stmt->fetchAll();
    
    return $stats;
}


// ============================================================
// LEAD SEARCH / API FUNCTIONS
// ============================================================

function searchLeads($params) {
    $source = $params['source'] ?? 'google_places';
    
    switch ($source) {
        case 'google_places':
            return searchGooglePlaces($params);
        case 'serpapi':
            return searchSerpAPI($params);
        case 'apify':
            return searchApify($params);
        default:
            return ['error' => 'Invalid source selected'];
    }
}

function searchGooglePlaces($params) {
    $apiKey = getSetting('google_places_api_key');
    if (empty($apiKey)) {
        return ['error' => 'Google Places API key not configured. Go to Settings.'];
    }
    
    $query = urlencode(($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ' ' . ($params['state'] ?? ''));
    $maxResults = min((int)($params['max_results'] ?? 20), 60);
    $radius = ($params['radius'] ?? 50000);
    
    $results = [];
    $nextPageToken = null;
    $fetched = 0;
    
    do {
        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query={$query}&key={$apiKey}";
        if ($nextPageToken) {
            $url .= "&pagetoken={$nextPageToken}";
            sleep(2); // Required delay for page tokens
        }
        
        $response = curlGet($url);
        if (!$response || !isset($response['results'])) break;
        
        foreach ($response['results'] as $place) {
            if ($fetched >= $maxResults) break 2;
            
            $lead = [
                'business_name' => $place['name'] ?? '',
                'address' => $place['formatted_address'] ?? '',
                'city' => $params['city'] ?? '',
                'state' => $params['state'] ?? '',
                'country' => $params['country'] ?? 'India',
                'rating' => $place['rating'] ?? null,
                'review_count' => $place['user_ratings_total'] ?? 0,
                'latitude' => $place['geometry']['location']['lat'] ?? null,
                'longitude' => $place['geometry']['location']['lng'] ?? null,
                'category' => $params['niche'] ?? '',
                'source' => 'Google Places',
                'source_id' => $place['place_id'] ?? null,
            ];
            
            // Get details for phone and website
            if (!empty($place['place_id'])) {
                $details = getGooglePlaceDetails($place['place_id'], $apiKey);
                if ($details) {
                    $lead['phone'] = $details['formatted_phone_number'] ?? null;
                    $lead['website_url'] = $details['website'] ?? null;
                    $lead['google_maps_url'] = $details['url'] ?? null;
                }
            }
            
            // Apply filters
            if (applySearchFilters($lead, $params)) {
                $results[] = $lead;
                $fetched++;
            }
        }
        
        $nextPageToken = $response['next_page_token'] ?? null;
    } while ($nextPageToken && $fetched < $maxResults);
    
    return ['leads' => $results, 'total' => count($results)];
}


function getGooglePlaceDetails($placeId, $apiKey) {
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$placeId}&fields=formatted_phone_number,website,url,opening_hours&key={$apiKey}";
    $response = curlGet($url);
    return $response['result'] ?? null;
}

function searchSerpAPI($params) {
    $apiKey = getSetting('serpapi_key');
    if (empty($apiKey)) {
        return ['error' => 'SerpAPI key not configured. Go to Settings.'];
    }
    
    $query = urlencode(($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ' ' . ($params['state'] ?? ''));
    $maxResults = min((int)($params['max_results'] ?? 20), 100);
    
    $results = [];
    $start = 0;
    
    do {
        $url = "https://serpapi.com/search.json?engine=google_maps&q={$query}&api_key={$apiKey}&start={$start}";
        $response = curlGet($url);
        
        if (!$response || !isset($response['local_results'])) break;
        
        foreach ($response['local_results'] as $place) {
            if (count($results) >= $maxResults) break 2;
            
            $lead = [
                'business_name' => $place['title'] ?? '',
                'address' => $place['address'] ?? '',
                'city' => $params['city'] ?? '',
                'state' => $params['state'] ?? '',
                'country' => $params['country'] ?? 'India',
                'phone' => $place['phone'] ?? null,
                'website_url' => $place['website'] ?? null,
                'google_maps_url' => $place['link'] ?? null,
                'rating' => $place['rating'] ?? null,
                'review_count' => $place['reviews'] ?? 0,
                'latitude' => $place['gps_coordinates']['latitude'] ?? null,
                'longitude' => $place['gps_coordinates']['longitude'] ?? null,
                'category' => $place['type'] ?? ($params['niche'] ?? ''),
                'source' => 'SerpAPI',
                'source_id' => $place['place_id'] ?? null,
            ];
            
            if (applySearchFilters($lead, $params)) {
                $results[] = $lead;
            }
        }
        
        $start += 20;
        sleep(1);
    } while (count($results) < $maxResults && isset($response['serpapi_pagination']));
    
    return ['leads' => $results, 'total' => count($results)];
}


function searchApify($params) {
    $apiToken = getSetting('apify_api_token');
    if (empty($apiToken)) {
        return ['error' => 'Apify API token not configured. Go to Settings.'];
    }
    
    $query = ($params['niche'] ?? '') . ' in ' . ($params['city'] ?? '') . ' ' . ($params['state'] ?? '');
    $maxResults = min((int)($params['max_results'] ?? 20), 100);
    
    // Run Apify Google Maps Scraper actor
    $actorUrl = "https://api.apify.com/v2/acts/compass~crawler-google-places/runs?token={$apiToken}";
    $payload = json_encode([
        'searchStringsArray' => [$query],
        'maxCrawledPlaces' => $maxResults,
        'language' => 'en',
        'countryCode' => 'in'
    ]);
    
    $response = curlPost($actorUrl, $payload, ['Content-Type: application/json']);
    if (!$response || !isset($response['data']['id'])) {
        return ['error' => 'Failed to start Apify actor'];
    }
    
    $runId = $response['data']['id'];
    
    // Wait for completion (max 5 minutes)
    $datasetUrl = "https://api.apify.com/v2/actor-runs/{$runId}?token={$apiToken}";
    $maxWait = 300;
    $waited = 0;
    
    do {
        sleep(10);
        $waited += 10;
        $status = curlGet($datasetUrl);
    } while ($waited < $maxWait && isset($status['data']['status']) && $status['data']['status'] === 'RUNNING');
    
    if (!isset($status['data']['defaultDatasetId'])) {
        return ['error' => 'Apify run did not complete in time'];
    }
    
    // Fetch results
    $datasetId = $status['data']['defaultDatasetId'];
    $itemsUrl = "https://api.apify.com/v2/datasets/{$datasetId}/items?token={$apiToken}&limit={$maxResults}";
    $items = curlGet($itemsUrl);
    
    if (!$items || !is_array($items)) {
        return ['error' => 'Failed to fetch Apify results'];
    }
    
    $results = [];
    foreach ($items as $place) {
        $lead = [
            'business_name' => $place['title'] ?? $place['name'] ?? '',
            'address' => $place['address'] ?? $place['street'] ?? '',
            'city' => $params['city'] ?? '',
            'state' => $params['state'] ?? '',
            'country' => $params['country'] ?? 'India',
            'phone' => $place['phone'] ?? $place['phoneUnformatted'] ?? null,
            'website_url' => $place['website'] ?? null,
            'google_maps_url' => $place['url'] ?? null,
            'rating' => $place['totalScore'] ?? $place['rating'] ?? null,
            'review_count' => $place['reviewsCount'] ?? 0,
            'latitude' => $place['location']['lat'] ?? null,
            'longitude' => $place['location']['lng'] ?? null,
            'category' => $place['categoryName'] ?? ($params['niche'] ?? ''),
            'source' => 'Apify',
            'source_id' => $place['placeId'] ?? null,
        ];
        
        if (applySearchFilters($lead, $params)) {
            $results[] = $lead;
        }
    }
    
    return ['leads' => $results, 'total' => count($results)];
}


function applySearchFilters($lead, $params) {
    // Min rating filter
    if (!empty($params['min_rating']) && ($lead['rating'] ?? 0) < (float)$params['min_rating']) {
        return false;
    }
    // Max rating filter
    if (!empty($params['max_rating']) && ($lead['rating'] ?? 0) > (float)$params['max_rating']) {
        return false;
    }
    // Has website filter
    if (isset($params['has_website']) && $params['has_website'] === 'yes' && empty($lead['website_url'])) {
        return false;
    }
    if (isset($params['has_website']) && $params['has_website'] === 'no' && !empty($lead['website_url'])) {
        return false;
    }
    // Has phone filter
    if (isset($params['has_phone']) && $params['has_phone'] === 'yes' && empty($lead['phone'])) {
        return false;
    }
    if (isset($params['has_phone']) && $params['has_phone'] === 'no' && !empty($lead['phone'])) {
        return false;
    }
    return true;
}

// ============================================================
// CURL HELPER FUNCTIONS
// ============================================================

function curlGet($url) {
    $timeout = (int)getSetting('curl_timeout', CURL_TIMEOUT_DEFAULT);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'BharatSEO-CRM/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return $decoded ?: null;
}

function curlPost($url, $data, $headers = []) {
    $timeout = (int)getSetting('curl_timeout', CURL_TIMEOUT_DEFAULT);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'BharatSEO-CRM/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return $decoded ?: null;
}

function curlGetHtml($url) {
    $timeout = (int)getSetting('curl_timeout', CURL_TIMEOUT_DEFAULT);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BharatSEO-Audit/1.0)',
        CURLOPT_HEADER => true,
    ]);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $loadTime = round(microtime(true) - $startTime, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_status' => 0, 'load_time' => $loadTime];
    }
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    return [
        'html' => $body,
        'http_status' => $httpCode,
        'load_time' => $loadTime,
        'page_size' => strlen($body),
        'effective_url' => $effectiveUrl,
        'headers' => $headers,
    ];
}


// ============================================================
// WEBSITE AUDIT FUNCTIONS
// ============================================================

function auditLead($leadId) {
    $lead = getLeadById($leadId);
    if (!$lead) return ['error' => 'Lead not found'];
    
    $auditData = [
        'lead_id' => $leadId,
        'website_status' => 'no_website',
        'http_status' => 0,
        'has_https' => 0,
        'has_title' => 0,
        'title_text' => null,
        'has_meta_description' => 0,
        'meta_description' => null,
        'has_h1' => 0,
        'h1_text' => null,
        'has_viewport' => 0,
        'has_canonical' => 0,
        'has_robots' => 0,
        'has_og_tags' => 0,
        'has_twitter_tags' => 0,
        'has_schema' => 0,
        'has_local_schema' => 0,
        'has_whatsapp' => 0,
        'has_phone' => 0,
        'has_email' => 0,
        'has_contact_form' => 0,
        'has_google_map' => 0,
        'has_social_links' => 0,
        'instagram_link' => null,
        'facebook_link' => null,
        'youtube_link' => null,
        'page_size' => 0,
        'load_time' => 0,
    ];
    
    $problems = [];
    $recommendations = [];
    
    // Website Audit
    if (!empty($lead['website_url'])) {
        $url = $lead['website_url'];
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        $result = curlGetHtml($url);
        
        if (isset($result['error'])) {
            $auditData['website_status'] = 'unreachable';
            $problems[] = 'Website is unreachable';
            $recommendations[] = 'Fix website hosting or domain issues';
        } else {
            $auditData['website_status'] = 'reachable';
            $auditData['http_status'] = $result['http_status'];
            $auditData['page_size'] = $result['page_size'];
            $auditData['load_time'] = $result['load_time'];
            
            $html = $result['html'] ?? '';
            $effectiveUrl = $result['effective_url'] ?? $url;
            
            // HTTPS check
            $auditData['has_https'] = (strpos($effectiveUrl, 'https://') === 0) ? 1 : 0;
            if (!$auditData['has_https']) {
                $problems[] = 'Website does not use HTTPS';
                $recommendations[] = 'Install SSL certificate for HTTPS';
            }
            
            // Parse HTML for SEO elements
            $auditData = array_merge($auditData, parseHtmlAudit($html, $problems, $recommendations));
        }
    } else {
        $auditData['website_status'] = 'no_website';
        $problems[] = 'No website found';
        $recommendations[] = 'Create mobile-friendly website';
        $recommendations[] = 'Add WhatsApp enquiry button';
        $recommendations[] = 'Add call button';
        $recommendations[] = 'Add Google Map embed';
    }
    
    // Google Profile Audit
    $googleProblems = auditGoogleProfile($lead);
    $problems = array_merge($problems, $googleProblems);
    
    // Calculate score
    $score = calculateAuditScore($auditData, $lead);
    $auditData['score'] = $score;
    $auditData['problems'] = json_encode($problems);
    $auditData['recommendations'] = json_encode($recommendations);
    $auditData['audit_json'] = json_encode($auditData);
    
    // Save audit
    saveAudit($auditData);
    
    // Update lead
    $opportunityLevel = getOpportunityLevel($score);
    $package = getRecommendedPackage($score, $lead);
    $outreach = generateOutreachMessage($lead, $problems, $score);
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE leads SET audit_score = ?, opportunity_level = ?, recommended_package = ?, outreach_message = ?, status = CASE WHEN status = 'New' THEN 'Audited' ELSE status END, last_audited_at = NOW() WHERE id = ?");
    $stmt->execute([$score, $opportunityLevel, $package, $outreach, $leadId]);
    
    logActivity('audit', "Audited lead: {$lead['business_name']} (Score: {$score})");
    
    return [
        'score' => $score,
        'opportunity' => $opportunityLevel,
        'problems' => $problems,
        'recommendations' => $recommendations,
        'package' => $package,
    ];
}


function parseHtmlAudit($html, &$problems, &$recommendations) {
    $data = [];
    
    // Title
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
        $data['has_title'] = 1;
        $data['title_text'] = trim(strip_tags($match[1]));
    } else {
        $data['has_title'] = 0;
        $problems[] = 'Missing page title';
        $recommendations[] = 'Add SEO title and meta description';
    }
    
    // Meta description
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i', $html, $match)) {
        $data['has_meta_description'] = 1;
        $data['meta_description'] = trim($match[1]);
    } else {
        $data['has_meta_description'] = 0;
        $problems[] = 'Missing meta description';
    }
    
    // H1 tag
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $match)) {
        $data['has_h1'] = 1;
        $data['h1_text'] = trim(strip_tags($match[1]));
    } else {
        $data['has_h1'] = 0;
        $problems[] = 'Missing H1 tag';
    }
    
    // Viewport (mobile)
    $data['has_viewport'] = preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html) ? 1 : 0;
    if (!$data['has_viewport']) {
        $problems[] = 'Not mobile optimized (no viewport meta)';
        $recommendations[] = 'Create mobile-friendly website';
    }
    
    // Canonical
    $data['has_canonical'] = preg_match('/<link[^>]+rel=["\']canonical["\']/i', $html) ? 1 : 0;
    
    // Robots meta
    $data['has_robots'] = preg_match('/<meta[^>]+name=["\']robots["\']/i', $html) ? 1 : 0;
    
    // Open Graph tags
    $data['has_og_tags'] = preg_match('/<meta[^>]+property=["\']og:/i', $html) ? 1 : 0;
    
    // Twitter card tags
    $data['has_twitter_tags'] = preg_match('/<meta[^>]+name=["\']twitter:/i', $html) ? 1 : 0;
    
    // JSON-LD Schema
    $data['has_schema'] = preg_match('/<script[^>]+type=["\']application\/ld\+json["\']/i', $html) ? 1 : 0;
    
    // Local business schema
    $data['has_local_schema'] = preg_match('/LocalBusiness|localBusiness/i', $html) ? 1 : 0;
    if (!$data['has_local_schema']) {
        $problems[] = 'No local business schema';
        $recommendations[] = 'Add local business schema';
    }
    
    // WhatsApp link
    $data['has_whatsapp'] = preg_match('/wa\.me|whatsapp\.com|whatsapp/i', $html) ? 1 : 0;
    if (!$data['has_whatsapp']) {
        $problems[] = 'No WhatsApp enquiry button';
        $recommendations[] = 'Add WhatsApp enquiry button';
    }
    
    // Phone number
    $data['has_phone'] = preg_match('/tel:|phone|call/i', $html) ? 1 : 0;
    if (!$data['has_phone']) {
        $recommendations[] = 'Add call button';
    }
    
    // Email
    $data['has_email'] = preg_match('/mailto:|email/i', $html) ? 1 : 0;
    
    // Contact form
    $data['has_contact_form'] = preg_match('/<form[^>]*>/i', $html) ? 1 : 0;
    if (!$data['has_contact_form']) {
        $problems[] = 'No contact form found';
        $recommendations[] = 'Add lead form';
    }
    
    // Google Map embed
    $data['has_google_map'] = preg_match('/maps\.google|google\.com\/maps|maps\.googleapis/i', $html) ? 1 : 0;
    if (!$data['has_google_map']) {
        $problems[] = 'No Google Map embed';
        $recommendations[] = 'Add Google Map embed';
    }
    
    // Social links
    $data['has_social_links'] = 0;
    $data['instagram_link'] = null;
    $data['facebook_link'] = null;
    $data['youtube_link'] = null;
    
    if (preg_match('/instagram\.com\/[a-zA-Z0-9_.]+/i', $html, $match)) {
        $data['instagram_link'] = 'https://www.' . $match[0];
        $data['has_social_links'] = 1;
    } else {
        $problems[] = 'No Instagram link found';
        $recommendations[] = 'Add Instagram content plan';
    }
    
    if (preg_match('/facebook\.com\/[a-zA-Z0-9_.]+/i', $html, $match)) {
        $data['facebook_link'] = 'https://www.' . $match[0];
        $data['has_social_links'] = 1;
    }
    
    if (preg_match('/youtube\.com\/(channel|c|@)[\/a-zA-Z0-9_.-]+/i', $html, $match)) {
        $data['youtube_link'] = 'https://www.' . $match[0];
        $data['has_social_links'] = 1;
    }
    
    return $data;
}


function auditGoogleProfile($lead) {
    $problems = [];
    
    if (empty($lead['google_maps_url']) && empty($lead['source_id'])) {
        $problems[] = 'No Google listing found';
    }
    if (empty($lead['phone'])) {
        $problems[] = 'Google profile has no phone number';
    }
    if (empty($lead['website_url'])) {
        $problems[] = 'Google profile has no website linked';
    }
    if (($lead['review_count'] ?? 0) < 10) {
        $problems[] = 'Google profile has low reviews (less than 10)';
    }
    if (($lead['rating'] ?? 0) < 4.0 && ($lead['rating'] ?? 0) > 0) {
        $problems[] = 'Google rating below 4.0';
    }
    
    return $problems;
}

function calculateAuditScore($auditData, $lead) {
    $score = 0;
    
    // Website presence: 15 points
    if ($auditData['website_status'] === 'reachable') {
        $score += 10;
        if ($auditData['has_https']) $score += 5;
    }
    
    // Website SEO basics: 20 points
    if ($auditData['has_title']) $score += 4;
    if ($auditData['has_meta_description']) $score += 4;
    if ($auditData['has_h1']) $score += 3;
    if ($auditData['has_canonical']) $score += 2;
    if ($auditData['has_og_tags']) $score += 2;
    if ($auditData['has_twitter_tags']) $score += 2;
    if ($auditData['has_schema']) $score += 3;
    
    // Mobile readiness: 10 points
    if ($auditData['has_viewport']) $score += 10;
    
    // Local conversion elements: 20 points
    if ($auditData['has_whatsapp']) $score += 5;
    if ($auditData['has_phone']) $score += 4;
    if ($auditData['has_contact_form']) $score += 4;
    if ($auditData['has_google_map']) $score += 4;
    if ($auditData['has_local_schema']) $score += 3;
    
    // Google profile strength: 20 points
    if (!empty($lead['google_maps_url']) || !empty($lead['source_id'])) $score += 5;
    if (!empty($lead['phone'])) $score += 3;
    if (!empty($lead['website_url'])) $score += 3;
    if (!empty($lead['address'])) $score += 2;
    if (($lead['rating'] ?? 0) >= 4.0) $score += 4;
    if (($lead['review_count'] ?? 0) >= 10) $score += 3;
    
    // Social presence: 10 points
    if ($auditData['has_social_links']) $score += 4;
    if (!empty($auditData['instagram_link'])) $score += 3;
    if (!empty($auditData['facebook_link'])) $score += 3;
    
    // Lead readiness: 5 points
    if ($auditData['has_email']) $score += 2;
    if ($auditData['has_contact_form'] && $auditData['has_whatsapp']) $score += 3;
    
    return min($score, 100);
}

function getOpportunityLevel($score) {
    if ($score <= 30) return 'Very High Opportunity';
    if ($score <= 50) return 'High Opportunity';
    if ($score <= 70) return 'Medium Opportunity';
    if ($score <= 85) return 'Low Opportunity';
    return 'Strong Online Presence';
}

function getRecommendedPackage($score, $lead) {
    if (empty($lead['website_url'])) {
        return getSetting('package_starter', 'Bharat Starter Package ₹2,999');
    }
    if ($score <= 50) {
        return getSetting('package_growth', 'Bharat Growth Package ₹6,999 + ₹999/month');
    }
    if ($score <= 70) {
        return getSetting('package_seo_fix', 'Bharat Starter SEO Fix Package ₹2,999');
    }
    return getSetting('package_pro', 'Bharat Pro Package ₹14,999 + ₹2,999/month');
}


function generateOutreachMessage($lead, $problems, $score) {
    $businessName = $lead['business_name'] ?? 'your business';
    
    if (empty($lead['website_url'])) {
        $template = getSetting('outreach_template_no_website', "Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapke business ka online presence check kiya. Aapka Google profile hai lekin website nahi mili.\nAgar website + WhatsApp enquiry button + Google Map SEO setup ho jaaye to customers direct call/WhatsApp kar sakte hain.\nStarter setup ₹2,999 me available hai. Demo free dikha sakta hoon.");
        return $template;
    }
    
    if ($score <= 70) {
        $template = getSetting('outreach_template_weak_website', "Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapki website aur Google profile check ki. Kuch issues mile:\n{problems}\nMain ye complete fix ₹2,999 se start kar sakta hoon. Free demo dikha sakta hoon.");
        
        $problemList = '';
        $count = 1;
        foreach (array_slice($problems, 0, 3) as $problem) {
            $problemList .= "{$count}. {$problem}\n";
            $count++;
        }
        return str_replace('{problems}', trim($problemList), $template);
    }
    
    $template = getSetting('outreach_template_good_website', "Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapki website check ki. Overall kaafi achhi hai lekin kuch advanced SEO improvements se aur growth ho sakti hai.\nPro package ₹14,999 + ₹2,999/month me full SEO + lead generation setup milega. Free consultation available hai.");
    return $template;
}

function saveAudit($auditData) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO lead_audits (lead_id, website_status, http_status, has_https, has_title, title_text, has_meta_description, meta_description, has_h1, h1_text, has_viewport, has_canonical, has_robots, has_og_tags, has_twitter_tags, has_schema, has_local_schema, has_whatsapp, has_phone, has_email, has_contact_form, has_google_map, has_social_links, instagram_link, facebook_link, youtube_link, page_size, load_time, audit_json, problems, recommendations, score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $auditData['lead_id'],
        $auditData['website_status'] ?? null,
        $auditData['http_status'] ?? 0,
        $auditData['has_https'] ?? 0,
        $auditData['has_title'] ?? 0,
        $auditData['title_text'] ?? null,
        $auditData['has_meta_description'] ?? 0,
        $auditData['meta_description'] ?? null,
        $auditData['has_h1'] ?? 0,
        $auditData['h1_text'] ?? null,
        $auditData['has_viewport'] ?? 0,
        $auditData['has_canonical'] ?? 0,
        $auditData['has_robots'] ?? 0,
        $auditData['has_og_tags'] ?? 0,
        $auditData['has_twitter_tags'] ?? 0,
        $auditData['has_schema'] ?? 0,
        $auditData['has_local_schema'] ?? 0,
        $auditData['has_whatsapp'] ?? 0,
        $auditData['has_phone'] ?? 0,
        $auditData['has_email'] ?? 0,
        $auditData['has_contact_form'] ?? 0,
        $auditData['has_google_map'] ?? 0,
        $auditData['has_social_links'] ?? 0,
        $auditData['instagram_link'] ?? null,
        $auditData['facebook_link'] ?? null,
        $auditData['youtube_link'] ?? null,
        $auditData['page_size'] ?? 0,
        $auditData['load_time'] ?? 0,
        $auditData['audit_json'] ?? null,
        $auditData['problems'] ?? null,
        $auditData['recommendations'] ?? null,
        $auditData['score'] ?? 0,
    ]);
}

// ============================================================
// CSV FUNCTIONS
// ============================================================

function importCSV($filePath) {
    $results = ['saved' => 0, 'duplicates' => 0, 'errors' => 0, 'total' => 0];
    
    if (($handle = fopen($filePath, 'r')) !== false) {
        $headers = fgetcsv($handle);
        if (!$headers) return ['error' => 'Invalid CSV file'];
        
        $headers = array_map('strtolower', array_map('trim', $headers));
        
        while (($row = fgetcsv($handle)) !== false) {
            $results['total']++;
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            
            $lead = [
                'business_name' => $data['business_name'] ?? $data['name'] ?? '',
                'phone' => $data['phone'] ?? $data['mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'website_url' => $data['website'] ?? $data['website_url'] ?? null,
                'google_maps_url' => $data['google_maps_url'] ?? $data['maps_url'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? 'India',
                'category' => $data['category'] ?? $data['niche'] ?? null,
                'rating' => $data['rating'] ?? null,
                'review_count' => $data['reviews'] ?? $data['review_count'] ?? 0,
                'source' => 'CSV Import',
                'source_id' => null,
            ];
            
            if (empty($lead['business_name'])) {
                $results['errors']++;
                continue;
            }
            
            $result = saveLead($lead);
            if ($result['status'] === 'saved') {
                $results['saved']++;
            } else {
                $results['duplicates']++;
            }
        }
        fclose($handle);
    }
    
    return $results;
}

function exportLeadsCSV($filters = []) {
    $db = getDB();
    $where = buildLeadFilters($filters);
    $sql = "SELECT * FROM leads" . ($where ? " WHERE " . $where['sql'] : "") . " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($where['params'] ?? []);
    return $stmt->fetchAll();
}


// ============================================================
// LEAD FILTERING / LISTING
// ============================================================

function getLeads($filters = [], $page = 1, $perPage = 25) {
    $db = getDB();
    $where = buildLeadFilters($filters);
    $offset = ($page - 1) * $perPage;
    
    // Count total
    $countSql = "SELECT COUNT(*) as total FROM leads" . ($where['sql'] ? " WHERE " . $where['sql'] : "");
    $stmt = $db->prepare($countSql);
    $stmt->execute($where['params']);
    $total = $stmt->fetch()['total'];
    
    // Fetch leads
    $sql = "SELECT * FROM leads" . ($where['sql'] ? " WHERE " . $where['sql'] : "") . " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($where['params']);
    $leads = $stmt->fetchAll();
    
    return [
        'leads' => $leads,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
    ];
}

function buildLeadFilters($filters) {
    $conditions = [];
    $params = [];
    
    if (!empty($filters['city'])) {
        $conditions[] = "city LIKE ?";
        $params[] = '%' . $filters['city'] . '%';
    }
    if (!empty($filters['category'])) {
        $conditions[] = "category LIKE ?";
        $params[] = '%' . $filters['category'] . '%';
    }
    if (!empty($filters['status'])) {
        $conditions[] = "status = ?";
        $params[] = $filters['status'];
    }
    if (!empty($filters['has_website'])) {
        if ($filters['has_website'] === 'yes') {
            $conditions[] = "website_url IS NOT NULL AND website_url != ''";
        } else {
            $conditions[] = "(website_url IS NULL OR website_url = '')";
        }
    }
    if (!empty($filters['high_opportunity'])) {
        $conditions[] = "opportunity_level IN ('Very High Opportunity', 'High Opportunity')";
    }
    if (!empty($filters['min_score'])) {
        $conditions[] = "audit_score >= ?";
        $params[] = (int)$filters['min_score'];
    }
    if (!empty($filters['max_score'])) {
        $conditions[] = "audit_score <= ?";
        $params[] = (int)$filters['max_score'];
    }
    if (!empty($filters['min_rating'])) {
        $conditions[] = "rating >= ?";
        $params[] = (float)$filters['min_rating'];
    }
    if (!empty($filters['max_rating'])) {
        $conditions[] = "rating <= ?";
        $params[] = (float)$filters['max_rating'];
    }
    if (!empty($filters['min_reviews'])) {
        $conditions[] = "review_count >= ?";
        $params[] = (int)$filters['min_reviews'];
    }
    if (!empty($filters['max_reviews'])) {
        $conditions[] = "review_count <= ?";
        $params[] = (int)$filters['max_reviews'];
    }
    if (!empty($filters['search'])) {
        $conditions[] = "(business_name LIKE ? OR phone LIKE ? OR city LIKE ? OR category LIKE ?)";
        $s = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$s, $s, $s, $s]);
    }
    
    return [
        'sql' => implode(' AND ', $conditions),
        'params' => $params,
    ];
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('d M Y', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date)) return '-';
    return date('d M Y H:i', strtotime($date));
}

function getWhatsAppLink($phone, $message = '') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        $phone = '91' . $phone;
    }
    $url = "https://wa.me/{$phone}";
    if ($message) {
        $url .= "?text=" . urlencode($message);
    }
    return $url;
}

function getScoreBadgeClass($score) {
    if ($score === null) return 'badge-secondary';
    if ($score <= 30) return 'badge-danger';
    if ($score <= 50) return 'badge-warning';
    if ($score <= 70) return 'badge-info';
    return 'badge-success';
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'New': return 'badge-primary';
        case 'Audited': return 'badge-info';
        case 'Contacted': return 'badge-warning';
        case 'Interested': return 'badge-success';
        case 'Follow-up': return 'badge-secondary';
        case 'Converted': return 'badge-success';
        case 'Not interested': return 'badge-danger';
        default: return 'badge-secondary';
    }
}
