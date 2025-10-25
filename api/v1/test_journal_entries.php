<?php
/**
 * Simple Test Script for Journal Entries API
 * Run this file in your browser or via CLI to test the API endpoints
 */

// Configuration
$BASE_URL = 'http://localhost/integ-capstone/api/v1/journal_entries.php';
$API_KEY = 'YOUR_API_KEY_HERE'; // Replace with your actual API key

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries API Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            background-color: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }
        .test-section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .endpoint {
            background-color: #e9ecef;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
            font-family: monospace;
        }
        .method {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .get { background-color: #28a745; }
        .post { background-color: #007bff; }
        .put { background-color: #ffc107; color: #333; }
        .delete { background-color: #dc3545; }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .response {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-top: 10px;
            border-radius: 5px;
            max-height: 400px;
            overflow: auto;
        }
        .success {
            border-left: 4px solid #28a745;
        }
        .error {
            border-left: 4px solid #dc3545;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #856404;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <h1>Journal Entries API Test Suite</h1>

    <?php if ($API_KEY === 'YOUR_API_KEY_HERE'): ?>
        <div class="warning">
            <strong>Warning!</strong> Please update the $API_KEY variable in this file with your actual API key.
            <br><br>
            To get an API key:
            <ol>
                <li>Go to <a href="../../admin/api_clients.php">Admin > API Clients</a></li>
                <li>Create a new API client</li>
                <li>Copy the generated API key</li>
                <li>Replace 'YOUR_API_KEY_HERE' in this file</li>
            </ol>
        </div>
    <?php endif; ?>

    <div class="info">
        <strong>Base URL:</strong> <?php echo htmlspecialchars($BASE_URL); ?>
        <br>
        <strong>API Key Status:</strong> <?php echo $API_KEY !== 'YOUR_API_KEY_HERE' ? '✓ Configured' : '✗ Not Configured'; ?>
    </div>

    <!-- GET Tests -->
    <div class="test-section">
        <h2>GET Requests - Retrieve Journal Entries</h2>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get All Journal Entries (Paginated)</strong>
            <button onclick="testGetAllEntries()">Test</button>
        </div>
        <div id="response-get-all" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get Journal Entries with Lines Included</strong>
            <button onclick="testGetEntriesWithLines()">Test</button>
        </div>
        <div id="response-get-lines" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get Posted Journal Entries Only</strong>
            <button onclick="testGetPostedEntries()">Test</button>
        </div>
        <div id="response-get-posted" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get Journal Entries Summary</strong>
            <button onclick="testGetSummary()">Test</button>
        </div>
        <div id="response-get-summary" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get Single Journal Entry by ID</strong>
            <input type="number" id="entry-id-get" placeholder="Entry ID" style="padding: 8px; margin: 0 10px;">
            <button onclick="testGetSingleEntry()">Test</button>
        </div>
        <div id="response-get-single" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>Get Journal Entry by Reference Number</strong>
            <input type="text" id="entry-ref-get" placeholder="e.g., JE-2025-0001" style="padding: 8px; margin: 0 10px;">
            <button onclick="testGetByReference()">Test</button>
        </div>
        <div id="response-get-ref" class="response" style="display:none;"></div>
    </div>

    <!-- POST Tests -->
    <div class="test-section">
        <h2>POST Requests - Create Journal Entries</h2>

        <div class="endpoint">
            <span class="method post">POST</span>
            <strong>Create Simple Journal Entry</strong>
            <button onclick="testCreateSimpleEntry()">Test</button>
        </div>
        <div id="response-create-simple" class="response" style="display:none;"></div>

        <div class="endpoint">
            <span class="method post">POST</span>
            <strong>Create Multi-Line Journal Entry</strong>
            <button onclick="testCreateComplexEntry()">Test</button>
        </div>
        <div id="response-create-complex" class="response" style="display:none;"></div>
    </div>

    <!-- PUT Tests -->
    <div class="test-section">
        <h2>PUT Requests - Update Journal Entries</h2>

        <div class="endpoint">
            <span class="method put">PUT</span>
            <strong>Update Journal Entry</strong>
            <input type="number" id="entry-id-update" placeholder="Entry ID" style="padding: 8px; margin: 0 10px;">
            <button onclick="testUpdateEntry()">Test</button>
        </div>
        <div id="response-update" class="response" style="display:none;"></div>
    </div>

    <!-- DELETE Tests -->
    <div class="test-section">
        <h2>DELETE Requests - Delete Journal Entries</h2>

        <div class="endpoint">
            <span class="method delete">DELETE</span>
            <strong>Delete Journal Entry</strong>
            <input type="number" id="entry-id-delete" placeholder="Entry ID" style="padding: 8px; margin: 0 10px;">
            <button onclick="testDeleteEntry()">Test</button>
        </div>
        <div id="response-delete" class="response" style="display:none;"></div>
    </div>

    <script>
        const BASE_URL = '<?php echo $BASE_URL; ?>';
        const API_KEY = '<?php echo $API_KEY; ?>';

        // Helper function to make API requests
        async function makeRequest(method, url, body = null) {
            const options = {
                method: method,
                headers: {
                    'Authorization': 'Bearer ' + API_KEY,
                    'Content-Type': 'application/json'
                }
            };

            if (body) {
                options.body = JSON.stringify(body);
            }

            try {
                const response = await fetch(url, options);
                const data = await response.json();
                return {
                    status: response.status,
                    data: data
                };
            } catch (error) {
                return {
                    status: 0,
                    data: { error: error.message }
                };
            }
        }

        // Display response
        function displayResponse(elementId, response) {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.className = 'response ' + (response.status === 200 ? 'success' : 'error');
            element.innerHTML = '<strong>Status: ' + response.status + '</strong><br><pre>' +
                JSON.stringify(response.data, null, 2) + '</pre>';
        }

        // Test functions
        async function testGetAllEntries() {
            const response = await makeRequest('GET', BASE_URL + '?limit=10&offset=0');
            displayResponse('response-get-all', response);
        }

        async function testGetEntriesWithLines() {
            const response = await makeRequest('GET', BASE_URL + '?limit=5&include_lines=true');
            displayResponse('response-get-lines', response);
        }

        async function testGetPostedEntries() {
            const response = await makeRequest('GET', BASE_URL + '?status=posted&limit=10');
            displayResponse('response-get-posted', response);
        }

        async function testGetSummary() {
            const response = await makeRequest('GET', BASE_URL + '?action=summary');
            displayResponse('response-get-summary', response);
        }

        async function testGetSingleEntry() {
            const id = document.getElementById('entry-id-get').value;
            if (!id) {
                alert('Please enter an Entry ID');
                return;
            }
            const response = await makeRequest('GET', BASE_URL + '?id=' + id);
            displayResponse('response-get-single', response);
        }

        async function testGetByReference() {
            const ref = document.getElementById('entry-ref-get').value;
            if (!ref) {
                alert('Please enter a Reference Number');
                return;
            }
            const response = await makeRequest('GET', BASE_URL + '?reference=' + encodeURIComponent(ref));
            displayResponse('response-get-ref', response);
        }

        async function testCreateSimpleEntry() {
            const body = {
                entry_date: new Date().toISOString().split('T')[0],
                description: 'Test entry created via API test script',
                status: 'draft',
                lines: [
                    {
                        account_id: 10,
                        debit: 1000.00,
                        credit: 0.00,
                        description: 'Test debit line'
                    },
                    {
                        account_id: 15,
                        debit: 0.00,
                        credit: 1000.00,
                        description: 'Test credit line'
                    }
                ]
            };
            const response = await makeRequest('POST', BASE_URL, body);
            displayResponse('response-create-simple', response);
        }

        async function testCreateComplexEntry() {
            const body = {
                entry_date: new Date().toISOString().split('T')[0],
                description: 'Complex test entry with multiple lines',
                status: 'draft',
                lines: [
                    {
                        account_id: 25,
                        debit: 1500.00,
                        credit: 0.00,
                        description: 'Expense account 1'
                    },
                    {
                        account_id: 26,
                        debit: 800.00,
                        credit: 0.00,
                        description: 'Expense account 2'
                    },
                    {
                        account_id: 27,
                        debit: 700.00,
                        credit: 0.00,
                        description: 'Expense account 3'
                    },
                    {
                        account_id: 10,
                        debit: 0.00,
                        credit: 3000.00,
                        description: 'Cash payment'
                    }
                ]
            };
            const response = await makeRequest('POST', BASE_URL, body);
            displayResponse('response-create-complex', response);
        }

        async function testUpdateEntry() {
            const id = document.getElementById('entry-id-update').value;
            if (!id) {
                alert('Please enter an Entry ID');
                return;
            }
            const body = {
                description: 'Updated description via API test - ' + new Date().toLocaleString(),
                status: 'draft'
            };
            const response = await makeRequest('PUT', BASE_URL + '?id=' + id, body);
            displayResponse('response-update', response);
        }

        async function testDeleteEntry() {
            const id = document.getElementById('entry-id-delete').value;
            if (!id) {
                alert('Please enter an Entry ID');
                return;
            }
            if (!confirm('Are you sure you want to delete entry #' + id + '?')) {
                return;
            }
            const response = await makeRequest('DELETE', BASE_URL + '?id=' + id);
            displayResponse('response-delete', response);
        }
    </script>
</body>
</html>
