<?php
// Add sample payroll data to demonstrate HR4 integration in reports

require_once 'config.php';
require_once 'includes/database.php';

$db = Database::getInstance()->getConnection();

try {
    $data = [
        ['2025-10-25', 'Kitchen', 25000, 'Payroll'],
        ['2025-10-25', 'Bar', 18000, 'Payroll'],
        ['2025-10-25', 'Front Desk', 12000, 'Payroll'],
        ['2025-10-25', 'Housekeeping', 8000, 'Payroll'],
        ['2025-10-24', 'Kitchen', 25000, 'Payroll'],
        ['2025-10-24', 'Bar', 18000, 'Payroll'],
        ['2025-10-24', 'Front Desk', 12000, 'Payroll'],
        ['2025-10-24', 'Housekeeping', 8000, 'Payroll'],
        ['2025-10-23', 'Kitchen', 15000, 'Operating'],
        ['2025-10-23', 'Bar', 12000, 'Operating'],
        ['2025-10-23', 'Front Desk', 8000, 'Operating'],
        ['2025-10-23', 'Housekeeping', 6000, 'Operating']
    ];

    $stmt = $db->prepare("INSERT INTO daily_expense_summary (expense_date, department, daily_expenses, expense_type) VALUES (?, ?, ?, ?)");

    foreach ($data as $row) {
        $stmt->execute($row);
    }

    echo "✅ Added 12 sample payroll records to demonstrate HR4 integration.\n";
    echo "Now when you visit admin/reports.php → Profit & Loss tab, you'll see departmental expenses including payroll!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
