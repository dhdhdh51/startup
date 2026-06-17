<?php
$pageTitle = 'Import CSV';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $message = 'Please upload a CSV file.';
            $messageType = 'danger';
        } else {
            $results = importCSV($file['tmp_name']);
            
            if (isset($results['error'])) {
                $message = $results['error'];
                $messageType = 'danger';
            } else {
                $message = "Import complete! Total rows: {$results['total']} | Saved: {$results['saved']} | Duplicates: {$results['duplicates']} | Errors: {$results['errors']}";
                $messageType = 'success';
                logActivity('csv_import', $message);
            }
        }
    } else {
        $message = 'Please select a CSV file to upload.';
        $messageType = 'danger';
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Import Leads from CSV</h2>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="csv_file">Select CSV File</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Import CSV</button>
        </form>
        
        <div class="mt-3">
            <h3 style="font-size:14px; margin-bottom:12px;">CSV Format Guide</h3>
            <p class="text-muted" style="font-size:13px;">Your CSV file should have a header row with the following columns (order doesn't matter):</p>
            <div class="table-responsive mt-1">
                <table>
                    <thead>
                        <tr>
                            <th>Column Name</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>business_name</code></td><td>Yes</td><td>Business name</td></tr>
                        <tr><td><code>phone</code></td><td>No</td><td>Phone number</td></tr>
                        <tr><td><code>website</code></td><td>No</td><td>Website URL</td></tr>
                        <tr><td><code>google_maps_url</code></td><td>No</td><td>Google Maps URL</td></tr>
                        <tr><td><code>address</code></td><td>No</td><td>Full address</td></tr>
                        <tr><td><code>city</code></td><td>No</td><td>City name</td></tr>
                        <tr><td><code>category</code></td><td>No</td><td>Business category/niche</td></tr>
                        <tr><td><code>rating</code></td><td>No</td><td>Google rating (1-5)</td></tr>
                        <tr><td><code>reviews</code></td><td>No</td><td>Number of reviews</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                <p class="text-muted" style="font-size:12px;"><strong>Example CSV:</strong></p>
                <pre style="background:var(--gray-100);padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;">business_name,phone,website,city,category,rating,reviews
"Sharma Coaching Center","9876543210","https://sharmacoaching.com","Agra","coaching center",4.5,120
"Gupta Gym","9988776655","","Delhi","gym",4.2,85</pre>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
