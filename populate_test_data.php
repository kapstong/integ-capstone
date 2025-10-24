<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Populating test data...\n";

    // Check if data already exists
    $stmt = $db->query("SELECT COUNT(*) as count FROM chart_of_accounts");
    $count = $stmt->fetch()['count'];

    if ($count > 0) {
        echo "Data already exists. Skipping population.\n";
        exit(0);
    }

    // Clear existing data first
    $db->exec("DELETE FROM journal_entry_lines");
    $db->exec("DELETE FROM journal_entries");
    $db->exec("DELETE FROM chart_of_accounts");

    // Insert test chart of accounts
    $accounts = [
        ['1001', 'Cash', 'asset', 'Current Assets', 'Cash on hand and in bank'],
        ['1002', 'Accounts Receivable', 'asset', 'Current Assets', 'Money owed by customers'],
        ['1101', 'Office Supplies', 'asset', 'Current Assets', 'Office supplies inventory'],
        ['2001', 'Accounts Payable', 'liability', 'Current Liabilities', 'Money owed to vendors'],
        ['2101', 'Loans Payable', 'liability', 'Current Liabilities', 'Short-term loans'],
        ['3001', 'Owner\'s Equity', 'equity', 'Equity', 'Owner investment'],
        ['3101', 'Retained Earnings', 'equity', 'Equity', 'Accumulated profits'],
        ['4001', 'Service Revenue', 'revenue', 'Revenue', 'Revenue from services'],
        ['4101', 'Product Sales', 'revenue', 'Revenue', 'Revenue from products'],
        ['5001', 'Rent Expense', 'expense', 'Operating Expenses', 'Monthly rent'],
        ['5002', 'Utilities', 'expense', 'Operating Expenses', 'Electricity and water'],
        ['5101', 'Salaries Expense', 'expense', 'Operating Expenses', 'Employee salaries'],
        ['5201', 'Office Supplies Expense', 'expense', 'Operating Expenses', 'Office supplies used']
    ];

    foreach ($accounts as $account) {
        $stmt = $db->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute($account);
        echo "Inserted account: {$account[0]} - {$account[1]}\n";
    }

    // Create some test journal entries
    $entries = [
        [
            'JE001',
            '2025-10-01',
            'Initial setup - Owner investment',
            [
                ['3001', 50000.00, 0.00],  // Owner's Equity - Credit
                ['1001', 50000.00, 0.00]   // Cash - Debit
            ]
        ],
        [
            'JE002',
            '2025-10-05',
            'Office supplies purchase',
            [
                ['1101', 2500.00, 0.00],  // Office Supplies - Debit
                ['1001', 0.00, 2500.00],  // Cash - Credit
                ['2001', 0.00, 2500.00]   // Accounts Payable - Credit
            ]
        ],
        [
            'JE003',
            '2025-10-10',
            'Service revenue',
            [
                ['1001', 15000.00, 0.00],  // Cash - Debit
                ['4001', 0.00, 15000.00]   // Service Revenue - Credit
            ]
        ],
        [
            'JE004',
            '2025-10-15',
            'Rent payment',
            [
                ['5001', 5000.00, 0.00],  // Rent Expense - Debit
                ['1001', 0.00, 5000.00]   // Cash - Credit
            ]
        ]
    ];

    foreach ($entries as $entry) {
        // Insert journal entry header
        $stmt = $db->prepare("INSERT INTO journal_entries (entry_number, entry_date, description, status) VALUES (?, ?, ?, 'posted')");
        $stmt->execute([$entry[0], $entry[1], $entry[2]]);
        $entryId = $db->lastInsertId();

        // Insert journal entry lines
        foreach ($entry[3] as $line) {
            $stmt = $db->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit) VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = ?), ?, ?)");
            $stmt->execute([$entryId, $line[0], $line[1], $line[2]]);
        }

        echo "Inserted journal entry: {$entry[0]}\n";
    }

    echo "\n✅ Test data populated successfully!\n";
    echo "📊 Summary: " . count($accounts) . " accounts, " . count($entries) . " journal entries\n";
    echo "\nYou can now view the General Ledger page to see the data.\n";

} catch (Exception $e) {
    echo "❌ Error populating test data: " . $e->getMessage() . "\n";
}
?>
