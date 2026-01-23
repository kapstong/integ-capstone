<?php
// Backfill HR3 claims disbursement journal entries to use proper expense and cash accounts.
// Usage (web, superadmin only):
//   /tools/backfill_claims_journal_entries.php
//   /tools/backfill_claims_journal_entries.php?apply=1

require_once __DIR__ . '/../includes/database.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$apply = false;
$dryRun = true;

if ($isCli) {
    $apply = in_array('--apply', $argv, true);
    $dryRun = !$apply;
} else {
    require_once __DIR__ . '/../includes/auth.php';
    $auth = new Auth();
    $roleName = strtolower($_SESSION['user']['role_name'] ?? '');
    $hasSuperRole = $roleName === 'super_admin' || $auth->hasRole('super_admin');
    if (!$hasSuperRole) {
        http_response_code(403);
        echo "Forbidden: superadmin access required.\n";
        exit(1);
    }
    $apply = isset($_GET['apply']) && ($_GET['apply'] === '1' || $_GET['apply'] === 'true');
    $dryRun = !$apply;
    header('Content-Type: text/plain; charset=utf-8');
}

function getAccountIdByCode(PDO $db, $code) {
    $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['id'] ?? null;
}

function getFirstActiveExpenseAccountId(PDO $db) {
    $stmt = $db->query("SELECT id FROM chart_of_accounts WHERE account_type = 'expense' AND is_active = 1 ORDER BY account_code LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['id'] ?? null;
}

function getClaimsExpenseAccountId(PDO $db) {
    $accountId = getAccountIdByCode($db, '5300');
    if ($accountId) {
        return $accountId;
    }
    $candidates = [
        'Employee Claims',
        'Employee Reimbursements',
        'Claims',
        'Reimbursements'
    ];
    foreach ($candidates as $name) {
        $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_name LIKE ? AND is_active = 1 LIMIT 1");
        $stmt->execute(['%' . $name . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['id'])) {
            return $row['id'];
        }
    }
    $accountId = getAccountIdByCode($db, '5403');
    if ($accountId) {
        return $accountId;
    }
    $accountId = getAccountIdByCode($db, '6000');
    if ($accountId) {
        return $accountId;
    }
    return getFirstActiveExpenseAccountId($db);
}

function getNextJournalEntryNumber(PDO $db) {
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(entry_number, '-', -1) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
    $maxNum = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;
    return 'JE-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
}

function createJournalEntryForDisbursement(PDO $db, array $disb, $debitAccountId, $creditAccountId) {
    $entryNumber = getNextJournalEntryNumber($db);
    $entryDate = $disb['disbursement_date'] ?? date('Y-m-d');
    $description = $disb['purpose'] ?? ('Disbursement: Payment to ' . ($disb['payee'] ?? 'vendor') . ' - ' . ($disb['reference_number'] ?? 'N/A'));
    $amount = floatval($disb['amount'] ?? 0);
    if ($amount <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        INSERT INTO journal_entries (
            entry_number, entry_date, description, reference,
            total_debit, total_credit, status, created_by, posted_by, posted_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'posted', 1, 1, NOW())
    ");
    $stmt->execute([
        $entryNumber,
        $entryDate,
        $description,
        'DISB-' . $disb['id'],
        $amount,
        $amount
    ]);

    $entryId = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description)
        VALUES (?, ?, ?, 0, ?)
    ");
    $stmt->execute([$entryId, $debitAccountId, $amount, $description]);

    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description)
        VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$entryId, $creditAccountId, $amount, $description]);

    return $entryId;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$cashAccountId = getAccountIdByCode($db, '1001');
$claimsExpenseAccountId = getClaimsExpenseAccountId($db);

if (!$cashAccountId || !$claimsExpenseAccountId) {
    fwrite(STDERR, "Missing required accounts. Cash(1001) or claims expense not found." . PHP_EOL);
    exit(1);
}

// Find HR3 claims disbursements
$stmt = $db->query("
    SELECT id, disbursement_number, disbursement_date, payee, purpose, reference_number, account_id, amount
    FROM disbursements
    WHERE LOWER(CONCAT(IFNULL(payee, ''), ' ', IFNULL(purpose, ''), ' ', IFNULL(reference_number, ''))) LIKE '%hr3%'
       OR LOWER(CONCAT(IFNULL(payee, ''), ' ', IFNULL(purpose, ''), ' ', IFNULL(reference_number, ''))) LIKE '%claim%'
    ORDER BY id ASC
");

$disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updatedEntries = 0;
$updatedDisbursements = 0;
$skipped = 0;

foreach ($disbursements as $disb) {
    $ref = 'DISB-' . $disb['id'];

    $entryStmt = $db->prepare("SELECT id FROM journal_entries WHERE reference = ? LIMIT 1");
    $entryStmt->execute([$ref]);
    $entryId = $entryStmt->fetchColumn();

    if (!$entryId) {
        if ($dryRun) {
            $updatedEntries++;
        } else {
            $db->beginTransaction();
            try {
                $entryId = createJournalEntryForDisbursement($db, $disb, $claimsExpenseAccountId, $cashAccountId);
                if ($entryId) {
                    $updateDisb = $db->prepare("UPDATE disbursements SET account_id = ? WHERE id = ?");
                    $updateDisb->execute([$claimsExpenseAccountId, $disb['id']]);
                    $updatedEntries++;
                    $updatedDisbursements++;
                } else {
                    $skipped++;
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                fwrite(STDERR, "Failed to create entry for {$ref}: " . $e->getMessage() . PHP_EOL);
            }
        }
        continue;
    }

    $linesStmt = $db->prepare("
        SELECT jel.id, jel.account_id, jel.debit, jel.credit
        FROM journal_entry_lines jel
        WHERE jel.journal_entry_id = ?
    ");
    $linesStmt->execute([$entryId]);
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lines)) {
        if ($dryRun) {
            $updatedEntries++;
        } else {
            $db->beginTransaction();
            try {
                createJournalEntryForDisbursement($db, $disb, $claimsExpenseAccountId, $cashAccountId);
                $updateDisb = $db->prepare("UPDATE disbursements SET account_id = ? WHERE id = ?");
                $updateDisb->execute([$claimsExpenseAccountId, $disb['id']]);
                $updatedEntries++;
                $updatedDisbursements++;
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                fwrite(STDERR, "Failed to backfill lines for {$ref}: " . $e->getMessage() . PHP_EOL);
            }
        }
        continue;
    }

    $debitLineId = null;
    $creditLineId = null;
    foreach ($lines as $line) {
        if (floatval($line['debit']) > 0) {
            $debitLineId = $line['id'];
        }
        if (floatval($line['credit']) > 0) {
            $creditLineId = $line['id'];
        }
    }

    if (!$debitLineId || !$creditLineId) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        $updatedEntries++;
    } else {
        $db->beginTransaction();
        try {
            $updateDebit = $db->prepare("UPDATE journal_entry_lines SET account_id = ? WHERE id = ?");
            $updateDebit->execute([$claimsExpenseAccountId, $debitLineId]);

            $updateCredit = $db->prepare("UPDATE journal_entry_lines SET account_id = ? WHERE id = ?");
            $updateCredit->execute([$cashAccountId, $creditLineId]);

            $updateDisb = $db->prepare("UPDATE disbursements SET account_id = ? WHERE id = ?");
            $updateDisb->execute([$claimsExpenseAccountId, $disb['id']]);

            $db->commit();
            $updatedEntries++;
            $updatedDisbursements++;
        } catch (Exception $e) {
            $db->rollBack();
            fwrite(STDERR, "Failed to update {$ref}: " . $e->getMessage() . PHP_EOL);
        }
    }
}

echo ($dryRun ? "DRY RUN: " : "") . "HR3 claims disbursement entries scanned: " . count($disbursements) . PHP_EOL;
echo ($dryRun ? "DRY RUN: " : "") . "Journal entries updated: " . $updatedEntries . PHP_EOL;
echo ($dryRun ? "DRY RUN: " : "") . "Disbursements updated: " . $updatedDisbursements . PHP_EOL;
echo ($dryRun ? "DRY RUN: " : "") . "Skipped (no entry/lines): " . $skipped . PHP_EOL;
