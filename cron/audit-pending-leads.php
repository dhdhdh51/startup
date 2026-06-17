<?php
/**
 * Bharat SEO CRM - Cron: Audit Pending Leads
 * 
 * Run this via cron job:
 * */10 * * * * php /path/to/cron/audit-pending-leads.php
 * 
 * Processes 10 leads per run with delay between each audit.
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../functions.php';

$batchSize = AUDIT_BATCH_SIZE;
$delay = (int)getSetting('delay_between_audits', AUDIT_DELAY_SECONDS);

echo "[" . date('Y-m-d H:i:s') . "] Starting audit batch...\n";

// Get pending leads (New status or never audited)
$db = getDB();
$stmt = $db->prepare("SELECT id, business_name FROM leads WHERE (status = 'New' OR last_audited_at IS NULL) AND website_url IS NOT NULL AND website_url != '' ORDER BY created_at ASC LIMIT ?");
$stmt->execute([$batchSize]);
$leads = $stmt->fetchAll();

if (empty($leads)) {
    echo "No pending leads to audit.\n";
    exit(0);
}

echo "Found " . count($leads) . " leads to audit.\n";

$audited = 0;
$errors = 0;

foreach ($leads as $lead) {
    echo "  Auditing: {$lead['business_name']} (ID: {$lead['id']})... ";
    
    try {
        $result = auditLead($lead['id']);
        
        if (isset($result['error'])) {
            echo "ERROR: {$result['error']}\n";
            $errors++;
        } else {
            echo "OK (Score: {$result['score']})\n";
            $audited++;
        }
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        $errors++;
        error_log("Cron audit error for lead {$lead['id']}: " . $e->getMessage());
    }
    
    // Delay between audits
    if ($delay > 0) {
        sleep($delay);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Batch complete. Audited: {$audited} | Errors: {$errors}\n";

logActivity('cron_audit', "Cron audit batch: Audited {$audited}, Errors: {$errors}");
