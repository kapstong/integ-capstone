<?php
/**
 * Budget alert utilities (thresholds, calculations, recipients)
 */

function getBudgetAlertThresholds() {
    return [
        ['percent' => 100, 'status' => 'red', 'label' => 'Red', 'color' => '#dc3545'],
        ['percent' => 90, 'status' => 'orange', 'label' => 'Orange', 'color' => '#fd7e14'],
        ['percent' => 80, 'status' => 'light_orange', 'label' => 'Light Orange', 'color' => '#ffb347'],
        ['percent' => 70, 'status' => 'yellow', 'label' => 'Yellow', 'color' => '#ffc107']
    ];
}

function calculateBudgetAlerts($db) {
    $stmt = $db->prepare("
        SELECT
            d.id AS department_id,
            d.dept_name AS department_name,
            d.dept_code AS department_code,
            b.budget_year,
            COALESCE(SUM(bi.budgeted_amount), 0) AS budgeted_amount,
            COALESCE(SUM(bi.actual_amount), 0) AS actual_amount,
            COALESCE(SUM(
                CASE
                    WHEN ba.status = 'pending' THEN ba.amount
                    ELSE 0
                END
            ), 0) AS committed_amount
        FROM budget_items bi
        JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN departments d ON bi.department_id = d.id
        LEFT JOIN budget_adjustments ba ON ba.budget_id = b.id
            AND ba.department_id = bi.department_id
            AND ba.status = 'pending'
        WHERE b.status IN ('approved', 'active')
        GROUP BY bi.department_id, d.id, d.dept_name, d.dept_code, b.budget_year
        ORDER BY d.dept_name
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $thresholds = getBudgetAlertThresholds();
    $alerts = [];

    foreach ($rows as $row) {
        $budgeted = (float) $row['budgeted_amount'];
        $actual = (float) $row['actual_amount'];
        $committed = (float) $row['committed_amount'];
        $utilized = $actual + $committed;
        $percent = $budgeted > 0 ? ($utilized / $budgeted) * 100 : 0;

        $status = null;
        foreach ($thresholds as $threshold) {
            if ($percent >= $threshold['percent']) {
                $status = $threshold;
                break;
            }
        }

        if (!$status) {
            continue;
        }

        $overAmount = max(0, $utilized - $budgeted);
        $overPercent = $budgeted > 0 ? ($overAmount / $budgeted) * 100 : 0;

        $alerts[] = [
            'id' => count($alerts) + 1,
            'department' => $row['department_name'] ?: 'Unassigned',
            'department_code' => $row['department_code'] ?: null,
            'department_id' => $row['department_id'],
            'budget_year' => $row['budget_year'],
            'budgeted_amount' => $budgeted,
            'actual_amount' => $actual,
            'committed_amount' => $committed,
            'utilized_amount' => $utilized,
            'utilization_percent' => (float) $percent,
            'over_amount' => (float) $overAmount,
            'over_percent' => (float) $overPercent,
            'severity' => $status['status'],
            'severity_label' => $status['label'],
            'severity_color' => $status['color'],
            'alert_date' => date('Y-m-d H:i:s')
        ];
    }

    return $alerts;
}

function getDepartmentAlertRecipients($db, $departmentId, $departmentName, $departmentCode) {
    $emails = [];

    $deptEmail = null;
    try {
        $columnCheck = $db->prepare("
            SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'departments'
              AND COLUMN_NAME = 'department_email'
        ");
        $columnCheck->execute();
        $exists = (int) $columnCheck->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
        if ($exists > 0) {
            $stmt = $db->prepare("SELECT department_email FROM departments WHERE id = ? LIMIT 1");
            $stmt->execute([$departmentId]);
            $deptEmail = $stmt->fetch(PDO::FETCH_ASSOC)['department_email'] ?? null;
        }
    } catch (Exception $e) {
        $deptEmail = null;
    }

    if ($deptEmail) {
        $emails[] = $deptEmail;
    }

    $deptTargets = array_values(array_filter([
        $departmentName,
        $departmentCode
    ]));

    if (empty($deptTargets)) {
        return $emails;
    }

    $placeholders = implode(',', array_fill(0, count($deptTargets), '?'));
    $stmt = $db->prepare("
        SELECT DISTINCT email
        FROM users
        WHERE status = 'active'
          AND email IS NOT NULL
          AND email != ''
          AND department IN ($placeholders)
    ");
    $stmt->execute($deptTargets);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $emails[] = $row['email'];
    }

    return array_values(array_unique($emails));
}
