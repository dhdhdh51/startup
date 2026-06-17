<?php
require_once __DIR__ . '/../functions.php';
initSession();
requireLogin();

// Handle export
if (isset($_GET['download'])) {
    $filters = [
        'city' => $_GET['city'] ?? '',
        'category' => $_GET['category'] ?? '',
        'status' => $_GET['status'] ?? '',
        'has_website' => $_GET['has_website'] ?? '',
        'high_opportunity' => $_GET['high_opportunity'] ?? '',
    ];
    
    $leads = exportLeadsCSV($filters);
    
    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bharat_seo_leads_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Business Name', 'Phone', 'Website', 'City', 'Category', 'Rating', 'Reviews', 'Audit Score', 'Opportunity Level', 'Status', 'Outreach Message']);
    
    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead['business_name'],
            $lead['phone'],
            $lead['website_url'],
            $lead['city'],
            $lead['category'],
            $lead['rating'],
            $lead['review_count'],
            $lead['audit_score'],
            $lead['opportunity_level'],
            $lead['status'],
            $lead['outreach_message'],
        ]);
    }
    
    fclose($output);
    logActivity('export', "Exported " . count($leads) . " leads to CSV");
    exit;
}

$pageTitle = 'Export Leads';
require_once 'includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Export Leads to CSV</h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">Select filters to export specific leads, or export all.</p>
        
        <form method="GET" action="">
            <input type="hidden" name="download" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" class="form-control" placeholder="All cities">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" class="form-control" placeholder="All categories">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="New">New</option>
                        <option value="Audited">Audited</option>
                        <option value="Contacted">Contacted</option>
                        <option value="Interested">Interested</option>
                        <option value="Follow-up">Follow-up</option>
                        <option value="Converted">Converted</option>
                        <option value="Not interested">Not interested</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="has_website">Website</label>
                    <select id="has_website" name="has_website" class="form-control">
                        <option value="">All</option>
                        <option value="yes">Has Website</option>
                        <option value="no">No Website</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="high_opportunity">Opportunity</label>
                    <select id="high_opportunity" name="high_opportunity" class="form-control">
                        <option value="">All</option>
                        <option value="1">High Opportunity Only</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-2">
                <button type="submit" class="btn btn-primary btn-lg">Download CSV</button>
                <a href="export.php?download=1" class="btn btn-secondary">Export All Leads</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Export Format</h2></div>
    <div class="card-body">
        <p class="text-muted">Exported CSV will include the following columns:</p>
        <ul style="padding-left:20px; color: var(--gray-600); font-size:13px;">
            <li>Business Name</li>
            <li>Phone</li>
            <li>Website</li>
            <li>City</li>
            <li>Category</li>
            <li>Rating</li>
            <li>Reviews</li>
            <li>Audit Score</li>
            <li>Opportunity Level</li>
            <li>Status</li>
            <li>Outreach Message</li>
        </ul>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
