<?php
/**
 * ATIERA Financial Management System - API Tests
 * Testing REST API endpoints and integrations
 */

class APITests extends APITestCase {
    public function testCustomerAPIEndpoints() {
        // Test GET /api/customers (list customers)
        $response = $this->makeRequest('GET', 'api/customers.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('customers', $response['body']);

        // Test POST /api/customers (create customer) - should fail without auth
        $customerData = [
            'customer_code' => 'APITEST001',
            'company_name' => 'API Test Company',
            'email' => 'api@test.com'
        ];
        $response = $this->makeRequest('POST', 'api/customers.php', $customerData);
        $this->assertResponseCode(401, $response); // Unauthorized

        // Test invalid endpoint
        $response = $this->makeRequest('GET', 'api/nonexistent.php');
        $this->assertResponseCode(404, $response);
    }

    public function testInvoiceAPIEndpoints() {
        // Test GET /api/invoices (list invoices)
        $response = $this->makeRequest('GET', 'api/invoices.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('invoices', $response['body']);

        // Test with query parameters
        $response = $this->makeRequest('GET', 'api/invoices.php?status=draft&limit=10');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);

        // Test POST /api/invoices (create invoice) - should fail without auth
        $invoiceData = [
            'invoice_number' => 'API-INV-001',
            'customer_id' => 1,
            'total_amount' => 1000.00
        ];
        $response = $this->makeRequest('POST', 'api/invoices.php', $invoiceData);
        $this->assertResponseCode(401, $response);
    }

    public function testChartOfAccountsAPI() {
        // Test GET /api/chart_of_accounts
        $response = $this->makeRequest('GET', 'api/chart_of_accounts.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('accounts', $response['body']);

        // Verify default accounts exist
        $accounts = $response['body']['accounts'];
        $this->assertNotEmpty($accounts);

        // Check for required account types
        $accountTypes = array_column($accounts, 'account_type');
        $this->assertContains('asset', $accountTypes);
        $this->assertContains('liability', $accountTypes);
        $this->assertContains('equity', $accountTypes);
        $this->assertContains('revenue', $accountTypes);
        $this->assertContains('expense', $accountTypes);
    }

    public function testJournalEntriesAPI() {
        // Test GET /api/journal_entries
        $response = $this->makeRequest('GET', 'api/journal_entries.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('entries', $response['body']);
    }

    public function testReportsAPI() {
        // Test GET /api/reports
        $response = $this->makeRequest('GET', 'api/reports.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('reports', $response['body']);
    }

    public function testVendorsAPI() {
        // Test GET /api/vendors
        $response = $this->makeRequest('GET', 'api/vendors.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('vendors', $response['body']);
    }

    public function testBillsAPI() {
        // Test GET /api/bills
        $response = $this->makeRequest('GET', 'api/bills.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('bills', $response['body']);
    }

    public function testPaymentsAPI() {
        // Test GET /api/payments
        $response = $this->makeRequest('GET', 'api/payments.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('payments', $response['body']);
    }

    public function testTasksAPI() {
        // Test GET /api/tasks
        $response = $this->makeRequest('GET', 'api/tasks.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('tasks', $response['body']);
    }

    public function testAdjustmentsAPI() {
        // Test GET /api/adjustments
        $response = $this->makeRequest('GET', 'api/adjustments.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('adjustments', $response['body']);
    }

    public function testDisbursementsAPI() {
        // Test GET /api/disbursements
        $response = $this->makeRequest('GET', 'api/disbursements.php');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
        $this->assertArrayHasKey('disbursements', $response['body']);
    }

    public function testAPIErrorHandling() {
        // Test invalid HTTP method
        $response = $this->makeRequest('PATCH', 'api/customers.php');
        $this->assertResponseCode(405, $response);

        // Test malformed JSON
        $response = $this->makeRequest('POST', 'api/customers.php', 'invalid json');
        $this->assertTrue($response['code'] >= 400);

        // Test missing required parameters
        $response = $this->makeRequest('GET', 'api/invoices.php?action=nonexistent');
        $this->assertResponseCode(400, $response);
    }

    public function testAPIRateLimiting() {
        // Test multiple rapid requests (would be rate limited in production)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->makeRequest('GET', 'api/customers.php');
            $this->assertTrue($response['code'] < 429); // Not rate limited
        }
    }

    public function testAPIResponseFormat() {
        // Test JSON response format
        $response = $this->makeRequest('GET', 'api/customers.php');

        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('success', $response['body']);

        if ($response['body']['success']) {
            $this->assertArrayHasKey('data', $response['body']);
            $this->assertArrayHasKey('meta', $response['body']);
        }
    }

    public function testAPIPagination() {
        // Test pagination parameters
        $response = $this->makeRequest('GET', 'api/customers.php?page=1&limit=10');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);

        // Check pagination metadata
        if (isset($response['body']['meta'])) {
            $meta = $response['body']['meta'];
            $this->assertArrayHasKey('page', $meta);
            $this->assertArrayHasKey('limit', $meta);
            $this->assertArrayHasKey('total', $meta);
        }
    }

    public function testAPISearchAndFiltering() {
        // Test search functionality
        $response = $this->makeRequest('GET', 'api/customers.php?search=test');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);

        // Test filtering
        $response = $this->makeRequest('GET', 'api/invoices.php?status=paid');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
    }

    public function testAPISorting() {
        // Test sorting functionality
        $response = $this->makeRequest('GET', 'api/customers.php?sort=company_name&order=asc');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);

        $response = $this->makeRequest('GET', 'api/invoices.php?sort=total_amount&order=desc');
        $this->assertResponseCode(200, $response);
        $this->assertAPISuccess($response);
    }

    public function testAPIDataValidation() {
        // Test API input validation
        $invalidData = [
            'customer_code' => '', // Required but empty
            'email' => 'invalid-email', // Invalid email format
            'credit_limit' => 'not-a-number' // Invalid number
        ];

        $response = $this->makeRequest('POST', 'api/customers.php', $invalidData);
        // Should return validation errors (would be 400 in production with auth)
        $this->assertTrue($response['code'] >= 400);
    }

    public function testAPICORSHeaders() {
        // Test CORS headers (if implemented)
        $response = $this->makeRequest('OPTIONS', 'api/customers.php');
        // CORS preflight should be handled
        $this->assertTrue(in_array($response['code'], [200, 204, 404]));
    }

    public function testAPIContentType() {
        // Test that API returns correct content type
        $response = $this->makeRequest('GET', 'api/customers.php');

        // Check if response is valid JSON
        $this->assertNotNull($response['body']);
        $this->assertIsArray($response['body']);

        // Test JSON parsing
        $rawJson = json_encode($response['body']);
        $this->assertNotFalse($rawJson);
    }

    public function testAPIEndpointDiscovery() {
        // Test API documentation/discovery endpoint (if exists)
        $response = $this->makeRequest('GET', 'api/');
        // May return 404 if not implemented, but shouldn't crash
        $this->assertTrue($response['code'] < 500);
    }

    public function testAPIVersioning() {
        // Test API versioning (if implemented)
        $response = $this->makeRequest('GET', 'api/v1/customers.php');
        // Should handle versioned endpoints gracefully
        $this->assertTrue($response['code'] < 500);
    }

    public function testAPICompression() {
        // Test gzip compression (if enabled)
        $response = $this->makeRequest('GET', 'api/customers.php', null, [
            'Accept-Encoding: gzip, deflate'
        ]);
        // Should still work with compression headers
        $this->assertTrue($response['code'] < 500);
    }

    public function testAPIHTTPSRedirect() {
        // Test HTTPS enforcement (if implemented)
        // This would typically redirect HTTP to HTTPS in production
        $response = $this->makeRequest('GET', 'api/customers.php');
        $this->assertTrue($response['code'] < 400); // Should work in test environment
    }
}
