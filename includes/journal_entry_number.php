<?php

/**
 * Generate a journal entry number in the format:
 * JE-{YYYY}-{ACCT}-{SEQ}
 * Example: JE-2026-1001-0001
 */
function generateJournalEntryNumber(PDO $db, ?int $accountId = null, ?string $entryDate = null): string
{
    $year = date('Y', strtotime($entryDate ?? date('Y-m-d')));
    $accountCode = '0000';

    if ($accountId) {
        $stmt = $db->prepare("SELECT account_code FROM chart_of_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['account_code'])) {
            $accountCode = (string)$row['account_code'];
        }
    }

    $accountCode = preg_replace('/[^A-Za-z0-9]/', '', $accountCode) ?: '0000';
    $accountCode = substr($accountCode, 0, 6);

    $pattern = "JE-{$year}-{$accountCode}-%";
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(entry_number, '-', -1) AS UNSIGNED)) as max_num
        FROM journal_entries
        WHERE entry_number LIKE ?
    ");
    $stmt->execute([$pattern]);
    $max = (int)($stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0);

    $seq = $max + 1;
    $entryNumber = sprintf('JE-%s-%s-%04d', $year, $accountCode, $seq);

    // Ensure uniqueness in case of race conditions
    while (true) {
        $check = $db->prepare("SELECT id FROM journal_entries WHERE entry_number = ? LIMIT 1");
        $check->execute([$entryNumber]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            break;
        }
        $seq++;
        $entryNumber = sprintf('JE-%s-%s-%04d', $year, $accountCode, $seq);
    }

    return $entryNumber;
}

