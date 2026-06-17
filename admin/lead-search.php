<?php
$pageTitle = 'Lead Search';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';
$searchResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'search') {
            $params = [
                'niche' => trim($_POST['niche'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? 'India'),
                'radius' => (int)($_POST['radius'] ?? 50000),
                'max_results' => (int)($_POST['max_results'] ?? 20),
                'source' => $_POST['source'] ?? 'google_places',
                'min_rating' => $_POST['min_rating'] ?? '',
                'max_rating' => $_POST['max_rating'] ?? '',
                'has_website' => $_POST['has_website'] ?? 'any',
                'has_phone' => $_POST['has_phone'] ?? 'any',
            ];
            
            if (empty($params['niche']) || empty($params['city'])) {
                $message = 'Please enter business type and city.';
                $messageType = 'danger';
            } else {
                $searchResults = searchLeads($params);
                
                if (isset($searchResults['error'])) {
                    $message = $searchResults['error'];
                    $messageType = 'danger';
                } else {
                    // Save leads
                    $saved = 0;
                    $duplicates = 0;
                    foreach ($searchResults['leads'] as $lead) {
                        $result = saveLead($lead);
                        if ($result['status'] === 'saved') $saved++;
                        else $duplicates++;
                    }
                    
                    // Log search
                    $db = getDB();
                    $stmt = $db->prepare("INSERT INTO lead_searches (niche, city, state, country, source, max_results, total_found, total_saved, total_duplicates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$params['niche'], $params['city'], $params['state'], $params['country'], $params['source'], $params['max_results'], count($searchResults['leads']), $saved, $duplicates]);
                    
                    logActivity('lead_search', "Searched: {$params['niche']} in {$params['city']} | Found: " . count($searchResults['leads']) . " | Saved: {$saved} | Duplicates: {$duplicates}");
                    
                    $message = "Search complete! Found: " . count($searchResults['leads']) . " | Saved: {$saved} | Duplicates: {$duplicates}";
                    $messageType = 'success';
                }
            }
        }
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Search for Business Leads</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="search">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="niche">Business Type / Niche *</label>
                    <input type="text" id="niche" name="niche" class="form-control" placeholder="e.g. coaching center, restaurant, gym" value="<?php echo e($_POST['niche'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" placeholder="e.g. Agra, Delhi, Mumbai" value="<?php echo e($_POST['city'] ?? getSetting('default_city')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="state">State</label>
                    <input type="text" id="state" name="state" class="form-control" placeholder="e.g. Uttar Pradesh" value="<?php echo e($_POST['state'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" class="form-control" value="<?php echo e($_POST['country'] ?? 'India'); ?>">
                </div>
                <div class="form-group">
                    <label for="source">Source</label>
                    <select id="source" name="source" class="form-control">
                        <option value="google_places" <?php echo ($_POST['source'] ?? '') === 'google_places' ? 'selected' : ''; ?>>Google Places API</option>
                        <option value="serpapi" <?php echo ($_POST['source'] ?? '') === 'serpapi' ? 'selected' : ''; ?>>SerpAPI (Google Maps)</option>
                        <option value="apify" <?php echo ($_POST['source'] ?? '') === 'apify' ? 'selected' : ''; ?>>Apify Actor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="max_results">Max Leads to Fetch</label>
                    <input type="number" id="max_results" name="max_results" class="form-control" value="<?php echo e($_POST['max_results'] ?? '20'); ?>" min="1" max="100">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="radius">Search Radius (meters)</label>
                    <input type="number" id="radius" name="radius" class="form-control" value="<?php echo e($_POST['radius'] ?? '50000'); ?>" min="1000" max="50000">
                </div>
                <div class="form-group">
                    <label for="min_rating">Min Rating</label>
                    <select id="min_rating" name="min_rating" class="form-control">
                        <option value="">Any</option>
                        <option value="3.0">3.0+</option>
                        <option value="3.5">3.5+</option>
                        <option value="4.0">4.0+</option>
                        <option value="4.5">4.5+</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="max_rating">Max Rating</label>
                    <select id="max_rating" name="max_rating" class="form-control">
                        <option value="">Any</option>
                        <option value="3.0">Up to 3.0</option>
                        <option value="3.5">Up to 3.5</option>
                        <option value="4.0">Up to 4.0</option>
                        <option value="4.5">Up to 4.5</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="has_website">Has Website</label>
                    <select id="has_website" name="has_website" class="form-control">
                        <option value="any">Any</option>
                        <option value="yes">Yes - Has Website</option>
                        <option value="no">No - No Website</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="has_phone">Has Phone</label>
                    <select id="has_phone" name="has_phone" class="form-control">
                        <option value="any">Any</option>
                        <option value="yes">Yes - Has Phone</option>
                        <option value="no">No - No Phone</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-2">
                <button type="submit" class="btn btn-primary btn-lg">Search Leads</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
