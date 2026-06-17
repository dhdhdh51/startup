<?php
$pageTitle = 'Settings';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $settings = [
        'agency_name', 'whatsapp_number', 'phone_number', 'email',
        'default_city', 'google_places_api_key', 'serpapi_key', 'apify_api_token',
        'default_search_source', 'curl_timeout', 'max_leads_per_search',
        'delay_between_audits', 'package_starter', 'package_growth',
        'package_seo_fix', 'package_pro', 'outreach_template_no_website',
        'outreach_template_weak_website', 'outreach_template_good_website'
    ];
    
    foreach ($settings as $key) {
        if (isset($_POST[$key])) {
            updateSetting($key, $_POST[$key]);
        }
    }
    
    $message = 'Settings saved successfully!';
    $messageType = 'success';
    logActivity('settings_update', 'Admin updated settings');
}

// Load current settings
$s = [];
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $s[$row['setting_key']] = $row['setting_value'];
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<form method="POST">
    <?php echo csrfField(); ?>
    
    <!-- Agency Settings -->
    <div class="card">
        <div class="card-header"><h2>Agency Settings</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="agency_name">Agency Name</label>
                    <input type="text" id="agency_name" name="agency_name" class="form-control" value="<?php echo e($s['agency_name'] ?? 'Bharat SEO'); ?>">
                </div>
                <div class="form-group">
                    <label for="whatsapp_number">WhatsApp Number</label>
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?php echo e($s['whatsapp_number'] ?? ''); ?>" placeholder="919876543210">
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?php echo e($s['phone_number'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo e($s['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="default_city">Default City</label>
                    <input type="text" id="default_city" name="default_city" class="form-control" value="<?php echo e($s['default_city'] ?? 'Agra'); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- API Keys -->
    <div class="card">
        <div class="card-header"><h2>API Keys</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="google_places_api_key">Google Places API Key</label>
                <input type="password" id="google_places_api_key" name="google_places_api_key" class="form-control" value="<?php echo e($s['google_places_api_key'] ?? ''); ?>" placeholder="Enter API key">
            </div>
            <div class="form-group">
                <label for="serpapi_key">SerpAPI Key</label>
                <input type="password" id="serpapi_key" name="serpapi_key" class="form-control" value="<?php echo e($s['serpapi_key'] ?? ''); ?>" placeholder="Enter API key">
            </div>
            <div class="form-group">
                <label for="apify_api_token">Apify API Token</label>
                <input type="password" id="apify_api_token" name="apify_api_token" class="form-control" value="<?php echo e($s['apify_api_token'] ?? ''); ?>" placeholder="Enter API token">
            </div>
            <div class="form-group">
                <label for="default_search_source">Default Search Source</label>
                <select id="default_search_source" name="default_search_source" class="form-control">
                    <option value="google_places" <?php echo ($s['default_search_source'] ?? '') === 'google_places' ? 'selected' : ''; ?>>Google Places API</option>
                    <option value="serpapi" <?php echo ($s['default_search_source'] ?? '') === 'serpapi' ? 'selected' : ''; ?>>SerpAPI</option>
                    <option value="apify" <?php echo ($s['default_search_source'] ?? '') === 'apify' ? 'selected' : ''; ?>>Apify</option>
                </select>
            </div>
        </div>
    </div>


    <!-- Technical Settings -->
    <div class="card">
        <div class="card-header"><h2>Technical Settings</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="curl_timeout">cURL Timeout (seconds)</label>
                    <input type="number" id="curl_timeout" name="curl_timeout" class="form-control" value="<?php echo e($s['curl_timeout'] ?? '30'); ?>" min="5" max="120">
                </div>
                <div class="form-group">
                    <label for="max_leads_per_search">Max Leads Per Search</label>
                    <input type="number" id="max_leads_per_search" name="max_leads_per_search" class="form-control" value="<?php echo e($s['max_leads_per_search'] ?? '100'); ?>" min="1" max="500">
                </div>
                <div class="form-group">
                    <label for="delay_between_audits">Delay Between Audits (seconds)</label>
                    <input type="number" id="delay_between_audits" name="delay_between_audits" class="form-control" value="<?php echo e($s['delay_between_audits'] ?? '3'); ?>" min="1" max="30">
                </div>
            </div>
        </div>
    </div>

    <!-- Package Settings -->
    <div class="card">
        <div class="card-header"><h2>Package Settings</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="package_starter">Starter Package (No Website)</label>
                <input type="text" id="package_starter" name="package_starter" class="form-control" value="<?php echo e($s['package_starter'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="package_growth">Growth Package (Score below 50)</label>
                <input type="text" id="package_growth" name="package_growth" class="form-control" value="<?php echo e($s['package_growth'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="package_seo_fix">SEO Fix Package (Score 51-70)</label>
                <input type="text" id="package_seo_fix" name="package_seo_fix" class="form-control" value="<?php echo e($s['package_seo_fix'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="package_pro">Pro Package (Score above 70)</label>
                <input type="text" id="package_pro" name="package_pro" class="form-control" value="<?php echo e($s['package_pro'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Outreach Templates -->
    <div class="card">
        <div class="card-header"><h2>Outreach Message Templates</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="outreach_template_no_website">Template: No Website</label>
                <textarea id="outreach_template_no_website" name="outreach_template_no_website" class="form-control" rows="5"><?php echo e($s['outreach_template_no_website'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="outreach_template_weak_website">Template: Weak Website (use {problems} for problem list)</label>
                <textarea id="outreach_template_weak_website" name="outreach_template_weak_website" class="form-control" rows="5"><?php echo e($s['outreach_template_weak_website'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="outreach_template_good_website">Template: Good Website</label>
                <textarea id="outreach_template_good_website" name="outreach_template_good_website" class="form-control" rows="5"><?php echo e($s['outreach_template_good_website'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <button type="submit" class="btn btn-primary btn-lg">Save All Settings</button>
    </div>
</form>

<?php require_once 'includes/admin-footer.php'; ?>
