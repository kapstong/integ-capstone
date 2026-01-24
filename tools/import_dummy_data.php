<?php
/**
 * Dummy Data Seeding Tool
 * Safe, reversible data import for testing and development
 */

session_start();

// Security check - only allow superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    die('Access Denied: Superadmin access required.');
}

require_once 'includes/database.php';

$result = array(
    'status' => 'idle',
    'message' => '',
    'records_inserted' => 0,
    'errors' => array()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'import') {
        try {
            $sql_file = __DIR__ . '/dummy_data_seeding.sql';
            
            if (!file_exists($sql_file)) {
                throw new Exception("Dummy data file not found: $sql_file");
            }
            
            $sql_content = file_get_contents($sql_file);
            
            // Parse SQL statements
            $statements = array();
            $current_statement = '';
            $in_multiline = false;
            
            $lines = explode("\n", $sql_content);
            
            foreach ($lines as $line) {
                $trimmed = trim($line);
                
                // Skip comments and empty lines
                if (empty($trimmed) || substr($trimmed, 0, 2) === '--') {
                    continue;
                }
                
                $current_statement .= ' ' . $line;
                
                // Check for statement terminator
                if (substr(rtrim($trimmed), -1) === ';') {
                    $stmt = trim(str_replace(';;', ';', $current_statement));
                    if (!empty($stmt)) {
                        $statements[] = $stmt;
                    }
                    $current_statement = '';
                }
            }
            
            // Execute statements
            $executed = 0;
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    if ($conn->multi_query($stmt) === FALSE) {
                        // Try to extract INSERT count
                        if (strpos($stmt, 'INSERT') !== false) {
                            $result['errors'][] = "Query Error: " . $conn->error;
                        }
                    } else {
                        // Count successful executions
                        if (preg_match('/INSERT INTO/i', $stmt)) {
                            $executed++;
                        }
                        
                        // Consume all results
                        while ($conn->next_result()) {
                            if ($res = $conn->use_result()) {
                                $res->free();
                            }
                        }
                    }
                }
            }
            
            $result['status'] = 'success';
            $result['message'] = 'Dummy data imported successfully!';
            $result['records_inserted'] = $executed;
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Error: ' . $e->getMessage();
            $result['errors'][] = $e->getMessage();
        }
    } 
    elseif ($action === 'backup') {
        try {
            // Create backup tables
            $tables_to_backup = array(
                'customers', 'vendors', 'invoices', 'bills', 'journal_entries',
                'chart_of_accounts', 'payments', 'disbursements', 'budgets',
                'fixed_assets', 'asset_depreciation_schedule', 'users', 'departments'
            );
            
            $backup_timestamp = date('YmdHis');
            
            foreach ($tables_to_backup as $table) {
                $backup_table = $table . '_backup_' . $backup_timestamp;
                $sql = "CREATE TABLE IF NOT EXISTS `$backup_table` LIKE `$table`;
                        INSERT INTO `$backup_table` SELECT * FROM `$table`;";
                
                if ($conn->multi_query($sql) === FALSE) {
                    throw new Exception("Backup failed for table: $table");
                }
                
                // Consume results
                while ($conn->next_result()) {
                    if ($res = $conn->use_result()) {
                        $res->free();
                    }
                }
            }
            
            $result['status'] = 'success';
            $result['message'] = "Data backed up successfully (suffix: $backup_timestamp)";
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Backup Error: ' . $e->getMessage();
        }
    }
    elseif ($action === 'verify') {
        try {
            // Verify data integrity
            $verification_queries = array(
                'SELECT COUNT(*) as count FROM customers' => 'Customers',
                'SELECT COUNT(*) as count FROM vendors' => 'Vendors',
                'SELECT COUNT(*) as count FROM invoices' => 'Invoices',
                'SELECT COUNT(*) as count FROM bills' => 'Bills',
                'SELECT COUNT(*) as count FROM journal_entries' => 'Journal Entries',
                'SELECT COUNT(*) as count FROM chart_of_accounts' => 'GL Accounts'
            );
            
            $counts = array();
            foreach ($verification_queries as $query => $label) {
                $res = $conn->query($query);
                if ($res) {
                    $row = $res->fetch_assoc();
                    $counts[$label] = $row['count'];
                } else {
                    $counts[$label] = 'Error: ' . $conn->error;
                }
            }
            
            $result['status'] = 'success';
            $result['message'] = 'Data verification complete';
            $result['data'] = $counts;
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Verification Error: ' . $e->getMessage();
        }
    }
    elseif ($action === 'clear') {
        try {
            $tables_to_clear = array(
                'audit_log', 'adjustments', 'payments', 'disbursements',
                'invoice_line_items', 'invoices', 'bill_line_items', 'bills',
                'journal_entries', 'journal_entry_details', 'budgets',
                'asset_depreciation_schedule', 'fixed_assets'
            );
            
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            
            foreach ($tables_to_clear as $table) {
                $conn->query("TRUNCATE TABLE `$table`");
            }
            
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            
            $result['status'] = 'success';
            $result['message'] = 'Dummy data cleared successfully. All transaction data removed.';
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Clear Error: ' . $e->getMessage();
        }
    }
    
    // Return JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// If not AJAX, display UI
$page_title = 'Dummy Data Management Tool';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-import {
            background: #4CAF50;
            color: white;
        }
        
        .btn-import:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        .btn-verify {
            background: #2196F3;
            color: white;
        }
        
        .btn-verify:hover {
            background: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }
        
        .btn-backup {
            background: #FF9800;
            color: white;
        }
        
        .btn-backup:hover {
            background: #e68900;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }
        
        .btn-clear {
            background: #f44336;
            color: white;
        }
        
        .btn-clear:hover {
            background: #da190b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
        }
        
        .result-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }
        
        .result-box.show {
            display: block;
        }
        
        .result-box.success {
            background: #e8f5e9;
            border-color: #4CAF50;
            color: #2e7d32;
        }
        
        .result-box.error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        .result-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .result-message {
            font-size: 14px;
            line-height: 1.6;
        }
        
        .verification-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .verification-table th,
        .verification-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .verification-table th {
            background: #f0f0f0;
            font-weight: 600;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
        }
        
        .loading.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        <p class="subtitle">Import, verify, and manage realistic dummy financial data for testing</p>
        
        <div class="info-box">
            <strong>ℹ️ Info:</strong> This tool imports 12 months of realistic financial data including invoices, bills, GL accounts, 
            customers, vendors, and journal entries. Perfect for testing reports, workflows, and system functionality.
        </div>
        
        <div class="button-grid">
            <button class="btn btn-import" onclick="importData()">📥 Import Data</button>
            <button class="btn btn-verify" onclick="verifyData()">✓ Verify Data</button>
            <button class="btn btn-backup" onclick="backupData()">💾 Backup First</button>
            <button class="btn btn-clear" onclick="clearData()">🗑️ Clear Data</button>
        </div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Processing... Please wait</p>
        </div>
        
        <div class="result-box" id="resultBox">
            <div class="result-title" id="resultTitle">Result</div>
            <div class="result-message" id="resultMessage"></div>
            <div id="resultDetails"></div>
        </div>
    </div>

    <script>
        function showLoading(show = true) {
            document.getElementById('loading').classList.toggle('show', show);
        }
        
        function showResult(status, message, details = '') {
            const box = document.getElementById('resultBox');
            const title = document.getElementById('resultTitle');
            const msg = document.getElementById('resultMessage');
            const det = document.getElementById('resultDetails');
            
            box.className = 'result-box show ' + status;
            title.textContent = status === 'success' ? '✓ Success' : '✗ Error';
            msg.textContent = message;
            det.innerHTML = details;
        }
        
        function importData() {
            if (confirm('This will import 12 months of dummy financial data. Continue?')) {
                showLoading(true);
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=import'
                })
                .then(r => r.json())
                .then(data => {
                    showLoading(false);
                    showResult(data.status, data.message);
                    if (data.status === 'error' && data.errors.length) {
                        let errorHtml = '<p style="margin-top: 10px;"><strong>Errors:</strong></p><ul>';
                        data.errors.forEach(e => {
                            errorHtml += '<li>' + e + '</li>';
                        });
                        errorHtml += '</ul>';
                        document.getElementById('resultDetails').innerHTML = errorHtml;
                    }
                })
                .catch(e => {
                    showLoading(false);
                    showResult('error', 'Request failed: ' + e.message);
                });
            }
        }
        
        function verifyData() {
            showLoading(true);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=verify'
            })
            .then(r => r.json())
            .then(data => {
                showLoading(false);
                
                let html = '';
                if (data.data) {
                    html = '<table class="verification-table"><tr><th>Table</th><th>Record Count</th></tr>';
                    for (let [table, count] of Object.entries(data.data)) {
                        html += '<tr><td>' + table + '</td><td>' + count + '</td></tr>';
                    }
                    html += '</table>';
                }
                
                showResult(data.status, data.message, html);
            })
            .catch(e => {
                showLoading(false);
                showResult('error', 'Verification failed: ' + e.message);
            });
        }
        
        function backupData() {
            showLoading(true);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=backup'
            })
            .then(r => r.json())
            .then(data => {
                showLoading(false);
                showResult(data.status, data.message);
            })
            .catch(e => {
                showLoading(false);
                showResult('error', 'Backup failed: ' + e.message);
            });
        }
        
        function clearData() {
            if (confirm('⚠️ WARNING: This will DELETE all dummy data. This cannot be undone unless you have backups. Continue?')) {
                if (confirm('Are you REALLY sure? This will remove all imported financial data.')) {
                    showLoading(true);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=clear'
                    })
                    .then(r => r.json())
                    .then(data => {
                        showLoading(false);
                        showResult(data.status, data.message);
                    })
                    .catch(e => {
                        showLoading(false);
                        showResult('error', 'Clear failed: ' + e.message);
                    });
                }
            }
        }
    </script>
</body>
</html>
