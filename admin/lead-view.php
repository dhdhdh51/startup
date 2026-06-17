<?php
/**
 * Bharat SEO CRM - Lead View
 * 
 * Full lead profile with audit data, package recommendation, outreach message.
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

$pageTitle = 'Lead: ' . $lead['business_name'];
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_notes') {
            $notes = trim($_POST['notes'] ?? '');
            $followUpDate = trim($_POST['follow_up_date'] ?? '') ?: null;
            $status = $_POST['status'] ?? $lead['status'];

            $stmt = db()->prepare("UPDATE leads SET notes = :notes, follow_up_date = :follow_up, status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':notes' => $notes,
                ':follow_up' => $followUpDate,
                ':status' => $status,
                ':id' => $leadId,
            ]);

            $lead['notes'] = $notes;
            $lead['follow_up_date'] = $followUpDate;
            $lead['status'] = $status;

            $message = 'Lead updated successfully.';
            $messageType = 'success';
            logActivity('lead_updated', 'Updated lead #' . $leadId . ': ' . $lead['business_name']);
        } elseif ($action === 'delete') {
            $stmt = db()->prepare("DELETE FROM leads WHERE id = :id");
            $stmt->execute([':id' => $leadId]);
            logActivity('lead_deleted', 'Deleted lead #' . $leadId . ': ' . $lead['business_name']);
            redirect('leads.php');
        }
    }
}

// Fetch audit data if available
$audit = null;
$stmt = db()->prepare("SELECT * FROM lead_audits WHERE lead_id = :lead_id ORDER BY audited_at DESC LIMIT 1");
$stmt->execute([':lead_id' => $leadId]);
$audit = $stmt->fetch();

// Generate outreach message
$whatsappTemplate = getSetting('whatsapp_message_template') ?: 'Hi {business_name}, I noticed your business could benefit from improved online visibility. We specialize in Google Map SEO and website optimization for local businesses like yours. Would you like a free audit?';
$outreachMessage = str_replace('{business_name}', $lead['business_name'], $whatsappTemplate);

// Determine recommended package based on audit score
$recommendedPackage = $lead['recommended_package'];
if (empty($recommendedPackage) && $lead['audit_score'] !== null) {
    $score = (int)$lead['audit_score'];
    if ($score < 30) {
        $recommendedPackage = 'Premium SEO Package - Full website rebuild + Google Maps SEO + Social Media';
    } elseif ($score < 50) {
        $recommendedPackage = 'Advanced SEO Package - Website optimization + Google Maps SEO + Content';
    } elseif ($score < 70) {
        $recommendedPackage = 'Standard SEO Package - On-page SEO + Google Maps optimization';
    } else {
        $recommendedPackage = 'Basic SEO Package - Monthly maintenance + Google Maps updates';
    }
}

// Parse recommendations from audit
$recommendations = [];
$problems = [];
if ($audit) {
    $rawRecs = $audit['recommendations'] ?? '';
    if (!empty($rawRecs)) {
        $decoded = json_decode($rawRecs, true);
        if (is_array($decoded)) {
            $recommendations = $decoded['recommendations'] ?? $decoded;
            $problems = $decoded['problems'] ?? [];
        } else {
            $recommendations = array_filter(array_map('trim', explode("\n", $rawRecs)));
        }
    }

    // Auto-detect problems from audit fields
    if (empty($problems)) {
        if (empty($audit['has_website']) || !$audit['has_website']) $problems[] = 'No website found';
        if (isset($audit['has_ssl']) && !$audit['has_ssl']) $problems[] = 'Website lacks SSL certificate';
        if (isset($audit['is_mobile_friendly']) && !$audit['is_mobile_friendly']) $problems[] = 'Website is not mobile-friendly';
        if (isset($audit['has_meta_title']) && !$audit['has_meta_title']) $problems[] = 'Missing meta title';
        if (isset($audit['has_meta_description']) && !$audit['has_meta_description']) $problems[] = 'Missing meta description';
        if (isset($audit['has_h1_tag']) && !$audit['has_h1_tag']) $problems[] = 'Missing H1 tag';
        if (isset($audit['has_schema_markup']) && !$audit['has_schema_markup']) $problems[] = 'No schema markup';
        if (isset($audit['has_sitemap']) && !$audit['has_sitemap']) $problems[] = 'No XML sitemap';
        if (isset($audit['has_robots_txt']) && !$audit['has_robots_txt']) $problems[] = 'No robots.txt';
        if (isset($audit['has_analytics']) && !$audit['has_analytics']) $problems[] = 'No analytics tracking';
        if (isset($audit['gmb_is_verified']) && !$audit['gmb_is_verified']) $problems[] = 'Google Business profile not verified';
        if (isset($audit['gmb_has_posts']) && !$audit['gmb_has_posts']) $problems[] = 'No Google Business posts';
        if (isset($audit['has_social_media']) && !$audit['has_social_media']) $problems[] = 'No social media presence';
    }

    // Auto-generate recommendations if empty
    if (empty($recommendations)) {
        if (!empty($problems)) {
            foreach ($problems as $problem) {
                switch ($problem) {
                    case 'No website found':
                        $recommendations[] = 'Create a professional website with SEO-optimized content';
                        break;
                    case 'Website lacks SSL certificate':
                        $recommendations[] = 'Install SSL certificate for secure HTTPS connection';
                        break;
                    case 'Website is not mobile-friendly':
                        $recommendations[] = 'Implement responsive design for mobile users';
                        break;
                    case 'Missing meta title':
                    case 'Missing meta description':
                        $recommendations[] = 'Optimize meta tags (title and description) for target keywords';
                        break;
                    case 'No schema markup':
                        $recommendations[] = 'Add local business schema markup for rich search results';
                        break;
                    case 'No XML sitemap':
                        $recommendations[] = 'Generate and submit XML sitemap to search engines';
                        break;
                    case 'Google Business profile not verified':
                        $recommendations[] = 'Verify and optimize Google Business Profile';
                        break;
                    case 'No Google Business posts':
                        $recommendations[] = 'Create weekly Google Business posts to improve visibility';
                        break;
                    case 'No social media presence':
                        $recommendations[] = 'Set up social media profiles on key platforms';
                        break;
                }
            }
            $recommendations = array_unique($recommendations);
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

<div class="lead-view-actions">
    <a href="leads.php" class="btn btn-outline">&laquo; Back to Leads</a>
    <form method="POST" action="" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this lead?');">Delete Lead</button>
    </form>
</div>

<!-- Business Details -->
<div class="card">
    <div class="card-header">
        <h3>Business Details</h3>
        <div><?php echo getLeadStatusBadge($lead['status']); ?> <?php echo getOpportunityBadge($lead['opportunity_level']); ?></div>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Business Name</label>
                <span><?php echo sanitize($lead['business_name']); ?></span>
            </div>
            <div class="detail-item">
                <label>Category</label>
                <span><?php echo sanitize($lead['category']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Address</label>
                <span><?php echo sanitize($lead['address']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>City</label>
                <span><?php echo sanitize($lead['city']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>State</label>
                <span><?php echo sanitize($lead['state']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Country</label>
                <span><?php echo sanitize($lead['country']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Source</label>
                <span><?php echo sanitize($lead['source']) ?: '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Added</label>
                <span><?php echo formatDate($lead['created_at']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Contact Details -->
<div class="card">
    <div class="card-header">
        <h3>Contact Details</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Phone</label>
                <span>
                    <?php if (!empty($lead['phone'])): ?>
                        <?php echo sanitize($lead['phone']); ?>
                        <a href="https://wa.me/<?php echo sanitize(preg_replace('/[^0-9]/', '', $lead['phone'])); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success">WhatsApp</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <span>
                    <?php if (!empty($lead['email'])): ?>
                        <a href="mailto:<?php echo sanitize($lead['email']); ?>"><?php echo sanitize($lead['email']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Website Details -->
<div class="card">
    <div class="card-header">
        <h3>Website Details</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Website URL</label>
                <span>
                    <?php if (!empty($lead['website_url'])): ?>
                        <a href="<?php echo sanitize($lead['website_url']); ?>" target="_blank" rel="noopener"><?php echo sanitize($lead['website_url']); ?></a>
                    <?php else: ?>
                        - (No website)
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Google Profile Details -->
<div class="card">
    <div class="card-header">
        <h3>Google Profile</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Google Maps URL</label>
                <span>
                    <?php if (!empty($lead['google_maps_url'])): ?>
                        <a href="<?php echo sanitize($lead['google_maps_url']); ?>" target="_blank" rel="noopener">View on Google Maps</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>Rating</label>
                <span><?php echo $lead['rating'] !== null ? number_format((float)$lead['rating'], 1) . ' / 5.0' : '-'; ?></span>
            </div>
            <div class="detail-item">
                <label>Reviews</label>
                <span><?php echo (int)$lead['review_count']; ?></span>
            </div>
            <div class="detail-item">
                <label>Coordinates</label>
                <span>
                    <?php if ($lead['latitude'] && $lead['longitude']): ?>
                        <?php echo $lead['latitude']; ?>, <?php echo $lead['longitude']; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Social Links (from audit) -->
<?php if ($audit): ?>
<div class="card">
    <div class="card-header">
        <h3>Social Links</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Facebook</label>
                <span>
                    <?php if (!empty($audit['social_facebook'])): ?>
                        <a href="<?php echo sanitize($audit['social_facebook']); ?>" target="_blank" rel="noopener"><?php echo sanitize($audit['social_facebook']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>Instagram</label>
                <span>
                    <?php if (!empty($audit['social_instagram'])): ?>
                        <a href="<?php echo sanitize($audit['social_instagram']); ?>" target="_blank" rel="noopener"><?php echo sanitize($audit['social_instagram']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>Twitter</label>
                <span>
                    <?php if (!empty($audit['social_twitter'])): ?>
                        <a href="<?php echo sanitize($audit['social_twitter']); ?>" target="_blank" rel="noopener"><?php echo sanitize($audit['social_twitter']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>LinkedIn</label>
                <span>
                    <?php if (!empty($audit['social_linkedin'])): ?>
                        <a href="<?php echo sanitize($audit['social_linkedin']); ?>" target="_blank" rel="noopener"><?php echo sanitize($audit['social_linkedin']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <label>YouTube</label>
                <span>
                    <?php if (!empty($audit['social_youtube'])): ?>
                        <a href="<?php echo sanitize($audit['social_youtube']); ?>" target="_blank" rel="noopener"><?php echo sanitize($audit['social_youtube']); ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Audit Score & Breakdown -->
<div class="card">
    <div class="card-header">
        <h3>Audit Score</h3>
    </div>
    <div class="card-body">
        <?php if ($audit): ?>
        <div class="audit-score-display">
            <div class="score-big"><?php echo (int)($audit['overall_score'] ?? $lead['audit_score'] ?? 0); ?>%</div>
            <p>Last audited: <?php echo formatDate($audit['audited_at']); ?></p>
        </div>

        <h4>Audit Breakdown</h4>
        <div class="audit-breakdown">
            <table class="table table-sm">
                <thead>
                    <tr><th>Check</th><th>Result</th></tr>
                </thead>
                <tbody>
                    <tr><td>Has Website</td><td><?php echo $audit['has_website'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <?php if ($audit['has_website']): ?>
                    <tr><td>SSL Certificate</td><td><?php echo $audit['has_ssl'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Mobile Friendly</td><td><?php echo $audit['is_mobile_friendly'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Meta Title</td><td><?php echo $audit['has_meta_title'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Meta Description</td><td><?php echo $audit['has_meta_description'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>H1 Tag</td><td><?php echo $audit['has_h1_tag'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Schema Markup</td><td><?php echo $audit['has_schema_markup'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>XML Sitemap</td><td><?php echo $audit['has_sitemap'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Robots.txt</td><td><?php echo $audit['has_robots_txt'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Analytics</td><td><?php echo $audit['has_analytics'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>Google Tag Manager</td><td><?php echo $audit['has_google_tag_manager'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Google Business Listing</td><td><?php echo $audit['has_gmb_listing'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <?php if ($audit['has_gmb_listing']): ?>
                    <tr><td>GMB Verified</td><td><?php echo $audit['gmb_is_verified'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>GMB Has Posts</td><td><?php echo $audit['gmb_has_posts'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <tr><td>GMB Has Reviews</td><td><?php echo $audit['gmb_has_reviews'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Social Media</td><td><?php echo $audit['has_social_media'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php elseif ($lead['audit_score'] !== null): ?>
        <div class="audit-score-display">
            <div class="score-big"><?php echo (int)$lead['audit_score']; ?>%</div>
            <p>Audit score available. Run a full audit for detailed breakdown.</p>
        </div>
        <?php else: ?>
        <p class="text-muted">No audit has been performed yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Problems Found -->
<?php if (!empty($problems)): ?>
<div class="card">
    <div class="card-header">
        <h3>Problems Found</h3>
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
        <h3>Recommendations</h3>
    </div>
    <div class="card-body">
        <ul class="recommendations-list">
            <?php foreach ($recommendations as $rec): ?>
            <li><?php echo sanitize(is_string($rec) ? $rec : ''); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Suggested Package -->
<div class="card">
    <div class="card-header">
        <h3>Suggested Bharat SEO Package</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($recommendedPackage)): ?>
        <div class="package-suggestion">
            <strong><?php echo sanitize($recommendedPackage); ?></strong>
        </div>
        <?php else: ?>
        <p class="text-muted">Run an audit to get a package recommendation.</p>
        <?php endif; ?>
    </div>
</div>

<!-- WhatsApp Outreach Message -->
<div class="card">
    <div class="card-header">
        <h3>WhatsApp Outreach Message</h3>
    </div>
    <div class="card-body">
        <div class="outreach-message">
            <textarea class="form-control" rows="4" readonly id="outreachMsg"><?php echo sanitize($lead['outreach_message'] ?: $outreachMessage); ?></textarea>
            <?php if (!empty($lead['phone'])): ?>
            <div class="form-actions">
                <a href="https://wa.me/<?php echo sanitize(preg_replace('/[^0-9]/', '', $lead['phone'])); ?>?text=<?php echo urlencode($lead['outreach_message'] ?: $outreachMessage); ?>" 
                   target="_blank" rel="noopener" class="btn btn-success">Send via WhatsApp</a>
                <button type="button" class="btn btn-outline" onclick="copyOutreach()">Copy Message</button>
            </div>
            <?php else: ?>
            <p class="text-muted">No phone number available for this lead.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notes & Follow-up -->
<div class="card">
    <div class="card-header">
        <h3>Notes & Status</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update_notes">

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="5" placeholder="Add notes about this lead..."><?php echo sanitize($lead['notes']); ?></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="follow_up_date">Follow-up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" class="form-control" 
                           value="<?php echo sanitize($lead['follow_up_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                        <option value="interested" <?php echo $lead['status'] === 'interested' ? 'selected' : ''; ?>>Interested</option>
                        <option value="proposal_sent" <?php echo $lead['status'] === 'proposal_sent' ? 'selected' : ''; ?>>Proposal Sent</option>
                        <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                        <option value="not_interested" <?php echo $lead['status'] === 'not_interested' ? 'selected' : ''; ?>>Not Interested</option>
                        <option value="follow_up" <?php echo $lead['status'] === 'follow_up' ? 'selected' : ''; ?>>Follow Up</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyOutreach() {
    var textarea = document.getElementById('outreachMsg');
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    document.execCommand('copy');
    alert('Message copied to clipboard!');
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
