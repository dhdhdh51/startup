<?php
$pageTitle = 'Bulk Audit';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';

// Handle POST bulk audit (non-JS fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $leadIds = $_POST['lead_ids'] ?? [];
    if (!empty($leadIds)) {
        $audited = 0;
        $errors = 0;
        $delay = (int)getSetting('delay_between_audits', 3);
        
        foreach ($leadIds as $id) {
            $result = auditLead((int)$id);
            if (isset($result['error'])) {
                $errors++;
            } else {
                $audited++;
            }
            if ($delay > 0) sleep($delay);
        }
        
        $message = "Bulk audit complete! Audited: {$audited} | Errors: {$errors}";
        $messageType = 'success';
        logActivity('bulk_audit', "Bulk audited {$audited} leads");
    }
}

// Get leads that need audit (New or never audited)
$db = getDB();
$stmt = $db->query("SELECT * FROM leads WHERE status = 'New' OR last_audited_at IS NULL ORDER BY created_at DESC LIMIT 100");
$pendingLeads = $stmt->fetchAll();

$stmt2 = $db->query("SELECT * FROM leads WHERE last_audited_at IS NOT NULL ORDER BY last_audited_at DESC LIMIT 50");
$auditedLeads = $stmt2->fetchAll();
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<!-- Progress indicator -->
<div class="card mb-2">
    <div class="card-body">
        <div class="d-flex justify-between align-center">
            <div>
                <strong>Pending Audits:</strong> <?php echo count($pendingLeads); ?> leads
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="runBulkAudit()">Audit Selected</button>
            </div>
        </div>
        <div id="auditProgress" class="mt-2" style="display:none;">
            <div class="progress-bar"><div class="fill" style="width:0%"></div></div>
            <p id="auditStatus" class="text-muted mt-1">Starting audit...</p>
        </div>
    </div>
</div>

<!-- Pending Leads -->
<div class="card">
    <div class="card-header">
        <h2>Leads Pending Audit (<?php echo count($pendingLeads); ?>)</h2>
        <span id="selectedCount" class="text-muted"></span>
    </div>
    <div class="card-body">
        <?php if (empty($pendingLeads)): ?>
            <div class="empty-state">
                <h3>All leads have been audited!</h3>
                <p>Search for new leads to audit.</p>
            </div>
        <?php else: ?>
        <form method="POST" id="bulkAuditForm">
            <?php echo csrfField(); ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                            <th>Business Name</th>
                            <th>City</th>
                            <th>Category</th>
                            <th>Website</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingLeads as $lead): ?>
                        <tr>
                            <td class="checkbox-col"><input type="checkbox" class="lead-checkbox" name="lead_ids[]" value="<?php echo $lead['id']; ?>"></td>
                            <td><a href="lead-view.php?id=<?php echo $lead['id']; ?>"><?php echo e($lead['business_name']); ?></a></td>
                            <td><?php echo e($lead['city']); ?></td>
                            <td><?php echo e($lead['category']); ?></td>
                            <td><?php echo $lead['website_url'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td>
                            <td><span class="badge <?php echo getStatusBadgeClass($lead['status']); ?>"><?php echo e($lead['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
