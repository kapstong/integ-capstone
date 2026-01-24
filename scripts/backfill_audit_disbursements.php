<?php
// Backfill script: scripts/backfill_audit_disbursements.php
// Usage: php scripts/backfill_audit_disbursements.php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

// Simple CLI options: --dry-run, --ip=<ip>, --agent=<user-agent>, --help
$opts = getopt('', ['dry-run', 'ip:', 'agent:', 'help']);
$dryRun = isset($opts['dry-run']);
$ipAddr = isset($opts['ip']) ? $opts['ip'] : '0.0.0.0';
$userAgent = isset($opts['agent']) ? $opts['agent'] : 'backfill-script';

if (isset($opts['help'])) {
    echo "Usage: php scripts/backfill_audit_disbursements.php [--dry-run] [--ip=IP] [--agent=AGENT]\n";
    echo "  --dry-run    : Do not insert, only print what would be inserted\n";
    echo "  --ip=IP      : IP address to record in audit_log (default: 0.0.0.0)\n";
    echo "  --agent=AGENT: User agent to record (default: backfill-script)\n";
    exit(0);
}

try {
    $db = Database::getInstance()->getConnection();

    // Find disbursements missing an audit_log entry linking them
    $disbStmt = $db->query("SELECT id, disbursement_number, disbursement_date, payee, amount, payment_method, reference_number, created_at, recorded_by FROM disbursements ORDER BY id");
    $disbursements = $disbStmt->fetchAll(PDO::FETCH_ASSOC);

    $checkStmt = $db->prepare(
        "SELECT COUNT(*) as cnt FROM audit_log WHERE table_name = 'disbursements' AND (record_id = ? OR new_values LIKE ? OR old_values LIKE ?)");

    $insertStmt = $db->prepare(
        "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
         VALUES (?, 'created', 'disbursements', ?, ?, ?, ?, ?, ?)");

    $inserted = 0;
    $skipped = 0;

    foreach ($disbursements as $d) {
        $id = $d['id'];
        $num = $d['disbursement_number'];
        $like = '%' . $num . '%';

        $checkStmt->execute([$id, $like, $like]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $cnt = intval($row['cnt'] ?? 0);

        if ($cnt > 0) {
            $skipped++;
            continue;
        }

        // Build new_values payload
        $newValues = [
            'disbursement_number' => $num,
            'disbursement_date' => $d['disbursement_date'],
            'payee' => $d['payee'],
            'amount' => $d['amount'],
            'payment_method' => $d['payment_method'],
            'reference_number' => $d['reference_number'] ?? null
        ];

        $oldValues = null;

        // Use recorded_by user if available, otherwise NULL
        $userId = !empty($d['recorded_by']) ? $d['recorded_by'] : null;

        // Use disbursement created_at as audit created_at when possible
        $createdAt = $d['created_at'] ?? date('Y-m-d H:i:s');

        try {
            if ($dryRun) {
                echo "[DRY-RUN] Would insert audit for disbursement id={$id} ({$num}) with payload:\n";
                echo "  user_id: " . var_export($userId, true) . "\n";
                echo "  new_values: " . json_encode($newValues) . "\n";
                echo "  ip_address: {$ipAddr}\n";
                echo "  user_agent: {$userAgent}\n";
                echo "  created_at: {$createdAt}\n\n";
                $skipped++; // treat as skipped for reporting
            } else {
                $db->beginTransaction();
                $insertStmt->execute([
                    $userId,
                    $id,
                    $oldValues,
                    json_encode($newValues),
                    $ipAddr,
                    $userAgent,
                    $createdAt
                ]);
                $db->commit();
                $inserted++;
                echo "Inserted audit for disbursement id={$id} ({$num})\n";
            }
        } catch (Exception $e) {
            if (!$dryRun) $db->rollBack();
            echo "Failed to insert audit for id={$id}: " . $e->getMessage() . "\n";
        }
    }

    echo "Backfill complete. Inserted: {$inserted}. Skipped (existing): {$skipped}.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
