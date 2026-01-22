<?php
/**
 * Chart of Accounts validation helpers.
 */

function normalizeChartOfAccountsIds(array $ids, array &$nonNumeric = []) {
    $normalized = [];
    foreach ($ids as $id) {
        if ($id === null) {
            continue;
        }
        if (is_string($id) && trim($id) === '') {
            continue;
        }
        if (!is_numeric($id)) {
            $nonNumeric[] = $id;
            continue;
        }
        $normalized[] = (int)$id;
    }
    return array_values(array_unique($normalized));
}

function findInvalidChartOfAccountsIds(PDO $db, array $ids) {
    $nonNumeric = [];
    $normalized = normalizeChartOfAccountsIds($ids, $nonNumeric);
    if (empty($normalized)) {
        return $nonNumeric;
    }

    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE is_active = 1 AND id IN ($placeholders)");
    $stmt->execute($normalized);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $found = array_map('intval', array_column($rows, 'id'));

    $missing = array_values(array_diff($normalized, $found));
    return array_values(array_unique(array_merge($missing, $nonNumeric)));
}
