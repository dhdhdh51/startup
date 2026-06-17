<?php
$pageTitle = 'Dashboard';
require_once 'includes/admin-header.php';
$stats = getDashboardStats();
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Leads</h3>
        <div class="stat-value"><?php echo number_format($stats['total_leads']); ?></div>
    </div>
    <div class="stat-card info">
        <h3>New Leads</h3>
        <div class="stat-value"><?php echo number_format($stats['new_leads']); ?></div>
    </div>
    <div class="stat-card success">
        <h3>Audited</h3>
        <div class="stat-value"><?php echo number_format($stats['audited_leads']); ?></div>
    </div>
    <div class="stat-card danger">
        <h3>High Opportunity</h3>
        <div class="stat-value"><?php echo number_format($stats['high_opportunity']); ?></div>
    </div>
    <div class="stat-card warning">
        <h3>Contacted</h3>
        <div class="stat-value"><?php echo number_format($stats['contacted_leads']); ?></div>
    </div>
    <div class="stat-card success">
        <h3>Converted</h3>
        <div class="stat-value"><?php echo number_format($stats['converted_leads']); ?></div>
    </div>
    <div class="stat-card info">
        <h3>Avg Audit Score</h3>
        <div class="stat-value"><?php echo $stats['avg_score']; ?>/100</div>
    </div>
</div>

<!-- Quick Search -->
<div class="card mb-3">
    <div class="card-body">
        <form action="leads.php" method="GET" class="form-inline">
            <div class="form-group" style="flex:1;">
                <input type="text" name="search" class="form-control" placeholder="Quick search leads by name, phone, city...">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="lead-search.php" class="btn btn-success">+ Find New Leads</a>
        </form>
    </div>
</div>

<!-- Recent Leads -->
<div class="card">
    <div class="card-header">
        <h2>Recent Leads</h2>
        <a href="leads.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($stats['recent_leads'])): ?>
            <div class="empty-state">
                <h3>No leads yet</h3>
                <p>Start by searching for leads or importing a CSV file.</p>
                <a href="lead-search.php" class="btn btn-primary mt-2">Find Leads</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>City</th>
                            <th>Category</th>
                            <th>Phone</th>
                            <th>Rating</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_leads'] as $lead): ?>
                        <tr>
                            <td><strong><?php echo e($lead['business_name']); ?></strong></td>
                            <td><?php echo e($lead['city']); ?></td>
                            <td><?php echo e($lead['category']); ?></td>
                            <td><?php echo e($lead['phone']); ?></td>
                            <td><?php echo $lead['rating'] ? e($lead['rating']) . ' &#9733;' : '-'; ?></td>
                            <td>
                                <?php if ($lead['audit_score'] !== null): ?>
                                    <span class="badge <?php echo getScoreBadgeClass($lead['audit_score']); ?>"><?php echo $lead['audit_score']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo getStatusBadgeClass($lead['status']); ?>"><?php echo e($lead['status']); ?></span></td>
                            <td>
                                <a href="lead-view.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <a href="lead-audit.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-primary">Audit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
