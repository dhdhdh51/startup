<?php
/**
 * Bharat SEO CRM - Import CSV
 * 
 * CSV file upload, parse, deduplicate, and save leads.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = 'Import CSV';
$message = '';
$messageType = '';
$importResults = null;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            ];
            $errorCode = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = $errorMessages[$errorCode] ?? 'File upload failed.';
            $messageType = 'danger';
        } else {
            $file = $_FILES['csv_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'csv') {
                $message = 'Please upload a CSV file (.csv extension).';
                $messageType = 'danger';
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $message = 'File is too large. Maximum size is 10MB.';
                $messageType = 'danger';
            } else {
                // Parse CSV
                $handle = fopen($file['tmp_name'], 'r');
                if (!$handle) {
                    $message = 'Could not read the uploaded file.';
                    $messageType = 'danger';
                } else {
                    // Read header row
                    $header = fgetcsv($handle);
                    if (!$header) {
                        $message = 'CSV file is empty or has no header row.';
                        $messageType = 'danger';
                        fclose($handle);
                    } else {
                        // Normalize header names (lowercase, trim)
                        $header = array_map(function($h) {
                            return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h)));
                        }, $header);

                        // Map expected columns
                        $columnMap = [
                            'business_name' => ['business_name', 'name', 'business', 'company', 'company_name'],
                            'phone' => ['phone', 'phone_number', 'tel', 'telephone', 'mobile'],
                            'website' => ['website', 'website_url', 'url', 'web', 'site'],
                            'google_maps_url' => ['google_maps_url', 'maps_url', 'google_maps', 'maps_link', 'google_url'],
                            'address' => ['address', 'full_address', 'street', 'location'],
                            'city' => ['city', 'town', 'locality'],
                            'state' => ['state', 'province', 'region'],
                            'country' => ['country', 'nation'],
                            'category' => ['category', 'type', 'business_type', 'niche', 'industry'],
                            'rating' => ['rating', 'stars', 'score', 'google_rating'],
                            'reviews' => ['reviews', 'review_count', 'num_reviews', 'total_reviews'],
                            'email' => ['email', 'email_address', 'mail'],
                        ];

                        $fieldIndices = [];
                        foreach ($columnMap as $field => $aliases) {
                            foreach ($aliases as $alias) {
                                $idx = array_search($alias, $header);
                                if ($idx !== false) {
                                    $fieldIndices[$field] = $idx;
                                    break;
                                }
                            }
                        }

                        if (!isset($fieldIndices['business_name'])) {
                            $message = 'CSV must have a "business_name" (or "name", "company") column. Found columns: ' . implode(', ', $header);
                            $messageType = 'danger';
                            fclose($handle);
                        } else {
                            $imported = 0;
                            $duplicates = 0;
                            $errors = 0;
                            $skipped = 0;
                            $rows = 0;
                            $errorDetails = [];
                            $defaultSource = $_POST['source'] ?? 'csv_import';
                            $defaultCity = trim($_POST['default_city'] ?? '');
                            $defaultState = trim($_POST['default_state'] ?? '');
                            $defaultCountry = trim($_POST['default_country'] ?? 'India');

                            while (($row = fgetcsv($handle)) !== false) {
                                $rows++;
                                if (count($row) < 1 || (count($row) === 1 && empty(trim($row[0])))) {
                                    $skipped++;
                                    continue;
                                }

                                $businessName = trim($row[$fieldIndices['business_name']] ?? '');
                                if (empty($businessName)) {
                                    $skipped++;
                                    continue;
                                }

                                $leadData = [
                                    'business_name' => $businessName,
                                    'phone' => isset($fieldIndices['phone']) ? trim($row[$fieldIndices['phone']] ?? '') : '',
                                    'website_url' => isset($fieldIndices['website']) ? trim($row[$fieldIndices['website']] ?? '') : '',
                                    'google_maps_url' => isset($fieldIndices['google_maps_url']) ? trim($row[$fieldIndices['google_maps_url']] ?? '') : '',
                                    'address' => isset($fieldIndices['address']) ? trim($row[$fieldIndices['address']] ?? '') : '',
                                    'city' => isset($fieldIndices['city']) ? trim($row[$fieldIndices['city']] ?? '') : $defaultCity,
                                    'state' => isset($fieldIndices['state']) ? trim($row[$fieldIndices['state']] ?? '') : $defaultState,
                                    'country' => isset($fieldIndices['country']) ? trim($row[$fieldIndices['country']] ?? '') : $defaultCountry,
                                    'category' => isset($fieldIndices['category']) ? trim($row[$fieldIndices['category']] ?? '') : '',
                                    'rating' => isset($fieldIndices['rating']) ? (float)($row[$fieldIndices['rating']] ?? 0) : null,
                                    'review_count' => isset($fieldIndices['reviews']) ? (int)($row[$fieldIndices['reviews']] ?? 0) : 0,
                                    'email' => isset($fieldIndices['email']) ? trim($row[$fieldIndices['email']] ?? '') : '',
                                    'source' => $defaultSource,
                                    'source_id' => '',
                                ];

                                // Apply defaults for empty city/state
                                if (empty($leadData['city'])) $leadData['city'] = $defaultCity;
                                if (empty($leadData['state'])) $leadData['state'] = $defaultState;

                                try {
                                    $result = saveLead($leadData);
                                    if ($result['action'] === 'inserted') {
                                        $imported++;
                                    } elseif ($result['action'] === 'updated' || $result['action'] === 'skipped') {
                                        $duplicates++;
                                    }
                                } catch (Exception $e) {
                                    $errors++;
                                    if (count($errorDetails) < 5) {
                                        $errorDetails[] = "Row $rows ($businessName): " . $e->getMessage();
                                    }
                                }
                            }

                            fclose($handle);

                            // Log the import
                            saveSearchLog([
                                'niche' => 'CSV Import: ' . $file['name'],
                                'city' => $defaultCity,
                                'state' => $defaultState,
                                'country' => $defaultCountry,
                                'source' => $defaultSource,
                                'max_results' => $rows,
                            ], [
                                'total_found' => $rows,
                                'total_saved' => $imported,
                                'total_duplicates' => $duplicates,
                            ]);

                            logActivity('csv_import', "Imported from {$file['name']}: $imported new, $duplicates duplicates, $errors errors, $skipped skipped");

                            $importResults = [
                                'total_rows' => $rows,
                                'imported' => $imported,
                                'duplicates' => $duplicates,
                                'errors' => $errors,
                                'skipped' => $skipped,
                                'error_details' => $errorDetails,
                            ];

                            $message = "Import complete. $imported new leads imported, $duplicates duplicates found, $errors errors, $skipped skipped.";
                            $messageType = $imported > 0 ? 'success' : ($duplicates > 0 ? 'warning' : 'info');
                        }
                    }
                }
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

<?php if ($importResults): ?>
<!-- Import Results Summary -->
<div class="card">
    <div class="card-header">
        <h3>Import Results</h3>
    </div>
    <div class="card-body">
        <div class="stats-grid">
            <div class="stat-card stat-card-success">
                <div class="stat-value"><?php echo $importResults['imported']; ?></div>
                <div class="stat-label">Imported</div>
            </div>
            <div class="stat-card stat-card-warning">
                <div class="stat-value"><?php echo $importResults['duplicates']; ?></div>
                <div class="stat-label">Duplicates</div>
            </div>
            <div class="stat-card stat-card-danger">
                <div class="stat-value"><?php echo $importResults['errors']; ?></div>
                <div class="stat-label">Errors</div>
            </div>
            <div class="stat-card stat-card-info">
                <div class="stat-value"><?php echo $importResults['skipped']; ?></div>
                <div class="stat-label">Skipped</div>
            </div>
        </div>

        <?php if (!empty($importResults['error_details'])): ?>
        <h4>Error Details</h4>
        <ul class="error-list">
            <?php foreach ($importResults['error_details'] as $detail): ?>
            <li class="text-danger"><?php echo sanitize($detail); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="form-actions">
            <a href="leads.php" class="btn btn-primary">View All Leads</a>
            <a href="import-csv.php" class="btn btn-outline">Import Another File</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Upload Form -->
<div class="card">
    <div class="card-header">
        <h3>Upload CSV File</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="form-group">
                <label for="csv_file">Select CSV File *</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                <small class="form-text text-muted">Maximum file size: 10MB. File must have a header row.</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="default_city">Default City</label>
                    <input type="text" id="default_city" name="default_city" class="form-control" 
                           placeholder="Applied when city column is empty">
                </div>
                <div class="form-group">
                    <label for="default_state">Default State</label>
                    <input type="text" id="default_state" name="default_state" class="form-control" 
                           placeholder="Applied when state column is empty">
                </div>
                <div class="form-group">
                    <label for="default_country">Default Country</label>
                    <input type="text" id="default_country" name="default_country" class="form-control" 
                           value="India">
                </div>
                <div class="form-group">
                    <label for="source">Source Label</label>
                    <input type="text" id="source" name="source" class="form-control" 
                           value="csv_import" placeholder="e.g., csv_import, justdial, indiamart">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Upload and Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Expected CSV Format -->
<div class="card">
    <div class="card-header">
        <h3>Expected CSV Format</h3>
    </div>
    <div class="card-body">
        <p>Your CSV file should have a header row with column names. The following columns are recognized:</p>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Column Name</th>
                        <th>Also Accepted As</th>
                        <th>Required</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>business_name</code></td><td>name, business, company, company_name</td><td><strong>Yes</strong></td></tr>
                    <tr><td><code>phone</code></td><td>phone_number, tel, telephone, mobile</td><td>No</td></tr>
                    <tr><td><code>website</code></td><td>website_url, url, web, site</td><td>No</td></tr>
                    <tr><td><code>google_maps_url</code></td><td>maps_url, google_maps, maps_link</td><td>No</td></tr>
                    <tr><td><code>address</code></td><td>full_address, street, location</td><td>No</td></tr>
                    <tr><td><code>city</code></td><td>town, locality</td><td>No</td></tr>
                    <tr><td><code>category</code></td><td>type, business_type, niche, industry</td><td>No</td></tr>
                    <tr><td><code>rating</code></td><td>stars, score, google_rating</td><td>No</td></tr>
                    <tr><td><code>reviews</code></td><td>review_count, num_reviews, total_reviews</td><td>No</td></tr>
                    <tr><td><code>email</code></td><td>email_address, mail</td><td>No</td></tr>
                </tbody>
            </table>
        </div>

        <h4>Example CSV</h4>
        <pre class="code-block">business_name,phone,website,google_maps_url,address,city,category,rating,reviews
"Sharma Electronics","+91-9876543210","https://sharmaelectronics.com","","MG Road, Pune","Pune","Electronics Store",4.2,85
"Delhi Dental Clinic","+91-9812345678","","https://maps.google.com/...","Connaught Place","Delhi","Dentist",4.5,120</pre>

        <h4>Deduplication</h4>
        <p>Each imported lead is checked against existing records. Duplicates are detected by:</p>
        <ul>
            <li>Phone number match (last 10 digits)</li>
            <li>Website URL match (domain comparison)</li>
            <li>Business name + city match (case-insensitive)</li>
            <li>Source ID match (e.g., Google Place ID)</li>
        </ul>
        <p>When a duplicate is found, missing fields on the existing record are filled in from the new data.</p>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
