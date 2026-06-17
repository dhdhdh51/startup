<?php
/**
 * Bharat SEO CRM - Bulk Audit
 * 
 * Select and audit multiple leads at once with safe delay between requests.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = 'Bulk Audit';
$message = '';
$messageType = '';
$results = [];
$processing = false;

// Get filter values for the form
$cities = [];
$stmt = db()->query("SELECT DISTINCT city FROM leads WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle POST - run bulk audit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'bulk_audit') {
            $processing = true;
            $selectedLeads = $_POST['lead_ids'] ?? [];
            $filterType = $_POST['filter_type'] ?? 'selected';

            $leadsToAudit = [];

            if ($filterType === 'all_unaudited') {
                $stmt = db()->query("SELECT id, business_name FROM leads WHERE last_audited_at IS NULL ORDER BY created_at ASC LIMIT 50");
                $leadsToAudit = $stmt->fetchAll();
            } elseif ($filterType === 'by_city' && !empty($_POST['filter_city'])) {
                $city = $_POST['filter_city'];
                $stmt = db()->prepare("SELECT id, business_name FROM leads WHERE city = :city AND last_audited_at IS NULL ORDER BY created_at ASC LIMIT 50");
                $stmt->execute([':city' => $city]);
                $leadsToAudit = $stmt->fetchAll();
            } elseif ($filterType === 'by_status' && !empty($_POST['filter_status'])) {
                $status = $_POST['filter_status'];
                $stmt = db()->prepare("SELECT id, business_name FROM leads WHERE status = :status AND last_audited_at IS NULL ORDER BY created_at ASC LIMIT 50");
                $stmt->execute([':status' => $status]);
                $leadsToAudit = $stmt->fetchAll();
            } elseif ($filterType === 'selected' && !empty($selectedLeads)) {
                $placeholders = implode(',', array_fill(0, count($selectedLeads), '?'));
                $stmt = db()->prepare("SELECT id, business_name FROM leads WHERE id IN ($placeholders)");
                $stmt->execute(array_map('intval', $selectedLeads));
                $leadsToAudit = $stmt->fetchAll();
            }

            if (empty($leadsToAudit)) {
                $message = 'No leads found to audit with the selected criteria.';
                $messageType = 'warning';
                $processing = false;
            } else {
                $auditedCount = 0;
                $errorCount = 0;
                $delay = max(2, (int)(getSetting('outreach_delay_seconds', 3) ?: 3));

                foreach ($leadsToAudit as $index => $auditLead) {
                    try {
                        $auditResult = performFullAudit((int)$auditLead['id']);
                        if ($auditResult['success']) {
                            $results[] = [
                                'lead_id' => $auditLead['id'],
                                'business_name' => $auditLead['business_name'],
                                'score' => $auditResult['score'],
                                'status' => 'success',
                                'message' => 'Score: ' . $auditResult['score'] . '/100',
                            ];
                            $auditedCount++;
                        } else {
                            $results[] = [
                                'lead_id' => $auditLead['id'],
                                'business_name' => $auditLead['business_name'],
                                'score' => 0,
                                'status' => 'error',
                                'message' => $auditResult['error'] ?? 'Unknown error',
                            ];
                            $errorCount++;
                        }
                    } catch (\Throwable $e) {
                        $results[] = [
                            'lead_id' => $auditLead['id'],
                            'business_name' => $auditLead['business_name'],
                            'score' => 0,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                        $errorCount++;
                    }

                    // Delay between audits (except after last one)
                    if ($index < count($leadsToAudit) - 1) {
                        sleep($delay);
                    }
                }

                $message = "Bulk audit complete: $auditedCount audited, $errorCount errors out of " . count($leadsToAudit) . " leads.";
                $messageType = $errorCount > 0 ? 'warning' : 'success';
                logActivity('bulk_audit', $message);
            }
        }
    }
}

// Fetch leads for selection
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalStmt = db()->query("SELECT COUNT(*) FROM leads");
$totalLeads = (int)$totalStmt->fetchColumn();

$leadsStmt = db()->prepare("SELECT id, business_name, city, website_url, audit_score, last_audited_at, status FROM leads ORDER BY last_audited_at ASC, created_at DESC LIMIT :limit OFFSET :offset");
$leadsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$leadsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$leadsStmt->execute();
$leads = $leadsStmt->fetchAll();

$pagination = paginate($totalLeads, $page, $perPage, '?');

include __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo sanitize($message); ?>
</div>
<?php endif; ?>

<!-- Bulk Audit Results -->
<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-header">
        <h3>Audit Results</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td>
                        <a href="lead-audit.php?id=<?php echo (int)$r['lead_id']; ?>">
                            <?php echo sanitize($r['business_name']); ?>
                        </a>
                    </td>
                    <td><?php echo (int)$r['score']; ?>/100</td>
                    <td>
                        <?php if ($r['status'] === 'success'): ?>
                            <span class="text-success">Success</span>
                        <?php else: ?>
                            <span class="text-danger">Error</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo sanitize($r['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Filter-based Bulk Audit -->
<div class="card">
    <div class="card-header">
        <h3>Quick Bulk Audit</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="bulk_audit">

            <div class="form-grid">
                <div class="form-group">
                    <label for="filter_type">Audit By</label>
                    <select name="filter_type" id="filter_type" class="form-control">
                        <option value="all_unaudited">All Unaudited Leads (max 50)</option>
                        <option value="by_city">By City (unaudited)</option>
                        <option value="by_status">By Status (unaudited)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_city">City</label>
                    <select name="filter_city" id="filter_city" class="form-control">
                        <option value="">-- Select City --</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo sanitize($city); ?>"><?php echo sanitize($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select name="filter_status" id="filter_status" class="form-control">
                        <option value="">-- Select Status --</option>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="interested">Interested</option>
                        <option value="follow_up">Follow Up</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" onclick="return confirm('This may take several minutes. Continue?');">Run Bulk Audit</button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Selection -->
<div class="card">
    <div class="card-header">
        <h3>Select Leads to Audit (<?php echo $totalLeads; ?> total)</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="bulk_audit">
            <input type="hidden" name="filter_type" value="selected">

            <table class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Business Name</th>
                        <th>City</th>
                        <th>Website</th>
                        <th>Current Score</th>
                        <th>Last Audited</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr><td colspan="6" class="text-muted">No leads found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($leads as $l): ?>
                    <tr>
                        <td><input type="checkbox" name="lead_ids[]" value="<?php echo (int)$l['id']; ?>"></td>
                        <td><a href="lead-view.php?id=<?php echo (int)$l['id']; ?>"><?php echo sanitize($l['business_name']); ?></a></td>
                        <td><?php echo sanitize($l['city'] ?? '-'); ?></td>
                        <td><?php echo !empty($l['website_url']) ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td>
                        <td><?php echo $l['audit_score'] !== null ? (int)$l['audit_score'] . '/100' : '-'; ?></td>
                        <td><?php echo $l['last_audited_at'] ? formatDate($l['last_audited_at']) : 'Never'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php echo $pagination['html']; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Audit selected leads? This may take time.');">Audit Selected</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('input[name="lead_ids[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = this.checked;
    }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
