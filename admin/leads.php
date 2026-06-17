<?php
$pageTitle = 'All Leads';
require_once 'includes/admin-header.php';

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $action = $_POST['action'] ?? '';
        $leadId = (int)($_POST['lead_id'] ?? 0);
        
        if ($action === 'update_status' && $leadId) {
            $status = $_POST['status'] ?? '';
            $validStatuses = ['New', 'Audited', 'Contacted', 'Interested', 'Follow-up', 'Converted', 'Not interested'];
            if (in_array($status, $validStatuses)) {
                updateLeadStatus($leadId, $status);
                $message = 'Lead status updated successfully.';
                $messageType = 'success';
                logActivity('status_update', "Lead #{$leadId} status changed to: {$status}");
            }
        }
    }
}

// Handle GET delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (isset($_GET['csrf']) && verifyCSRFToken($_GET['csrf'])) {
        $leadId = (int)$_GET['id'];
        $lead = getLeadById($leadId);
        if ($lead) {
            deleteLead($leadId);
            $message = 'Lead deleted successfully.';
            $messageType = 'success';
            logActivity('delete_lead', "Deleted lead: {$lead['business_name']}");
        }
    }
}

// Get filters
$filters = [
    'city' => $_GET['city'] ?? '',
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'has_website' => $_GET['has_website'] ?? '',
    'high_opportunity' => $_GET['high_opportunity'] ?? '',
    'min_score' => $_GET['min_score'] ?? '',
    'max_score' => $_GET['max_score'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? '',
    'max_rating' => $_GET['max_rating'] ?? '',
    'min_reviews' => $_GET['min_reviews'] ?? '',
    'max_reviews' => $_GET['max_reviews'] ?? '',
    'search' => $_GET['search'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = getLeads($filters, $page, 25);
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="">
<div class="filter-bar">
    <div class="form-group">
        <label>Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, phone, city..." value="<?php echo e($filters['search']); ?>">
    </div>
    <div class="form-group">
        <label>City</label>
        <input type="text" name="city" class="form-control" placeholder="City" value="<?php echo e($filters['city']); ?>">
    </div>
    <div class="form-group">
        <label>Category</label>
        <input type="text" name="category" class="form-control" placeholder="Category" value="<?php echo e($filters['category']); ?>">
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
            <option value="">All</option>
            <option value="New" <?php echo $filters['status'] === 'New' ? 'selected' : ''; ?>>New</option>
            <option value="Audited" <?php echo $filters['status'] === 'Audited' ? 'selected' : ''; ?>>Audited</option>
            <option value="Contacted" <?php echo $filters['status'] === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
            <option value="Interested" <?php echo $filters['status'] === 'Interested' ? 'selected' : ''; ?>>Interested</option>
            <option value="Follow-up" <?php echo $filters['status'] === 'Follow-up' ? 'selected' : ''; ?>>Follow-up</option>
            <option value="Converted" <?php echo $filters['status'] === 'Converted' ? 'selected' : ''; ?>>Converted</option>
            <option value="Not interested" <?php echo $filters['status'] === 'Not interested' ? 'selected' : ''; ?>>Not interested</option>
        </select>
    </div>
    <div class="form-group">
        <label>Website</label>
        <select name="has_website" class="form-control">
            <option value="">Any</option>
            <option value="yes" <?php echo $filters['has_website'] === 'yes' ? 'selected' : ''; ?>>Has website</option>
            <option value="no" <?php echo $filters['has_website'] === 'no' ? 'selected' : ''; ?>>No website</option>
        </select>
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">Filter</button>
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <a href="leads.php" class="btn btn-secondary">Reset</a>
    </div>
</div>
</form>

<!-- Leads Table -->
<div class="card">
    <div class="card-header">
        <h2>Leads (<?php echo number_format($result['total']); ?>)</h2>
        <div class="btn-group">
            <span id="selectedCount" class="text-muted"></span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($result['leads'])): ?>
            <div class="empty-state">
                <h3>No leads found</h3>
                <p>Try different filters or search for new leads.</p>
            </div>
        <?php else: ?>
        <form id="leadsForm" method="POST">
            <?php echo csrfField(); ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                            <th>Business Name</th>
                            <th>City</th>
                            <th>Category</th>
                            <th>Phone</th>
                            <th>Website</th>
                            <th>Rating</th>
                            <th>Score</th>
                            <th>Opportunity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['leads'] as $lead): ?>
                        <tr>
                            <td class="checkbox-col"><input type="checkbox" class="lead-checkbox" value="<?php echo $lead['id']; ?>"></td>
                            <td><strong><a href="lead-view.php?id=<?php echo $lead['id']; ?>"><?php echo e($lead['business_name']); ?></a></strong></td>
                            <td><?php echo e($lead['city']); ?></td>
                            <td><?php echo e($lead['category']); ?></td>
                            <td><?php echo e($lead['phone']); ?></td>
                            <td><?php echo $lead['website_url'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td>
                            <td><?php echo $lead['rating'] ? e($lead['rating']) . ' &#9733;' : '-'; ?></td>
                            <td><span class="badge <?php echo getScoreBadgeClass($lead['audit_score']); ?>"><?php echo $lead['audit_score'] ?? '-'; ?></span></td>
                            <td><?php echo e($lead['opportunity_level'] ?? '-'); ?></td>
                            <td><span class="badge <?php echo getStatusBadgeClass($lead['status']); ?>"><?php echo e($lead['status']); ?></span></td>
                            <td>
                                <a href="lead-view.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <a href="lead-audit.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-primary">Audit</a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $lead['id']; ?>)">Del</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $result['total_pages']; $i++): ?>
                <?php
                $queryParams = array_merge($filters, ['page' => $i]);
                $queryString = http_build_query(array_filter($queryParams));
                ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo $queryString; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
