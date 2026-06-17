<?php
$pageTitle = 'Outreach';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';
$outreachLeads = [];

// Handle generate messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate') {
        $leadIds = $_POST['lead_ids'] ?? [];
        if (!empty($leadIds)) {
            $db = getDB();
            $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
            $stmt = $db->prepare("SELECT * FROM leads WHERE id IN ({$placeholders})");
            $stmt->execute($leadIds);
            $outreachLeads = $stmt->fetchAll();
            $message = count($outreachLeads) . ' leads loaded for outreach.';
            $messageType = 'success';
        }
    }
    
    if ($action === 'mark_contacted') {
        $leadIds = $_POST['lead_ids'] ?? [];
        foreach ($leadIds as $id) {
            updateLeadStatus((int)$id, 'Contacted');
        }
        $message = count($leadIds) . ' leads marked as contacted.';
        $messageType = 'success';
        logActivity('outreach', "Marked " . count($leadIds) . " leads as contacted");
    }
}

// Default: show leads with outreach messages ready
if (empty($outreachLeads)) {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM leads WHERE outreach_message IS NOT NULL AND outreach_message != '' AND status IN ('Audited', 'New') ORDER BY audit_score ASC LIMIT 50");
    $outreachLeads = $stmt->fetchAll();
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<div class="card mb-2">
    <div class="card-body">
        <div class="d-flex justify-between align-center" style="flex-wrap:wrap; gap:12px;">
            <div>
                <strong>Outreach Ready:</strong> <?php echo count($outreachLeads); ?> leads with messages
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="document.getElementById('markContactedForm').submit()">Mark Selected as Contacted</button>
                <span id="selectedCount" class="text-muted"></span>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="markContactedForm">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="mark_contacted">

<?php if (empty($outreachLeads)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <h3>No outreach messages ready</h3>
                <p>Audit leads first to generate outreach messages.</p>
                <a href="bulk-audit.php" class="btn btn-primary mt-2">Run Bulk Audit</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($outreachLeads as $lead): ?>
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-center gap-2">
                <input type="checkbox" class="lead-checkbox" name="lead_ids[]" value="<?php echo $lead['id']; ?>">
                <h2 style="font-size:15px;"><?php echo e($lead['business_name']); ?></h2>
                <span class="badge <?php echo getScoreBadgeClass($lead['audit_score']); ?>"><?php echo $lead['audit_score'] ?? '-'; ?></span>
            </div>
            <div>
                <span class="text-muted"><?php echo e($lead['city']); ?> | <?php echo e($lead['phone']); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="outreach-box" id="msg-<?php echo $lead['id']; ?>">
                <button type="button" class="copy-btn" data-target="msg-<?php echo $lead['id']; ?>">Copy</button>
                <?php echo nl2br(e($lead['outreach_message'])); ?>
            </div>
            <div class="mt-1 btn-group">
                <?php if ($lead['phone']): ?>
                <a href="<?php echo getWhatsAppLink($lead['phone'], $lead['outreach_message']); ?>" target="_blank" class="btn btn-sm btn-success">Open WhatsApp</a>
                <?php endif; ?>
                <a href="lead-view.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline">View Lead</a>
                <button type="button" class="btn btn-sm btn-warning" onclick="markContacted(<?php echo $lead['id']; ?>)">Mark Contacted</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</form>

<?php require_once 'includes/admin-footer.php'; ?>
