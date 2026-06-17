<?php
$pageTitle = 'Lead Audit';
require_once 'includes/admin-header.php';

$leadId = (int)($_GET['id'] ?? 0);
$isAjax = isset($_GET['ajax']);

$lead = getLeadById($leadId);

if (!$lead) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Lead not found']);
        exit;
    }
    echo '<div class="alert alert-danger">Lead not found.</div>';
    echo '<a href="leads.php" class="btn btn-primary">Back to Leads</a>';
    require_once 'includes/admin-footer.php';
    exit;
}

// Run audit
$result = auditLead($leadId);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

$lead = getLeadById($leadId); // Refresh
?>

<div class="mb-2">
    <a href="leads.php" class="btn btn-secondary">&larr; Back to Leads</a>
    <a href="lead-view.php?id=<?php echo $lead['id']; ?>" class="btn btn-outline">View Lead</a>
</div>

<?php if (isset($result['error'])): ?>
    <div class="alert alert-danger"><?php echo e($result['error']); ?></div>
<?php else: ?>
    <div class="alert alert-success">
        Audit completed for <strong><?php echo e($lead['business_name']); ?></strong> | 
        Score: <strong><?php echo $result['score']; ?>/100</strong> | 
        Opportunity: <strong><?php echo e($result['opportunity']); ?></strong>
    </div>

    <div class="lead-detail-grid">
        <div class="card">
            <div class="card-header"><h2>Audit Score</h2></div>
            <div class="card-body text-center">
                <div class="score-display <?php echo $result['score'] <= 30 ? 'score-low' : ($result['score'] <= 50 ? 'score-medium' : ($result['score'] <= 70 ? 'score-high' : 'score-great')); ?>" style="width:100px;height:100px;font-size:32px;margin:0 auto 16px;">
                    <?php echo $result['score']; ?>
                </div>
                <p><strong>Opportunity Level:</strong> <?php echo e($result['opportunity']); ?></p>
                <p><strong>Recommended Package:</strong> <?php echo e($result['package']); ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Problems Found (<?php echo count($result['problems']); ?>)</h2></div>
            <div class="card-body">
                <?php if (!empty($result['problems'])): ?>
                    <ul class="problem-list">
                        <?php foreach ($result['problems'] as $p): ?>
                            <li>&#10060; <?php echo e($p); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-success">No major problems found!</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Recommendations</h2></div>
            <div class="card-body">
                <?php if (!empty($result['recommendations'])): ?>
                    <ul class="recommendation-list">
                        <?php foreach ($result['recommendations'] as $r): ?>
                            <li>&#10004; <?php echo e($r); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-success">Business has strong online presence.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Outreach Message -->
    <?php if ($lead['outreach_message']): ?>
    <div class="card">
        <div class="card-header"><h2>Generated Outreach Message</h2></div>
        <div class="card-body">
            <div class="outreach-box" id="outreachMsg">
                <button class="copy-btn" data-target="outreachMsg">Copy</button>
                <?php echo nl2br(e($lead['outreach_message'])); ?>
            </div>
            <?php if ($lead['phone']): ?>
            <div class="mt-2">
                <a href="<?php echo getWhatsAppLink($lead['phone'], $lead['outreach_message']); ?>" target="_blank" class="btn btn-success">Send via WhatsApp</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
