<?php
/**
 * Bharat SEO CRM - Cron: Audit Pending Leads
 * 
 * CLI-runnable script that processes up to 10 pending leads
 * (status='new', never audited) with delay between each audit.
 * 
 * Usage: php cron/audit-pending-leads.php
 * 
 * Recommended cron schedule: every 30 minutes
 * Example: 0,30 * * * * php /path/to/cron/audit-pending-leads.php
 */

// Ensure running from CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Configuration
$batchSize = 10;
$delayBetweenAudits = 3; // seconds

echo "=== Bharat SEO CRM - Audit Pending Leads ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Batch size: $batchSize\n\n";

try {
    // Query pending leads: status='new' and never audited
    $stmt = db()->prepare(
        "SELECT id, business_name, website_url, city 
         FROM leads 
         WHERE status = 'new' AND last_audited_at IS NULL 
         ORDER BY created_at ASC 
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $leads = $stmt->fetchAll();

    if (empty($leads)) {
        echo "No pending leads to audit.\n";
        logActivity('cron_audit', 'No pending leads to audit.');
        exit(0);
    }

    echo "Found " . count($leads) . " leads to audit.\n\n";

    $auditedCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($leads as $index => $lead) {
        $leadId = (int)$lead['id'];
        $businessName = $lead['business_name'];

        echo "[" . ($index + 1) . "/" . count($leads) . "] Auditing: $businessName (ID: $leadId)";

        try {
            $result = performFullAudit($leadId);

            if ($result['success']) {
                echo " - Score: " . $result['score'] . "/100 - " . ($result['opportunity'] ?? '') . "\n";
                $auditedCount++;
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                echo " - FAILED: $errorMsg\n";
                $errorCount++;
                $errors[] = "Lead #$leadId ($businessName): $errorMsg";
            }
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            echo " - ERROR: $errorMsg\n";
            $errorCount++;
            $errors[] = "Lead #$leadId ($businessName): $errorMsg";
        }

        // Delay between audits (except after last one)
        if ($index < count($leads) - 1) {
            sleep($delayBetweenAudits);
        }
    }

    echo "\n=== Summary ===\n";
    echo "Total processed: " . count($leads) . "\n";
    echo "Successfully audited: $auditedCount\n";
    echo "Errors: $errorCount\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

    if (!empty($errors)) {
        echo "\nError details:\n";
        foreach ($errors as $err) {
            echo "  - $err\n";
        }
    }

    // Log activity
    $logDetails = "Cron audit batch: $auditedCount audited, $errorCount errors out of " . count($leads) . " leads.";
    logActivity('cron_audit', $logDetails);

} catch (\Throwable $e) {
    $errorMsg = "Fatal error in cron audit: " . $e->getMessage();
    echo "ERROR: $errorMsg\n";

    try {
        logActivity('cron_audit_error', $errorMsg);
    } catch (\Throwable $logError) {
        // Can't log to DB, just output
        echo "Could not log error to database: " . $logError->getMessage() . "\n";
    }

    exit(1);
}

exit(0);
