<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Populating AP test data...\n";

    // Check if AP data already exists
    $stmt = $db->query("SELECT COUNT(*) as count FROM vendors");
    $count = $stmt->fetch()['count'];

    if ($count > 0) {
        echo "AP data already exists. Skipping population.\n";
        exit(0);
    }

    // Clear existing AP data first (in case incomplete data exists)
    $db->exec("DELETE FROM adjustments");
    $db->exec("DELETE FROM payments_made");
    $db->exec("DELETE FROM bills");
    $db->exec("DELETE FROM vendors");

    // Insert test vendors (updated to use auto-incremented IDs)
    $vendors = [
        ['ABC Supplies Inc.', 'Jane Smith', 'jane@abcsupplies.com', '+1-555-0101', '123 Main St, NY', 'Net 30'],
        ['TechCorp Solutions', 'John Doe', 'john@techcorp.com', '+1-555-0102', '456 Tech Ave, CA', 'Net 15'],
        ['Global Services Ltd.', 'Mary Johnson', 'mary@globalservices.com', '+1-555-0103', '789 Service Rd, TX', 'Net 60'],
        ['Premium Materials Co.', 'Bob Wilson', 'bob@premiummaterials.com', '+1-555-0104', '321 Industrial Blvd, FL', 'Net 45'],
    ];

    foreach ($vendors as $vendor) {
        $stmt = $db->prepare("INSERT INTO vendors (company_name, contact_person, email, phone, address, payment_terms, status, is_active) VALUES (?, ?, ?, ?, ?, ?, 'active', 1)");
        $stmt->execute($vendor);
        echo "Inserted vendor: {$vendor[0]}\n";
    }

    // Get vendor IDs (in order of creation)
    $vendorIds = $db->query("SELECT id FROM vendors ORDER BY id LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);

    // Insert test bills
    $bills = [
        [$vendorIds[0], '2025-10-15', '2025-11-15', 25000.00, 'Office supplies for Q4', 'approved'],
        [$vendorIds[1], '2025-10-10', '2025-10-25', 15000.00, 'Software licenses', 'approved'],
        [$vendorIds[2], '2025-10-05', '2025-11-05', 35000.00, 'Consulting services', 'approved'],
        [$vendorIds[3], '2025-10-01', '2025-10-16', 12000.00, 'Raw materials', 'paid'],
        [$vendorIds[0], '2025-09-20', '2025-10-20', 8000.00, 'Office equipment', 'overdue'], // Past due
        [$vendorIds[1], '2025-09-15', '2025-10-15', 22000.00, 'IT maintenance', 'overdue'], // Past due
        [$vendorIds[2], '2025-10-12', '2025-11-12', 18000.00, 'Marketing services', 'draft'],
    ];

    foreach ($bills as $billIndex => $bill) {
        $billNumber = sprintf("BILL-%04d", $billIndex + 1);
        $stmt = $db->prepare("INSERT INTO bills (bill_number, vendor_id, bill_date, due_date, amount, description, status, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$billNumber, $bill[0], $bill[1], $bill[2], $bill[4], $bill[5], $bill[6], $bill[4]]);
        echo "Inserted bill: {$billNumber}\n";
    }

    // Get bill IDs for payments
    $billIds = $db->query("SELECT id FROM bills ORDER BY id LIMIT 7")->fetchAll(PDO::FETCH_COLUMN);

    // Insert test payments
    $payments = [
        [$vendorIds[3], $billIds[3], '2025-10-08', 12000.00, 'check', 'CHK-2025-1001'], // Paid bill #4
        [$vendorIds[0], $billIds[0], '2025-10-20', 15000.00, 'transfer', 'TRF-2025-2001'], // Partial payment for bill #1
        [$vendorIds[1], $billIds[1], '2025-10-18', 15000.00, 'check', 'CHK-2025-1002'], // Paid bill #2
        [$vendorIds[2], null, '2025-10-22', 20000.00, 'transfer', 'TRF-2025-2002'], // Payment without specific bill
        [$vendorIds[0], $billIds[4], '2025-09-25', 8000.00, 'cash', 'CSH-2025-3001'], // Paid overdue bill #5
    ];

    foreach ($payments as $paymentIndex => $payment) {
        $paymentNumber = sprintf("PAY-%04d", $paymentIndex + 1);
        $stmt = $db->prepare("INSERT INTO payments_made (payment_number, vendor_id, bill_id, payment_date, amount, payment_method, reference_number, payment_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'made', '')");
        $stmt->execute([$paymentNumber, $payment[0], $payment[1], $payment[2], $payment[3], $payment[4], $payment[5]]);
        echo "Inserted payment: {$paymentNumber}\n";
    }

    // Update bill balances for payments made
    $billPayments = [
        [$billIds[0], 15000.00], // Reduce bill #1 balance by partial payment
        [$billIds[4], 8000.00],   // Fully paid bill #5
    ];

    foreach ($billPayments as $payment) {
        $stmt = $db->prepare("UPDATE bills SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$payment[1], $payment[0]]);
    }

    // Insert test adjustments
    $adjustments = [
        [$vendorIds[0], 'debit_memo', '2025-10-16', 2500.00, 'Discount for early payment'],
        [$vendorIds[1], 'credit_memo', '2025-10-20', 1000.00, 'Adjustment for overbilling'],
        [$vendorIds[2], 'discount', '2025-10-14', 1200.00, 'Volume discount'],
    ];

    foreach ($adjustments as $adjIndex => $adjustment) {
        $adjNumber = sprintf("ADJ-%04d", $adjIndex + 1);
        $stmt = $db->prepare("INSERT INTO adjustments (adjustment_number, vendor_id, adjustment_type, adjustment_date, amount, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$adjNumber, $adjustment[0], $adjustment[1], $adjustment[2], $adjustment[3], $adjustment[4]]);
        echo "Inserted adjustment: {$adjNumber}\n";
    }

    // Update bill balances for adjustments
    $stmt = $db->prepare("UPDATE bills SET balance = balance - 2500 WHERE id = ?");
    $stmt->execute([$billIds[0]]); // Reduce bill #1 by debit memo

    echo "\n✅ AP test data populated successfully!\n";
    echo "📊 Summary: " . count($vendors) . " vendors, " . count($bills) . " bills, " . count($payments) . " payments, " . count($adjustments) . " adjustments\n";
    echo "\nYou can now view the Accounts Payable page to see the populated data in the overview cards and reports.\n";

} catch (Exception $e) {
    echo "❌ Error populating AP test data: " . $e->getMessage() . "\n";
}
?>
