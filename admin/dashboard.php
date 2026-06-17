<?php
/**
 * Bharat SEO CRM - Dashboard
 * 
 * Overview with statistics cards, recent leads, and quick search.
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$pageTitle = 'Dashboard';

// Get statistics
$stats = [];

// Total leads
$stmt = db()->query("SELECT COUNT(*) FROM leads");
$stats['total_leads'] = (int) $stmt->fetchColumn();

// New leads
$stmt = db()->query("SELECT COUNT(*) FROM leads WHERE status = 'new'");
$stats['new_leads'] = (int) $stmt->fetchColumn();

// Audited leads
$stmt = db()->query("SELECT COUNT(*) FROM leads WHERE last_audited_at IS NOT NULL");
$stats['audited_leads'] = (int) $stmt->fetchColumn();

// High opportunity leads
$stmt = db()->query("SELECT COUNT(*) FROM leads WHERE opportunity_level IN ('high', 'very_high')");
$stats['high_opportunity'] = (int) $stmt->fetchColumn();

// Contacted leads
$stmt = db()->query("SELECT COUNT(*) FROM leads WHERE status = 'contacted'");
$stats['contacted_leads'] = (int) $stmt->fetchColumn();

// Converted leads
$stmt = db()->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'");
$stats['converted_leads'] = (int) $stmt->fetchColumn();

// Average audit score
$stmt = db()->query("SELECT AVG(audit_score) FROM leads WHERE audit_score IS NOT NULL");
$avgScore = $stmt->fetchColumn();
$stats['avg_audit_score'] = $avgScore !== null ? (int) round((float) $avgScore) : 0;

// Recent leads (last 10)
$stmt = db()->query("SELECT id, business_name, city, status, opportunity_level, audit_score, created_at FROM leads ORDER BY created_at DESC LIMIT 10");
$recentLeads = $stmt->fetchAll();

// Handle quick search
$searchQuery = trim($_GET['q'] ?? '');
$searchResults = [];
if (!empty($searchQuery)) {
    $stmt = db()->prepare("SELECT id, business_name, city, phone, status, opportunity_level FROM leads WHERE business_name LIKE :q OR city LIKE :q2 OR phone LIKE :q3 OR email LIKE :q4 LIMIT 20");
    $searchTerm = '%' . $searchQuery . '%';
    $stmt->execute([':q' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm, ':q4' => $searchTerm]);
    $searchResults = $stmt->fetchAll();
}

include __DIR__ . '/includes/admin-header.php';
?>

<!-- Quick Search -->
<div class="search-box">
    <form method="GET" action="">
        <div class="form-inline">
            <input type="text" name="q" placeholder="Search leads by name, city, phone, email..." 
                   value="<?php echo sanitize($searchQuery); ?>" class="form-control search-input">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>
</div>

<?php if (!empty($searchQuery) && !empty($searchResults)): ?>
<div class="card">
    <div class="card-header">
        <h3>Search Results for "<?php echo sanitize($searchQuery); ?>"</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Business Name</th>
                    <th>City</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Opportunity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($searchResults as $lead): ?>
                <tr>
                    <td><?php echo sanitize($lead['business_name']); ?></td>
                    <td><?php echo sanitize($lead['city']); ?></td>
                    <td><?php echo sanitize($lead['phone']); ?></td>
                    <td><?php echo getLeadStatusBadge($lead['status']); ?></td>
                    <td><?php echo getOpportunityBadge($lead['opportunity_level']); ?></td>
                    <td><a href="lead-view.php?id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-info">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif (!empty($searchQuery)): ?>
<div class="alert alert-info">No results found for "<?php echo sanitize($searchQuery); ?>"</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($stats['total_leads']); ?></div>
        <div class="stat-label">Total Leads</div>
    </div>
    <div class="stat-card stat-card-info">
        <div class="stat-value"><?php echo number_format($stats['new_leads']); ?></div>
        <div class="stat-label">New Leads</div>
    </div>
    <div class="stat-card stat-card-warning">
        <div class="stat-value"><?php echo number_format($stats['audited_leads']); ?></div>
        <div class="stat-label">Audited Leads</div>
    </div>
    <div class="stat-card stat-card-success">
        <div class="stat-value"><?php echo number_format($stats['high_opportunity']); ?></div>
        <div class="stat-label">High Opportunity</div>
    </div>
    <div class="stat-card stat-card-primary">
        <div class="stat-value"><?php echo number_format($stats['contacted_leads']); ?></div>
        <div class="stat-label">Contacted</div>
    </div>
    <div class="stat-card stat-card-dark">
        <div class="stat-value"><?php echo number_format($stats['converted_leads']); ?></div>
        <div class="stat-label">Converted</div>
    </div>
    <div class="stat-card stat-card-secondary">
        <div class="stat-value"><?php echo $stats['avg_audit_score']; ?>%</div>
        <div class="stat-label">Avg Audit Score</div>
    </div>
</div>

<!-- Recent Leads -->
<div class="card">
    <div class="card-header">
        <h3>Recent Leads</h3>
        <a href="leads.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLeads)): ?>
            <p class="text-muted">No leads yet. Start by searching for leads.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Business Name</th>
                    <th>City</th>
                    <th>Status</th>
                    <th>Opportunity</th>
                    <th>Audit Score</th>
                    <th>Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLeads as $lead): ?>
                <tr>
                    <td><?php echo sanitize($lead['business_name']); ?></td>
                    <td><?php echo sanitize($lead['city']); ?></td>
                    <td><?php echo getLeadStatusBadge($lead['status']); ?></td>
                    <td><?php echo getOpportunityBadge($lead['opportunity_level']); ?></td>
                    <td><?php echo $lead['audit_score'] !== null ? (int)$lead['audit_score'] . '%' : '-'; ?></td>
                    <td><?php echo formatDate($lead['created_at']); ?></td>
                    <td><a href="lead-view.php?id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-info">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
