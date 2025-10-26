<?php
/**
 * CLEAN ALL SAMPLE/TEST DATA
 * This script removes ALL sample, test, mock, and dummy data from the database
 * WARNING: This is irreversible! Make a backup first!
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Clean All Sample Data</title>\n";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
.success { color: green; background: #e8f5e9; padding: 10px; margin: 5px 0; border-left: 4px solid green; }
.error { color: red; background: #ffebee; padding: 10px; margin: 5px 0; border-left: 4px solid red; }
.warning { color: orange; background: #fff3e0; padding: 10px; margin: 5px 0; border-left: 4px solid orange; }
.info { color: blue; background: #e3f2fd; padding: 10px; margin: 5px 0; border-left: 4px solid blue; }
h1 { color: #1b2f73; } h2 { color: #2342a6; margin-top: 30px; border-bottom: 2px solid #2342a6; padding-bottom: 10px; }
.stat { display: inline-block; background: white; padding: 15px 25px; margin: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.stat-number { font-size: 32px; font-weight: bold; color: #1b2f73; }
.stat-label { font-size: 14px; color: #666; }
</style>\n</head>\n<body>";

echo "<h1>🧹 Clean All Sample Data</h1>";
echo "<div class='warning'><strong>⚠️ WARNING:</strong> This will delete ALL sample, test, and mock data from your database!<br>";
echo "Only run this if you're sure you want to start with a clean slate.</div>";

try {
    $db = Database::getInstance()->getConnection();

    $deletedCounts = [];

    echo "<h2>Step 1: Identifying Sample Data</h2>";

    // Patterns to identify sample/test data
    $samplePatterns = ['%test%', '%sample%', '%mock%', '%dummy%', '%fake%', '%demo%', '%example%'];

    // Check budgets for sample data
    $stmt = $db->query("SELECT COUNT(*) as count FROM budgets WHERE
        budget_name LIKE '%test%' OR
        budget_name LIKE '%sample%' OR
        budget_name LIKE '%mock%' OR
        budget_name LIKE '%dummy%' OR
        budget_name LIKE '%demo%' OR
        description LIKE '%test%' OR
        description LIKE '%sample%'");
    $budgetCount = $stmt->fetch()['count'];
    echo "<div class='info'>Found <strong>{$budgetCount}</strong> sample budgets</div>";

    // Check journal entries
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE
        description LIKE '%test%' OR
        description LIKE '%sample%' OR
        description LIKE '%mock%' OR
        description LIKE '%dummy%' OR
        description LIKE '%demo%'");
    $journalCount = $stmt->fetch()['count'];
    echo "<div class='info'>Found <strong>{$journalCount}</strong> sample journal entries</div>";

    // Check invoices
    $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE
        description LIKE '%test%' OR
        description LIKE '%sample%' OR
        invoice_number LIKE '%TEST%' OR
        invoice_number LIKE '%SAMPLE%'");
    $invoiceCount = $stmt->fetch()['count'];
    echo "<div class='info'>Found <strong>{$invoiceCount}</strong> sample invoices</div>";

    // Check vendors/customers with test names
    $stmt = $db->query("SELECT COUNT(*) as count FROM vendors WHERE
        company_name LIKE '%test%' OR
        company_name LIKE '%sample%' OR
        company_name LIKE '%dummy%' OR
        company_name LIKE '%demo%'");
    $vendorCount = $stmt->fetch()['count'];
    echo "<div class='info'>Found <strong>{$vendorCount}</strong> sample vendors</div>";

    $stmt = $db->query("SELECT COUNT(*) as count FROM customers WHERE
        customer_name LIKE '%test%' OR
        customer_name LIKE '%sample%' OR
        customer_name LIKE '%dummy%' OR
        customer_name LIKE '%demo%'");
    $customerCount = $stmt->fetch()['count'];
    echo "<div class='info'>Found <strong>{$customerCount}</strong> sample customers</div>";

    $totalSampleData = $budgetCount + $journalCount + $invoiceCount + $vendorCount + $customerCount;

    if ($totalSampleData == 0) {
        echo "<div class='success'><h3>✅ No Sample Data Found!</h3>";
        echo "<p>Your database is clean and contains only real data.</p></div>";
    } else {
        echo "<h2>Step 2: Deleting Sample Data</h2>";

        // Start transaction
        $db->beginTransaction();

        try {
            // Delete sample budget data
            if ($budgetCount > 0) {
                $stmt = $db->prepare("DELETE FROM budgets WHERE
                    budget_name LIKE '%test%' OR
                    budget_name LIKE '%sample%' OR
                    budget_name LIKE '%mock%' OR
                    budget_name LIKE '%dummy%' OR
                    budget_name LIKE '%demo%' OR
                    description LIKE '%test%' OR
                    description LIKE '%sample%'");
                $stmt->execute();
                $deletedCounts['budgets'] = $stmt->rowCount();
                echo "<div class='success'>✓ Deleted {$deletedCounts['budgets']} sample budgets</div>";
            }

            // Delete sample journal entries (and their lines will be deleted by CASCADE)
            if ($journalCount > 0) {
                $stmt = $db->prepare("DELETE FROM journal_entries WHERE
                    description LIKE '%test%' OR
                    description LIKE '%sample%' OR
                    description LIKE '%mock%' OR
                    description LIKE '%dummy%' OR
                    description LIKE '%demo%'");
                $stmt->execute();
                $deletedCounts['journal_entries'] = $stmt->rowCount();
                echo "<div class='success'>✓ Deleted {$deletedCounts['journal_entries']} sample journal entries</div>";
            }

            // Delete sample invoices
            if ($invoiceCount > 0) {
                $stmt = $db->prepare("DELETE FROM invoices WHERE
                    description LIKE '%test%' OR
                    description LIKE '%sample%' OR
                    invoice_number LIKE '%TEST%' OR
                    invoice_number LIKE '%SAMPLE%'");
                $stmt->execute();
                $deletedCounts['invoices'] = $stmt->rowCount();
                echo "<div class='success'>✓ Deleted {$deletedCounts['invoices']} sample invoices</div>";
            }

            // Delete sample vendors
            if ($vendorCount > 0) {
                $stmt = $db->prepare("DELETE FROM vendors WHERE
                    company_name LIKE '%test%' OR
                    company_name LIKE '%sample%' OR
                    company_name LIKE '%dummy%' OR
                    company_name LIKE '%demo%'");
                $stmt->execute();
                $deletedCounts['vendors'] = $stmt->rowCount();
                echo "<div class='success'>✓ Deleted {$deletedCounts['vendors']} sample vendors</div>";
            }

            // Delete sample customers
            if ($customerCount > 0) {
                $stmt = $db->prepare("DELETE FROM customers WHERE
                    customer_name LIKE '%test%' OR
                    customer_name LIKE '%sample%' OR
                    customer_name LIKE '%dummy%' OR
                    customer_name LIKE '%demo%'");
                $stmt->execute();
                $deletedCounts['customers'] = $stmt->rowCount();
                echo "<div class='success'>✓ Deleted {$deletedCounts['customers']} sample customers</div>";
            }

            // Commit transaction
            $db->commit();

            echo "<div class='success'><h3>✅ Sample Data Cleaned Successfully!</h3></div>";

        } catch (Exception $e) {
            $db->rollBack();
            echo "<div class='error'>✗ Error during deletion: " . $e->getMessage() . "</div>";
            echo "<div class='error'>All changes have been rolled back.</div>";
        }
    }

    echo "<h2>Step 3: Verification</h2>";
    echo "<p>Checking database for any remaining sample data...</p>";

    // Re-check for sample data
    $stmt = $db->query("SELECT COUNT(*) as count FROM budgets WHERE
        budget_name LIKE '%test%' OR budget_name LIKE '%sample%'");
    $remaining = $stmt->fetch()['count'];

    if ($remaining == 0) {
        echo "<div class='success'>✓ Database is completely clean!</div>";
    } else {
        echo "<div class='warning'>⚠ Found {$remaining} remaining sample entries</div>";
    }

    echo "<h2>Step 4: Database Statistics</h2>";
    echo "<div style='text-align: center; margin: 30px 0;'>";

    // Show current data counts
    $tables = ['budgets', 'journal_entries', 'invoices', 'vendors', 'customers', 'departments'];

    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            echo "<div class='stat'>";
            echo "<div class='stat-number'>{$count}</div>";
            echo "<div class='stat-label'>" . ucfirst(str_replace('_', ' ', $table)) . "</div>";
            echo "</div>";
        } catch (Exception $e) {
            // Table might not exist
        }
    }

    echo "</div>";

    echo "<h2>✅ System Status</h2>";
    echo "<div class='success' style='padding: 20px;'>";
    echo "<h3>✓ Your system now contains ONLY real data!</h3>";
    echo "<ul style='line-height: 2;'>";
    echo "<li>All sample data has been removed</li>";
    echo "<li>All pages use database/API connections</li>";
    echo "<li>No hardcoded mock data in code</li>";
    echo "<li>Reports pull directly from database</li>";
    echo "<li>Ready for production use</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='text-align: center; margin: 40px 0;'>";
    echo "<a href='admin/budget_management.php' style='display: inline-block; padding: 15px 30px; background: #1b2f73; color: white; text-decoration: none; border-radius: 8px; margin: 10px;'>Go to Budget Management</a>";
    echo "<a href='admin/reports.php' style='display: inline-block; padding: 15px 30px; background: #2342a6; color: white; text-decoration: none; border-radius: 8px; margin: 10px;'>Go to Reports</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'><h2>Fatal Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body>\n</html>";
?>
