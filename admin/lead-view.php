<?php
$pageTitle = 'Lead Details';
require_once 'includes/admin-header.php';

$leadId = (int)($_GET['id'] ?? 0);
$lead = getLeadById($leadId);

if (!$lead) {
    echo '<div class="alert alert-danger">Lead not found.</div>';
    echo '<a href="leads.php" class="btn btn-primary">Back to Leads</a>';
    require_once 'includes/admin-footer.php';
    exit;
}

$audit = getLeadAudit($leadId);
$problems = $audit ? json_decode($audit['problems'] ?? '[]', true) : [];
$recommendations = $audit ? json_decode($audit['recommendations'] ?? '[]', true) : [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_notes') {
        $notes = $_POST['notes'] ?? '';
        $followUp = $_POST['follow_up_date'] ?? null;
        updateLeadNotes($leadId, $notes, $followUp ?: null);
        $lead = getLeadById($leadId);
        echo '<div class="alert alert-success">Notes updated successfully.</div>';
    }
    
    if ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        updateLeadStatus($leadId, $status);
        $lead = getLeadById($leadId);
        echo '<div class="alert alert-success">Status updated.</div>';
    }
}
?>

<div class="mb-2">
    <a href="leads.php" class="btn btn-secondary">&larr; Back to Leads</a>
    <a href="lead-audit.php?id=<?php echo $lead['id']; ?>" class="btn btn-primary">Run Audit</a>
    <?php if ($lead['phone']): ?>
    <a href="<?php echo getWhatsAppLink($lead['phone'], $lead['outreach_message'] ?? ''); ?>" target="_blank" class="btn btn-success">WhatsApp</a>
    <?php endif; ?>
</div>

<div class="lead-detail-grid">
    <!-- Business Details -->
    <div class="card">
        <div class="card-header"><h2>Business Details</h2></div>
        <div class="card-body">
            <div class="detail-row"><span class="label">Business Name</span><span class="value"><?php echo e($lead['business_name']); ?></span></div>
            <div class="detail-row"><span class="label">Category</span><span class="value"><?php echo e($lead['category']); ?></span></div>
            <div class="detail-row"><span class="label">Address</span><span class="value"><?php echo e($lead['address']); ?></span></div>
            <div class="detail-row"><span class="label">City</span><span class="value"><?php echo e($lead['city']); ?></span></div>
            <div class="detail-row"><span class="label">State</span><span class="value"><?php echo e($lead['state']); ?></span></div>
            <div class="detail-row"><span class="label">Country</span><span class="value"><?php echo e($lead['country']); ?></span></div>
            <div class="detail-row"><span class="label">Source</span><span class="value"><?php echo e($lead['source']); ?></span></div>
            <div class="detail-row"><span class="label">Added</span><span class="value"><?php echo formatDateTime($lead['created_at']); ?></span></div>
        </div>
    </div>

    <!-- Contact Details -->
    <div class="card">
        <div class="card-header"><h2>Contact Details</h2></div>
        <div class="card-body">
            <div class="detail-row"><span class="label">Phone</span><span class="value"><?php echo e($lead['phone'] ?: 'Not available'); ?></span></div>
            <div class="detail-row"><span class="label">Email</span><span class="value"><?php echo e($lead['email'] ?: 'Not available'); ?></span></div>
            <div class="detail-row"><span class="label">Website</span><span class="value"><?php echo $lead['website_url'] ? '<a href="' . e($lead['website_url']) . '" target="_blank">' . e($lead['website_url']) . '</a>' : 'No website'; ?></span></div>
            <div class="detail-row"><span class="label">Google Maps</span><span class="value"><?php echo $lead['google_maps_url'] ? '<a href="' . e($lead['google_maps_url']) . '" target="_blank">View on Maps</a>' : '-'; ?></span></div>
        </div>
    </div>

    <!-- Google Profile -->
    <div class="card">
        <div class="card-header"><h2>Google Profile</h2></div>
        <div class="card-body">
            <div class="detail-row"><span class="label">Rating</span><span class="value"><?php echo $lead['rating'] ? e($lead['rating']) . ' &#9733;' : '-'; ?></span></div>
            <div class="detail-row"><span class="label">Reviews</span><span class="value"><?php echo number_format($lead['review_count']); ?></span></div>
            <div class="detail-row"><span class="label">Latitude</span><span class="value"><?php echo e($lead['latitude']); ?></span></div>
            <div class="detail-row"><span class="label">Longitude</span><span class="value"><?php echo e($lead['longitude']); ?></span></div>
        </div>
    </div>

    <!-- Audit Score -->
    <div class="card">
        <div class="card-header"><h2>Audit Score</h2></div>
        <div class="card-body">
            <?php if ($lead['audit_score'] !== null): ?>
                <div class="text-center mb-2">
                    <div class="score-display <?php echo $lead['audit_score'] <= 30 ? 'score-low' : ($lead['audit_score'] <= 50 ? 'score-medium' : ($lead['audit_score'] <= 70 ? 'score-high' : 'score-great')); ?>" style="width:80px;height:80px;font-size:24px;margin:0 auto;">
                        <?php echo $lead['audit_score']; ?>
                    </div>
                </div>
                <div class="detail-row"><span class="label">Opportunity</span><span class="value"><span class="badge <?php echo getScoreBadgeClass($lead['audit_score']); ?>"><?php echo e($lead['opportunity_level']); ?></span></span></div>
                <div class="detail-row"><span class="label">Package</span><span class="value"><?php echo e($lead['recommended_package']); ?></span></div>
                <div class="detail-row"><span class="label">Last Audited</span><span class="value"><?php echo formatDateTime($lead['last_audited_at']); ?></span></div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Not audited yet</p>
                    <a href="lead-audit.php?id=<?php echo $lead['id']; ?>" class="btn btn-primary btn-sm">Run Audit Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php if ($audit): ?>
<!-- Social Links -->
<div class="card">
    <div class="card-header"><h2>Social Links Found</h2></div>
    <div class="card-body">
        <div class="detail-row"><span class="label">Instagram</span><span class="value"><?php echo $audit['instagram_link'] ? '<a href="' . e($audit['instagram_link']) . '" target="_blank">' . e($audit['instagram_link']) . '</a>' : 'Not found'; ?></span></div>
        <div class="detail-row"><span class="label">Facebook</span><span class="value"><?php echo $audit['facebook_link'] ? '<a href="' . e($audit['facebook_link']) . '" target="_blank">' . e($audit['facebook_link']) . '</a>' : 'Not found'; ?></span></div>
        <div class="detail-row"><span class="label">YouTube</span><span class="value"><?php echo $audit['youtube_link'] ? '<a href="' . e($audit['youtube_link']) . '" target="_blank">' . e($audit['youtube_link']) . '</a>' : 'Not found'; ?></span></div>
    </div>
</div>
<?php endif; ?>

<!-- Problems & Recommendations -->
<?php if (!empty($problems)): ?>
<div class="card">
    <div class="card-header"><h2>Problems Found</h2></div>
    <div class="card-body">
        <ul class="problem-list">
            <?php foreach ($problems as $problem): ?>
                <li>&#10060; <?php echo e($problem); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($recommendations)): ?>
<div class="card">
    <div class="card-header"><h2>Recommendations</h2></div>
    <div class="card-body">
        <ul class="recommendation-list">
            <?php foreach ($recommendations as $rec): ?>
                <li>&#10004; <?php echo e($rec); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Outreach Message -->
<?php if ($lead['outreach_message']): ?>
<div class="card">
    <div class="card-header"><h2>WhatsApp Outreach Message</h2></div>
    <div class="card-body">
        <div class="outreach-box" id="outreachMsg">
            <button class="copy-btn" data-target="outreachMsg">Copy</button>
            <?php echo nl2br(e($lead['outreach_message'])); ?>
        </div>
        <?php if ($lead['phone']): ?>
        <div class="mt-2">
            <a href="<?php echo getWhatsAppLink($lead['phone'], $lead['outreach_message']); ?>" target="_blank" class="btn btn-success">Open in WhatsApp</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Notes & Status Update -->
<div class="lead-detail-grid">
    <div class="card">
        <div class="card-header"><h2>Notes & Follow-up</h2></div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_notes">
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo e($lead['notes']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="follow_up_date">Follow-up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" class="form-control" value="<?php echo e($lead['follow_up_date']); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Save Notes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Update Status</h2></div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_status">
                <div class="form-group">
                    <label for="status">Current Status</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach (['New', 'Audited', 'Contacted', 'Interested', 'Follow-up', 'Converted', 'Not interested'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $lead['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
