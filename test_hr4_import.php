<?php
/**
 * HR4 Payroll Import Test Script
 * This script manually tests and imports payroll data from HR4 API
 */

require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/logger.php';
require_once 'includes/api_integrations.php';

// Set headers for output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>HR4 Payroll Import Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #1e2936; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e2936; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e2936; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #2c3e50; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 HR4 Payroll Import Test</h1>

        <?php
        echo '<div class="info">Starting HR4 payroll import test at ' . date('Y-m-d H:i:s') . '</div>';

        try {
            // Step 1: Test HR4 API Connection
            echo '<div class="section">';
            echo '<h2>Step 1: Testing HR4 API Connection</h2>';

            $apiUrl = 'https://hr4.atierahotelandrestaurant.com/payroll_api.php';
            echo '<p><strong>API URL:</strong> ' . htmlspecialchars($apiUrl) . '</p>';

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                echo '<div class="error"><strong>cURL Error:</strong> ' . htmlspecialchars($curlError) . '</div>';
                throw new Exception('Failed to connect to HR4 API: ' . $curlError);
            }

            echo '<div class="success">✅ HTTP Response Code: ' . $httpCode . '</div>';

            if ($httpCode !== 200) {
                throw new Exception('HR4 API returned HTTP ' . $httpCode);
            }

            // Parse JSON response
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="error"><strong>JSON Parse Error:</strong> ' . json_last_error_msg() . '</div>';
                echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                throw new Exception('Invalid JSON response from HR4 API');
            }

            echo '<div class="success">✅ Valid JSON response received</div>';

            // Display API response structure
            echo '<h3>API Response Data:</h3>';
            echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';

            echo '</div>'; // End Step 1

            // Step 2: Parse and validate payroll data
            echo '<div class="section">';
            echo '<h2>Step 2: Parsing Payroll Data</h2>';

            if (!isset($data['success']) || !$data['success']) {
                throw new Exception('API returned success=false');
            }

            if (!isset($data['payroll_data']) || empty($data['payroll_data'])) {
                throw new Exception('No payroll data found in API response');
            }

            $employeeCount = count($data['payroll_data']);
            $month = $data['month'] ?? 'Unknown';
            $totals = $data['totals'] ?? [];

            echo '<div class="success">✅ Found ' . $employeeCount . ' employee(s) for month: ' . htmlspecialchars($month) . '</div>';

            if (!empty($totals)) {
                echo '<h3>Payroll Totals:</h3>';
                echo '<ul>';
                echo '<li><strong>Total Payroll:</strong> ₱' . number_format($totals['total_payroll'] ?? 0, 2) . '</li>';
                echo '<li><strong>Employee Count:</strong> ' . ($totals['employee_count'] ?? 0) . '</li>';
                echo '<li><strong>Average Salary:</strong> ₱' . number_format($totals['average_salary'] ?? 0, 2) . '</li>';
                echo '</ul>';
            }

            // Display employee details
            echo '<h3>Employee Payroll Details:</h3>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Name</th><th>Position</th><th>Department</th><th>Gross</th><th>Net Pay</th><th>Status</th></tr>';

            foreach ($data['payroll_data'] as $employee) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($employee['id']) . '</td>';
                echo '<td>' . htmlspecialchars($employee['name']) . '</td>';
                echo '<td>' . htmlspecialchars($employee['position']) . '</td>';
                echo '<td>' . htmlspecialchars($employee['department']) . '</td>';
                echo '<td>₱' . number_format($employee['gross'], 2) . '</td>';
                echo '<td>₱' . number_format($employee['net'], 2) . '</td>';
                echo '<td>' . htmlspecialchars($employee['status']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            echo '</div>'; // End Step 2

            // Step 3: Import data using API Integration Manager
            echo '<div class="section">';
            echo '<h2>Step 3: Importing Payroll Data to Database</h2>';

            $integrationManager = APIIntegrationManager::getInstance();

            // Get HR4 configuration
            $hr4Config = $integrationManager->getIntegrationConfig('hr4');

            if (!$hr4Config) {
                throw new Exception('HR4 integration not configured');
            }

            echo '<div class="info">HR4 Integration configured with API URL: ' . htmlspecialchars($hr4Config['api_url']) . '</div>';

            // Execute import
            echo '<p>Executing importPayroll action...</p>';
            $importResult = $integrationManager->executeIntegrationAction('hr4', 'importPayroll', []);

            echo '<h3>Import Result:</h3>';
            echo '<pre>' . htmlspecialchars(json_encode($importResult, JSON_PRETTY_PRINT)) . '</pre>';

            if ($importResult['success']) {
                echo '<div class="success">✅ Successfully imported ' . $importResult['imported_count'] . ' payroll record(s)</div>';

                if (!empty($importResult['errors'])) {
                    echo '<div class="warning"><strong>Warnings:</strong><ul>';
                    foreach ($importResult['errors'] as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul></div>';
                }
            } else {
                echo '<div class="error">❌ Import failed</div>';
                if (!empty($importResult['errors'])) {
                    echo '<div class="error"><strong>Errors:</strong><ul>';
                    foreach ($importResult['errors'] as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul></div>';
                }
            }

            echo '</div>'; // End Step 3

            // Step 4: Verify imported data
            echo '<div class="section">';
            echo '<h2>Step 4: Verifying Imported Data</h2>';

            $db = Database::getInstance()->getConnection();

            // Check imported_transactions table
            echo '<h3>Imported Transactions (Last 10):</h3>';
            $stmt = $db->query("
                SELECT * FROM imported_transactions
                WHERE source_system = 'HR_SYSTEM'
                ORDER BY imported_at DESC
                LIMIT 10
            ");
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($transactions)) {
                echo '<div class="warning">⚠️ No records found in imported_transactions table</div>';
            } else {
                echo '<div class="success">✅ Found ' . count($transactions) . ' transaction(s)</div>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Batch</th><th>Date</th><th>Type</th><th>External ID</th><th>Dept</th><th>Amount</th><th>Status</th></tr>';
                foreach ($transactions as $tx) {
                    echo '<tr>';
                    echo '<td>' . $tx['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($tx['import_batch']) . '</td>';
                    echo '<td>' . $tx['transaction_date'] . '</td>';
                    echo '<td>' . htmlspecialchars($tx['transaction_type']) . '</td>';
                    echo '<td>' . htmlspecialchars($tx['external_id']) . '</td>';
                    echo '<td>' . $tx['department_id'] . '</td>';
                    echo '<td>₱' . number_format($tx['amount'], 2) . '</td>';
                    echo '<td>' . htmlspecialchars($tx['status']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            // Check daily_expense_summary table
            echo '<h3>Daily Expense Summary (Payroll - Last 10):</h3>';
            $stmt = $db->query("
                SELECT des.*, d.dept_name
                FROM daily_expense_summary des
                LEFT JOIN departments d ON des.department_id = d.id
                WHERE des.expense_category = 'labor_payroll'
                ORDER BY des.updated_at DESC
                LIMIT 10
            ");
            $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($summaries)) {
                echo '<div class="warning">⚠️ No records found in daily_expense_summary table</div>';
            } else {
                echo '<div class="success">✅ Found ' . count($summaries) . ' expense summary record(s)</div>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Date</th><th>Department</th><th>Category</th><th>Source</th><th>Transactions</th><th>Total Amount</th><th>Updated</th></tr>';
                foreach ($summaries as $summary) {
                    echo '<tr>';
                    echo '<td>' . $summary['id'] . '</td>';
                    echo '<td>' . $summary['business_date'] . '</td>';
                    echo '<td>' . htmlspecialchars($summary['dept_name'] ?? 'Dept ' . $summary['department_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($summary['expense_category']) . '</td>';
                    echo '<td>' . htmlspecialchars($summary['source_system']) . '</td>';
                    echo '<td>' . $summary['total_transactions'] . '</td>';
                    echo '<td>₱' . number_format($summary['total_amount'], 2) . '</td>';
                    echo '<td>' . $summary['updated_at'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            echo '</div>'; // End Step 4

            // Summary
            echo '<div class="section" style="background: #d4edda;">';
            echo '<h2>✅ Import Test Complete!</h2>';
            echo '<p><strong>Next Steps:</strong></p>';
            echo '<ul>';
            echo '<li>Go to <a href="admin/reports.php" class="btn">View Reports</a> to see the imported payroll data</li>';
            echo '<li>Check the Income Statement for payroll expenses</li>';
            echo '<li>Check the Cash Flow Statement for operating activities</li>';
            echo '</ul>';
            echo '<p><a href="admin/integrations.php" class="btn">Manage Integrations</a></p>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Error During Import:</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <h3>Troubleshooting Tips:</h3>
            <ul>
                <li>If the API connection fails, check if the HR4 system is online</li>
                <li>Verify the API URL is correct in config/integrations/hr4.json</li>
                <li>Check database tables: <code>imported_transactions</code> and <code>daily_expense_summary</code></li>
                <li>Review logs in the Logger for detailed error messages</li>
            </ul>
            <p><a href="test_hr4_import.php" class="btn">🔄 Run Test Again</a></p>
        </div>
    </div>
</body>
</html>
