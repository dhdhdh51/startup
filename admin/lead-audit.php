<?php
/**
 * Bharat SEO CRM - Lead Audit
 * 
 * Run and display a full SEO audit for a single lead.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$leadId = (int)($_GET['id'] ?? 0);
if ($leadId <= 0) {
    redirect('leads.php');
}

// Fetch lead
$stmt = db()->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $leadId]);
$lead = $stmt->fetch();

if (!$lead) {
    redirect('leads.php');
}

$pageTitle = 'Audit: ' . $lead['business_name'];
$message = '';
$messageType = '';
$auditResult = null;

// Handle audit request (POST or first visit with run=1)
$runAudit = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        $runAudit = true;
    }
} elseif (isset($_GET['run']) && $_GET['run'] === '1') {
    $runAudit = true;
}

if ($runAudit) {
    try {
        $auditResult = performFullAudit($leadId);
        if ($auditResult['success']) {
            $message = 'Audit completed successfully! Score: ' . $auditResult['score'] . '/100';
            $messageType = 'success';
            logActivity('lead_audited', 'Audited lead #' . $leadId . ': ' . $lead['business_name'] . ' - Score: ' . $auditResult['score']);
            // Refresh lead data
            $stmt = db()->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $leadId]);
            $lead = $stmt->fetch();
        } else {
            $message = 'Audit failed: ' . ($auditResult['error'] ?? 'Unknown error');
            $messageType = 'danger';
        }
    } catch (\Throwable $e) {
        $message = 'Audit error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch latest audit record
$stmt = db()->prepare("SELECT * FROM lead_audits WHERE lead_id = :lead_id ORDER BY audited_at DESC LIMIT 1");
$stmt->execute([':lead_id' => $leadId]);
$audit = $stmt->fetch();

// Parse raw data for detailed display
$rawData = [];
$problems = [];
$recommendations = [];
$breakdown = [];
$package = null;

if ($audit && !empty($audit['raw_data'])) {
    $rawData = json_decode($audit['raw_data'], true) ?: [];
    $problems = $rawData['problems'] ?? [];
    $recommendations = $rawData['recommendations'] ?? [];
    $breakdown = $rawData['score_breakdown'] ?? [];
    $package = $rawData['package'] ?? null;
}

// If raw_data missing but recommendations field exists
if (empty($problems) && !empty($audit['recommendations'])) {
    $decoded = json_decode($audit['recommendations'], true);
    if (is_array($decoded)) {
        $problems = $decoded['problems'] ?? [];
        $recommendations = $decoded['recommendations'] ?? [];
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo sanitize($message); ?>
</div>
<?php endif; ?>

<div class="lead-view-actions">
    <a href="lead-view.php?id=<?php echo $leadId; ?>" class="btn btn-outline">&laquo; Back to Lead</a>
    <form method="POST" action="" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <button type="submit" class="btn btn-primary">Re-audit Now</button>
    </form>
</div>

<!-- Lead Info Summary -->
<div class="card">
    <div class="card-header">
        <h3><?php echo sanitize($lead['business_name']); ?></h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Website</label>
                <span>
                    <?php if (!empty($lead['website_url'])): ?>
                        <a href="<?php echo sanitize($lead['website_url']); ?>" target="_blank" rel="noopener"><?php echo sanitize($lead['website_url']); ?></a>
                    <?php else: ?>
                        <span class="text-danger">No website</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>City</label>
                <span><?php echo sanitize($lead['city']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Last Audited</label>
                <span><?php echo $lead['last_audited_at'] ? formatDate($lead['last_audited_at']) : 'Never'; ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($audit): ?>
<!-- Overall Score -->
<div class="card">
    <div class="card-header">
        <h3>Audit Score</h3>
    </div>
    <div class="card-body">
        <div class="audit-score-display">
            <div class="score-big"><?php echo (int)$audit['overall_score']; ?>/100</div>
            <p class="text-muted">
                <?php echo sanitize(getOpportunityLevel((int)$audit['overall_score'])); ?>
            </p>
            <p>Audited: <?php echo formatDate($audit['audited_at']); ?></p>
        </div>
    </div>
</div>

<!-- Score Breakdown -->
<?php if (!empty($breakdown)): ?>
<div class="card">
    <div class="card-header">
        <h3>Score Breakdown</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr><th>Category</th><th>Score</th><th>Max</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Website Presence</td>
                    <td><?php echo (int)($breakdown['website_presence'] ?? 0); ?></td>
                    <td>15</td>
                </tr>
                <tr>
                    <td>Website SEO Basics</td>
                    <td><?php echo (int)($breakdown['website_seo'] ?? 0); ?></td>
                    <td>20</td>
                </tr>
                <tr>
                    <td>Mobile Readiness</td>
                    <td><?php echo (int)($breakdown['mobile_readiness'] ?? 0); ?></td>
                    <td>10</td>
                </tr>
                <tr>
                    <td>Local Conversion Elements</td>
                    <td><?php echo (int)($breakdown['local_conversion'] ?? 0); ?></td>
                    <td>20</td>
                </tr>
                <tr>
                    <td>Google Profile Strength</td>
                    <td><?php echo (int)($breakdown['google_profile'] ?? 0); ?></td>
                    <td>20</td>
                </tr>
                <tr>
                    <td>Social Presence</td>
                    <td><?php echo (int)($breakdown['social_presence'] ?? 0); ?></td>
                    <td>10</td>
                </tr>
                <tr>
                    <td>Lead Readiness</td>
                    <td><?php echo (int)($breakdown['lead_readiness'] ?? 0); ?></td>
                    <td>5</td>
                </tr>
                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong><?php echo (int)$audit['overall_score']; ?></strong></td>
                    <td><strong>100</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Audit Details -->
<div class="card">
    <div class="card-header">
        <h3>Audit Details</h3>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr><th>Check</th><th>Result</th></tr>
            </thead>
            <tbody>
                <tr><td>Has Website</td><td><?php echo $audit['has_website'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>SSL Certificate</td><td><?php echo $audit['has_ssl'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Mobile Friendly</td><td><?php echo $audit['is_mobile_friendly'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Meta Title</td><td><?php echo $audit['has_meta_title'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Meta Description</td><td><?php echo $audit['has_meta_description'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>H1 Tag</td><td><?php echo $audit['has_h1_tag'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Schema Markup</td><td><?php echo $audit['has_schema_markup'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Google Business Listing</td><td><?php echo $audit['has_gmb_listing'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                <tr><td>Social Media</td><td><?php echo $audit['has_social_media'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Problems Found -->
<?php if (!empty($problems)): ?>
<div class="card">
    <div class="card-header">
        <h3>Problems Found (<?php echo count($problems); ?>)</h3>
    </div>
    <div class="card-body">
        <ul class="problems-list">
            <?php foreach ($problems as $problem): ?>
            <li class="text-danger"><?php echo sanitize($problem); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Recommendations -->
<?php if (!empty($recommendations)): ?>
<div class="card">
    <div class="card-header">
        <h3>Recommendations (<?php echo count($recommendations); ?>)</h3>
    </div>
    <div class="card-body">
        <ul class="recommendations-list">
            <?php foreach ($recommendations as $rec): ?>
            <li><?php echo sanitize($rec); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Package Recommendation -->
<?php if (!empty($package)): ?>
<div class="card">
    <div class="card-header">
        <h3>Recommended Package</h3>
    </div>
    <div class="card-body">
        <div class="package-suggestion">
            <h4><?php echo sanitize($package['name'] ?? ''); ?></h4>
            <p class="package-price">
                <strong><?php echo sanitize($package['price'] ?? ''); ?></strong>
                <?php if (!empty($package['recurring'])): ?>
                    + <?php echo sanitize($package['recurring']); ?>
                <?php endif; ?>
            </p>
            <p><?php echo sanitize($package['description'] ?? ''); ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- No audit yet -->
<div class="card">
    <div class="card-body">
        <p class="text-muted">No audit has been performed for this lead yet.</p>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <button type="submit" class="btn btn-primary">Run Audit Now</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
