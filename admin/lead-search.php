<?php
/**
 * Bharat SEO CRM - Lead Search
 * 
 * Multi-source search page: Google Places API, SerpAPI, Apify, CSV.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = 'Lead Search';
$searchResults = [];
$message = '';
$messageType = '';

// Handle form submission - search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        if ($action === 'search') {
            $params = [
                'niche' => trim($_POST['niche'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? 'India'),
                'radius' => (int)($_POST['radius'] ?? 5000),
                'max_results' => (int)($_POST['max_results'] ?? 100),
                'source' => $_POST['source'] ?? 'google_places',
                'min_rating' => (float)($_POST['min_rating'] ?? 0),
                'max_rating' => (float)($_POST['max_rating'] ?? 5),
                'has_website' => $_POST['has_website'] ?? 'any',
                'has_phone' => $_POST['has_phone'] ?? 'any',
            ];

            if (empty($params['niche'])) {
                $message = 'Please enter a business type or niche to search.';
                $messageType = 'danger';
            } else {
                // Call the appropriate source function
                switch ($params['source']) {
                    case 'google_places':
                        $result = searchGooglePlaces($params);
                        break;
                    case 'serpapi':
                        $result = searchSerpAPI($params);
                        break;
                    case 'apify':
                        $result = searchApify($params);
                        break;
                    default:
                        $result = ['success' => false, 'data' => [], 'error' => 'Invalid source selected.'];
                }

                if ($result['success']) {
                    $searchResults = $result['data'];

                    // Apply post-search filters
                    if ($params['has_website'] === 'yes') {
                        $searchResults = array_filter($searchResults, fn($l) => !empty($l['website_url']));
                    } elseif ($params['has_website'] === 'no') {
                        $searchResults = array_filter($searchResults, fn($l) => empty($l['website_url']));
                    }

                    if ($params['has_phone'] === 'yes') {
                        $searchResults = array_filter($searchResults, fn($l) => !empty($l['phone']));
                    } elseif ($params['has_phone'] === 'no') {
                        $searchResults = array_filter($searchResults, fn($l) => empty($l['phone']));
                    }

                    $searchResults = array_values($searchResults);
                    $message = 'Found ' . count($searchResults) . ' results from ' . sanitize($params['source']) . '.';
                    $messageType = 'success';
                } else {
                    $message = $result['error'];
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'save_leads') {
            // Save selected leads from search results
            $leadsJson = $_POST['leads_data'] ?? '[]';
            $leadsToSave = json_decode($leadsJson, true);
            $selectedIds = $_POST['selected_leads'] ?? [];

            if (empty($selectedIds) || !is_array($selectedIds)) {
                $message = 'No leads selected to save.';
                $messageType = 'warning';
            } else {
                $saved = 0;
                $duplicates = 0;
                $errors = 0;

                foreach ($selectedIds as $index) {
                    if (!isset($leadsToSave[(int)$index])) continue;
                    $leadData = $leadsToSave[(int)$index];

                    try {
                        $result = saveLead($leadData);
                        if ($result['action'] === 'inserted') {
                            $saved++;
                        } elseif ($result['action'] === 'updated' || $result['action'] === 'skipped') {
                            $duplicates++;
                        }
                    } catch (Exception $e) {
                        $errors++;
                    }
                }

                // Log the search
                $searchParams = [
                    'niche' => $_POST['search_niche'] ?? '',
                    'city' => $_POST['search_city'] ?? '',
                    'state' => $_POST['search_state'] ?? '',
                    'country' => $_POST['search_country'] ?? 'India',
                    'source' => $_POST['search_source'] ?? '',
                    'max_results' => count($selectedIds),
                ];
                saveSearchLog($searchParams, [
                    'total_found' => count($leadsToSave),
                    'total_saved' => $saved,
                    'total_duplicates' => $duplicates,
                ]);

                logActivity('leads_saved', "Saved $saved leads, $duplicates duplicates, $errors errors");
                $message = "Saved: $saved | Duplicates: $duplicates | Errors: $errors";
                $messageType = $saved > 0 ? 'success' : 'warning';
            }
        }
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo sanitize($message); ?>
</div>
<?php endif; ?>

<!-- Search Form -->
<div class="card">
    <div class="card-header">
        <h3>Search for Leads</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="searchForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="search">

            <div class="form-grid">
                <div class="form-group">
                    <label for="niche">Business Type / Niche *</label>
                    <input type="text" id="niche" name="niche" class="form-control" 
                           placeholder="e.g., Restaurant, Dentist, Plumber" 
                           value="<?php echo sanitize($_POST['niche'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" class="form-control" 
                           placeholder="e.g., Mumbai, Delhi, Bangalore"
                           value="<?php echo sanitize($_POST['city'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="state">State</label>
                    <input type="text" id="state" name="state" class="form-control" 
                           placeholder="e.g., Maharashtra, Karnataka"
                           value="<?php echo sanitize($_POST['state'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" class="form-control" 
                           value="<?php echo sanitize($_POST['country'] ?? 'India'); ?>">
                </div>

                <div class="form-group">
                    <label for="radius">Search Radius (meters)</label>
                    <input type="number" id="radius" name="radius" class="form-control" 
                           value="<?php echo (int)($_POST['radius'] ?? 5000); ?>" min="100" max="50000">
                </div>

                <div class="form-group">
                    <label for="max_results">Maximum Leads to Fetch</label>
                    <input type="number" id="max_results" name="max_results" class="form-control" 
                           value="<?php echo (int)($_POST['max_results'] ?? 100); ?>" min="1" max="500">
                </div>

                <div class="form-group">
                    <label for="source">Source</label>
                    <select id="source" name="source" class="form-control">
                        <option value="google_places" <?php echo ($_POST['source'] ?? '') === 'google_places' ? 'selected' : ''; ?>>Google Places API</option>
                        <option value="serpapi" <?php echo ($_POST['source'] ?? '') === 'serpapi' ? 'selected' : ''; ?>>SerpAPI</option>
                        <option value="apify" <?php echo ($_POST['source'] ?? '') === 'apify' ? 'selected' : ''; ?>>Apify</option>
                        <option value="csv" <?php echo ($_POST['source'] ?? '') === 'csv' ? 'selected' : ''; ?>>CSV Import</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="min_rating">Minimum Rating</label>
                    <input type="number" id="min_rating" name="min_rating" class="form-control" 
                           value="<?php echo (float)($_POST['min_rating'] ?? 0); ?>" min="0" max="5" step="0.1">
                </div>

                <div class="form-group">
                    <label for="max_rating">Maximum Rating</label>
                    <input type="number" id="max_rating" name="max_rating" class="form-control" 
                           value="<?php echo (float)($_POST['max_rating'] ?? 5); ?>" min="0" max="5" step="0.1">
                </div>

                <div class="form-group">
                    <label for="has_website">Has Website</label>
                    <select id="has_website" name="has_website" class="form-control">
                        <option value="any" <?php echo ($_POST['has_website'] ?? 'any') === 'any' ? 'selected' : ''; ?>>Any</option>
                        <option value="yes" <?php echo ($_POST['has_website'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($_POST['has_website'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="has_phone">Has Phone</label>
                    <select id="has_phone" name="has_phone" class="form-control">
                        <option value="any" <?php echo ($_POST['has_phone'] ?? 'any') === 'any' ? 'selected' : ''; ?>>Any</option>
                        <option value="yes" <?php echo ($_POST['has_phone'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($_POST['has_phone'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="searchBtn">Search Leads</button>
                <span class="text-muted" id="csvNote" style="display:none;">For CSV source, use the <a href="import-csv.php">Import CSV</a> page instead.</span>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($searchResults)): ?>
<!-- Search Results -->
<div class="card">
    <div class="card-header">
        <h3>Search Results (<?php echo count($searchResults); ?> found)</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="save_leads">
            <input type="hidden" name="leads_data" value="<?php echo sanitize(json_encode($searchResults)); ?>">
            <input type="hidden" name="search_niche" value="<?php echo sanitize($_POST['niche'] ?? ''); ?>">
            <input type="hidden" name="search_city" value="<?php echo sanitize($_POST['city'] ?? ''); ?>">
            <input type="hidden" name="search_state" value="<?php echo sanitize($_POST['state'] ?? ''); ?>">
            <input type="hidden" name="search_country" value="<?php echo sanitize($_POST['country'] ?? 'India'); ?>">
            <input type="hidden" name="search_source" value="<?php echo sanitize($_POST['source'] ?? ''); ?>">

            <div class="table-actions">
                <label class="checkbox-label">
                    <input type="checkbox" id="selectAll"> Select All
                </label>
                <button type="submit" class="btn btn-success">Save Selected Leads</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllTop" class="select-all-check"></th>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th>City</th>
                            <th>Phone</th>
                            <th>Website</th>
                            <th>Rating</th>
                            <th>Reviews</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $index => $lead): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_leads[]" value="<?php echo $index; ?>" class="lead-checkbox"></td>
                            <td><?php echo sanitize($lead['business_name']); ?></td>
                            <td><?php echo sanitize($lead['category']); ?></td>
                            <td><?php echo sanitize($lead['city']); ?></td>
                            <td><?php echo sanitize($lead['phone']); ?></td>
                            <td>
                                <?php if (!empty($lead['website_url'])): ?>
                                    <a href="<?php echo sanitize($lead['website_url']); ?>" target="_blank" rel="noopener">Visit</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $lead['rating'] > 0 ? number_format($lead['rating'], 1) : '-'; ?></td>
                            <td><?php echo (int)$lead['review_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">Save Selected Leads</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Source dropdown - show CSV note
    var sourceSelect = document.getElementById('source');
    var csvNote = document.getElementById('csvNote');
    var searchBtn = document.getElementById('searchBtn');

    if (sourceSelect) {
        sourceSelect.addEventListener('change', function() {
            if (this.value === 'csv') {
                csvNote.style.display = 'inline';
                searchBtn.disabled = true;
            } else {
                csvNote.style.display = 'none';
                searchBtn.disabled = false;
            }
        });
    }

    // Select all checkboxes
    var selectAll = document.getElementById('selectAll');
    var selectAllTop = document.getElementById('selectAllTop');
    var checkboxes = document.querySelectorAll('.lead-checkbox');

    function toggleAll(checked) {
        checkboxes.forEach(function(cb) { cb.checked = checked; });
        if (selectAll) selectAll.checked = checked;
        if (selectAllTop) selectAllTop.checked = checked;
    }

    if (selectAll) selectAll.addEventListener('change', function() { toggleAll(this.checked); });
    if (selectAllTop) selectAllTop.addEventListener('change', function() { toggleAll(this.checked); });
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
