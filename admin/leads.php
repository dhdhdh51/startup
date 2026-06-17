<?php
/**
 * Bharat SEO CRM - Leads List
 * 
 * CRM list with filters, sorting, pagination, and bulk actions.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = 'Leads';
$message = '';
$messageType = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['bulk_action'] ?? '';
        $selectedIds = $_POST['selected_leads'] ?? [];

        if (!empty($action) && !empty($selectedIds) && is_array($selectedIds)) {
            $ids = array_map('intval', $selectedIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            switch ($action) {
                case 'mark_contacted':
                    $stmt = db()->prepare("UPDATE leads SET status = 'contacted', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . ' leads marked as contacted.';
                    $messageType = 'success';
                    logActivity('bulk_status_update', 'Marked ' . count($ids) . ' leads as contacted');
                    break;

                case 'mark_new':
                    $stmt = db()->prepare("UPDATE leads SET status = 'new', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . ' leads marked as new.';
                    $messageType = 'success';
                    break;

                case 'mark_not_interested':
                    $stmt = db()->prepare("UPDATE leads SET status = 'not_interested', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . ' leads marked as not interested.';
                    $messageType = 'success';
                    break;

                case 'delete':
                    $stmt = db()->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . ' leads deleted.';
                    $messageType = 'success';
                    logActivity('bulk_delete', 'Deleted ' . count($ids) . ' leads');
                    break;
            }
        } elseif (!empty($action) && empty($selectedIds)) {
            $message = 'No leads selected for bulk action.';
            $messageType = 'warning';
        }
    }
}

// Get filter parameters
$filters = [
    'city' => trim($_GET['city'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'opportunity' => $_GET['opportunity'] ?? '',
    'has_website' => $_GET['has_website'] ?? '',
    'has_whatsapp' => $_GET['has_whatsapp'] ?? '',
    'high_opportunity' => isset($_GET['high_opportunity']) ? '1' : '',
    'min_rating' => $_GET['min_rating'] ?? '',
    'max_rating' => $_GET['max_rating'] ?? '',
    'min_reviews' => $_GET['min_reviews'] ?? '',
    'max_reviews' => $_GET['max_reviews'] ?? '',
    'min_audit_score' => $_GET['min_audit_score'] ?? '',
    'max_audit_score' => $_GET['max_audit_score'] ?? '',
];

// Build query
$where = [];
$params = [];

if (!empty($filters['city'])) {
    $where[] = "city LIKE :city";
    $params[':city'] = '%' . $filters['city'] . '%';
}
if (!empty($filters['category'])) {
    $where[] = "category LIKE :category";
    $params[':category'] = '%' . $filters['category'] . '%';
}
if (!empty($filters['status'])) {
    $where[] = "status = :status";
    $params[':status'] = $filters['status'];
}
if (!empty($filters['opportunity'])) {
    $where[] = "opportunity_level = :opportunity";
    $params[':opportunity'] = $filters['opportunity'];
}
if ($filters['has_website'] === 'yes') {
    $where[] = "website_url IS NOT NULL AND website_url != ''";
} elseif ($filters['has_website'] === 'no') {
    $where[] = "(website_url IS NULL OR website_url = '')";
}
if ($filters['has_whatsapp'] === 'yes') {
    $where[] = "phone IS NOT NULL AND phone != ''";
} elseif ($filters['has_whatsapp'] === 'no') {
    $where[] = "(phone IS NULL OR phone = '')";
}
if ($filters['high_opportunity'] === '1') {
    $where[] = "opportunity_level IN ('high', 'very_high')";
}
if ($filters['min_rating'] !== '') {
    $where[] = "rating >= :min_rating";
    $params[':min_rating'] = (float)$filters['min_rating'];
}
if ($filters['max_rating'] !== '') {
    $where[] = "rating <= :max_rating";
    $params[':max_rating'] = (float)$filters['max_rating'];
}
if ($filters['min_reviews'] !== '') {
    $where[] = "review_count >= :min_reviews";
    $params[':min_reviews'] = (int)$filters['min_reviews'];
}
if ($filters['max_reviews'] !== '') {
    $where[] = "review_count <= :max_reviews";
    $params[':max_reviews'] = (int)$filters['max_reviews'];
}
if ($filters['min_audit_score'] !== '') {
    $where[] = "audit_score >= :min_audit_score";
    $params[':min_audit_score'] = (int)$filters['min_audit_score'];
}
if ($filters['max_audit_score'] !== '') {
    $where[] = "audit_score <= :max_audit_score";
    $params[':max_audit_score'] = (int)$filters['max_audit_score'];
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sorting
$allowedSorts = ['business_name', 'city', 'category', 'rating', 'review_count', 'audit_score', 'opportunity_level', 'status', 'last_audited_at', 'created_at'];
$sort = in_array($_GET['sort'] ?? '', $allowedSorts) ? $_GET['sort'] : 'created_at';
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

// Count total
$countStmt = db()->prepare("SELECT COUNT(*) FROM leads $whereClause");
$countStmt->execute($params);
$totalLeads = (int)$countStmt->fetchColumn();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;

// Build base URL for pagination
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = '?' . http_build_query($queryParams) . '&';
$pagination = paginate($totalLeads, $page, $perPage, $baseUrl);

// Fetch leads
$sql = "SELECT id, business_name, city, category, phone, website_url, rating, review_count, audit_score, opportunity_level, status, last_audited_at, created_at 
        FROM leads $whereClause 
        ORDER BY $sort $order 
        LIMIT :limit OFFSET :offset";

$stmt = db()->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

// Helper for sort links
function sortLink(string $column, string $label): string
{
    global $sort, $order, $filters;
    $params = array_filter($filters, fn($v) => $v !== '');
    $params['sort'] = $column;
    $params['order'] = ($sort === $column && $order === 'ASC') ? 'desc' : 'asc';
    $arrow = '';
    if ($sort === $column) {
        $arrow = $order === 'ASC' ? ' &#9650;' : ' &#9660;';
    }
    return '<a href="?' . http_build_query($params) . '">' . $label . $arrow . '</a>';
}

include __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo sanitize($message); ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3>Filters</h3>
        <a href="leads.php" class="btn btn-sm btn-outline">Clear Filters</a>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="filter-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="filter_city">City</label>
                    <input type="text" id="filter_city" name="city" class="form-control" 
                           value="<?php echo sanitize($filters['city']); ?>" placeholder="Filter by city">
                </div>
                <div class="form-group">
                    <label for="filter_category">Business Type</label>
                    <input type="text" id="filter_category" name="category" class="form-control" 
                           value="<?php echo sanitize($filters['category']); ?>" placeholder="Filter by category">
                </div>
                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select id="filter_status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="new" <?php echo $filters['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="contacted" <?php echo $filters['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                        <option value="interested" <?php echo $filters['status'] === 'interested' ? 'selected' : ''; ?>>Interested</option>
                        <option value="proposal_sent" <?php echo $filters['status'] === 'proposal_sent' ? 'selected' : ''; ?>>Proposal Sent</option>
                        <option value="converted" <?php echo $filters['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                        <option value="not_interested" <?php echo $filters['status'] === 'not_interested' ? 'selected' : ''; ?>>Not Interested</option>
                        <option value="follow_up" <?php echo $filters['status'] === 'follow_up' ? 'selected' : ''; ?>>Follow Up</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_has_website">Has Website</label>
                    <select id="filter_has_website" name="has_website" class="form-control">
                        <option value="">Any</option>
                        <option value="yes" <?php echo $filters['has_website'] === 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo $filters['has_website'] === 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_has_whatsapp">Has WhatsApp/Phone</label>
                    <select id="filter_has_whatsapp" name="has_whatsapp" class="form-control">
                        <option value="">Any</option>
                        <option value="yes" <?php echo $filters['has_whatsapp'] === 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo $filters['has_whatsapp'] === 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_min_audit_score">Audit Score (Min)</label>
                    <input type="number" id="filter_min_audit_score" name="min_audit_score" class="form-control" 
                           value="<?php echo sanitize($filters['min_audit_score']); ?>" min="0" max="100" placeholder="0">
                </div>
                <div class="form-group">
                    <label for="filter_max_audit_score">Audit Score (Max)</label>
                    <input type="number" id="filter_max_audit_score" name="max_audit_score" class="form-control" 
                           value="<?php echo sanitize($filters['max_audit_score']); ?>" min="0" max="100" placeholder="100">
                </div>
                <div class="form-group">
                    <label for="filter_min_rating">Rating (Min)</label>
                    <input type="number" id="filter_min_rating" name="min_rating" class="form-control" 
                           value="<?php echo sanitize($filters['min_rating']); ?>" min="0" max="5" step="0.1" placeholder="0">
                </div>
                <div class="form-group">
                    <label for="filter_max_rating">Rating (Max)</label>
                    <input type="number" id="filter_max_rating" name="max_rating" class="form-control" 
                           value="<?php echo sanitize($filters['max_rating']); ?>" min="0" max="5" step="0.1" placeholder="5">
                </div>
                <div class="form-group">
                    <label for="filter_min_reviews">Reviews (Min)</label>
                    <input type="number" id="filter_min_reviews" name="min_reviews" class="form-control" 
                           value="<?php echo sanitize($filters['min_reviews']); ?>" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label for="filter_max_reviews">Reviews (Max)</label>
                    <input type="number" id="filter_max_reviews" name="max_reviews" class="form-control" 
                           value="<?php echo sanitize($filters['max_reviews']); ?>" min="0" placeholder="Any">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="high_opportunity" value="1" <?php echo $filters['high_opportunity'] === '1' ? 'checked' : ''; ?>>
                        High Opportunity Only
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="leads.php" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Leads Table -->
<div class="card">
    <div class="card-header">
        <h3>Leads (<?php echo number_format($totalLeads); ?> total)</h3>
        <div>
            <a href="lead-search.php" class="btn btn-sm btn-primary">+ Search New</a>
            <a href="import-csv.php" class="btn btn-sm btn-outline">Import CSV</a>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="bulkForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="table-actions">
                <label class="checkbox-label">
                    <input type="checkbox" id="selectAll"> Select All
                </label>
                <select name="bulk_action" class="form-control form-control-sm" style="width:auto;display:inline-block;">
                    <option value="">Bulk Actions</option>
                    <option value="mark_contacted">Mark Contacted</option>
                    <option value="mark_new">Mark New</option>
                    <option value="mark_not_interested">Mark Not Interested</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Apply bulk action to selected leads?');">Apply</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="select-all-check"></th>
                            <th><?php echo sortLink('business_name', 'Business Name'); ?></th>
                            <th><?php echo sortLink('city', 'City'); ?></th>
                            <th><?php echo sortLink('category', 'Category'); ?></th>
                            <th>Phone</th>
                            <th>Website</th>
                            <th><?php echo sortLink('rating', 'Rating'); ?></th>
                            <th><?php echo sortLink('review_count', 'Reviews'); ?></th>
                            <th><?php echo sortLink('audit_score', 'Audit Score'); ?></th>
                            <th><?php echo sortLink('opportunity_level', 'Opportunity'); ?></th>
                            <th><?php echo sortLink('status', 'Status'); ?></th>
                            <th><?php echo sortLink('last_audited_at', 'Last Audited'); ?></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="13" class="text-center text-muted">No leads found. Try adjusting your filters or <a href="lead-search.php">search for new leads</a>.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_leads[]" value="<?php echo (int)$lead['id']; ?>" class="lead-checkbox"></td>
                            <td><a href="lead-view.php?id=<?php echo (int)$lead['id']; ?>"><?php echo sanitize($lead['business_name']); ?></a></td>
                            <td><?php echo sanitize($lead['city']); ?></td>
                            <td><?php echo sanitize($lead['category']); ?></td>
                            <td><?php echo sanitize($lead['phone']); ?></td>
                            <td>
                                <?php if (!empty($lead['website_url'])): ?>
                                    <a href="<?php echo sanitize($lead['website_url']); ?>" target="_blank" rel="noopener" title="<?php echo sanitize($lead['website_url']); ?>">Visit</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $lead['rating'] !== null ? number_format((float)$lead['rating'], 1) : '-'; ?></td>
                            <td><?php echo (int)$lead['review_count']; ?></td>
                            <td><?php echo $lead['audit_score'] !== null ? (int)$lead['audit_score'] . '%' : '-'; ?></td>
                            <td><?php echo getOpportunityBadge($lead['opportunity_level']); ?></td>
                            <td><?php echo getLeadStatusBadge($lead['status']); ?></td>
                            <td><?php echo $lead['last_audited_at'] ? formatDate($lead['last_audited_at'], 'd M Y') : '-'; ?></td>
                            <td class="actions-cell">
                                <a href="lead-view.php?id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-info" title="View">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if (!empty($pagination['html'])): ?>
        <div class="pagination-wrapper">
            <?php echo $pagination['html']; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var selectAll = document.getElementById('selectAll');
    var selectAllCheck = document.querySelector('.select-all-check');
    var checkboxes = document.querySelectorAll('.lead-checkbox');

    function toggleAll(checked) {
        checkboxes.forEach(function(cb) { cb.checked = checked; });
        if (selectAll) selectAll.checked = checked;
        if (selectAllCheck) selectAllCheck.checked = checked;
    }

    if (selectAll) selectAll.addEventListener('change', function() { toggleAll(this.checked); });
    if (selectAllCheck) selectAllCheck.addEventListener('change', function() { toggleAll(this.checked); });
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
