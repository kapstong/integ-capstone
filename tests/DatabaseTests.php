<?php
/**
 * ATIERA Financial Management System - Database Tests
 * Testing database operations and data integrity
 */

class DatabaseTests extends DatabaseTestCase {
    public function testDatabaseConnection() {
        $this->assertNotNull($this->db);
        $this->assertInstanceOf('PDO', $this->db);
    }

    public function testUserTableOperations() {
        // Test inserting a user
        $userId = $this->insertTestData('users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'role' => 'staff'
        ]);

        $this->assertNotNull($userId);
        $this->assertTrue($userId > 0);

        // Test retrieving the user
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($user);
        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test User', $user['full_name']);
    }

    public function testCustomerTableOperations() {
        // Test inserting a customer
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'TEST001',
            'company_name' => 'Test Company',
            'contact_person' => 'John Doe',
            'email' => 'john@testcompany.com',
            'phone' => '123-456-7890',
            'credit_limit' => 50000.00
        ]);

        $this->assertNotNull($customerId);

        // Test retrieving the customer
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($customer);
        $this->assertEquals('TEST001', $customer['customer_code']);
        $this->assertEquals('Test Company', $customer['company_name']);
        $this->assertEquals(50000.00, $customer['credit_limit']);
    }

    public function testInvoiceCreation() {
        // First create a customer
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'INVTEST001',
            'company_name' => 'Invoice Test Company',
            'email' => 'invoice@test.com'
        ]);

        // Create an invoice
        $invoiceId = $this->insertTestData('invoices', [
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customerId,
            'invoice_date' => '2025-01-15',
            'due_date' => '2025-02-15',
            'subtotal' => 1000.00,
            'tax_rate' => 12.00,
            'tax_amount' => 120.00,
            'total_amount' => 1120.00,
            'status' => 'draft'
        ]);

        $this->assertNotNull($invoiceId);

        // Test invoice retrieval with customer join
        $stmt = $this->db->prepare("
            SELECT i.*, c.company_name
            FROM invoices i
            INNER JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($invoice);
        $this->assertEquals('INV-TEST-001', $invoice['invoice_number']);
        $this->assertEquals('Invoice Test Company', $invoice['company_name']);
        $this->assertEquals(1120.00, $invoice['total_amount']);
    }

    public function testInvoiceItemsOperations() {
        // Create customer and invoice first
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'ITEMTEST001',
            'company_name' => 'Item Test Company'
        ]);

        $invoiceId = $this->insertTestData('invoices', [
            'invoice_number' => 'INV-ITEM-001',
            'customer_id' => $customerId,
            'total_amount' => 500.00
        ]);

        // Create chart of accounts entry
        $accountId = $this->insertTestData('chart_of_accounts', [
            'account_code' => 'TEST4001',
            'account_name' => 'Test Revenue Account',
            'account_type' => 'revenue'
        ]);

        // Add invoice items
        $item1Id = $this->insertTestData('invoice_items', [
            'invoice_id' => $invoiceId,
            'description' => 'Service 1',
            'quantity' => 2,
            'unit_price' => 100.00,
            'line_total' => 200.00,
            'account_id' => $accountId
        ]);

        $item2Id = $this->insertTestData('invoice_items', [
            'invoice_id' => $invoiceId,
            'description' => 'Service 2',
            'quantity' => 1,
            'unit_price' => 300.00,
            'line_total' => 300.00,
            'account_id' => $accountId
        ]);

        // Test calculating total from items
        $stmt = $this->db->prepare("
            SELECT SUM(line_total) as calculated_total
            FROM invoice_items
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(500.00, $result['calculated_total']);
    }

    public function testPaymentProcessing() {
        // Create customer and invoice
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'PAYTEST001',
            'company_name' => 'Payment Test Company'
        ]);

        $invoiceId = $this->insertTestData('invoices', [
            'invoice_number' => 'INV-PAY-001',
            'customer_id' => $customerId,
            'total_amount' => 1000.00,
            'balance' => 1000.00
        ]);

        // Create payment
        $paymentId = $this->insertTestData('payments_received', [
            'payment_number' => 'PAY-TEST-001',
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'payment_date' => '2025-01-20',
            'amount' => 500.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'REF123'
        ]);

        $this->assertNotNull($paymentId);

        // Test payment retrieval
        $stmt = $this->db->prepare("SELECT * FROM payments_received WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($payment);
        $this->assertEquals(500.00, $payment['amount']);
        $this->assertEquals('bank_transfer', $payment['payment_method']);
    }

    public function testJournalEntryBalance() {
        // Create journal entry
        $entryId = $this->insertTestData('journal_entries', [
            'entry_number' => 'JE-TEST-001',
            'entry_date' => '2025-01-15',
            'description' => 'Test journal entry',
            'status' => 'draft'
        ]);

        // Create chart of accounts entries
        $cashAccountId = $this->insertTestData('chart_of_accounts', [
            'account_code' => 'TEST1001',
            'account_name' => 'Test Cash',
            'account_type' => 'asset'
        ]);

        $revenueAccountId = $this->insertTestData('chart_of_accounts', [
            'account_code' => 'TEST4001',
            'account_name' => 'Test Revenue',
            'account_type' => 'revenue'
        ]);

        // Add journal entry lines (balanced)
        $this->insertTestData('journal_entry_lines', [
            'journal_entry_id' => $entryId,
            'account_id' => $cashAccountId,
            'debit' => 1000.00,
            'credit' => 0.00
        ]);

        $this->insertTestData('journal_entry_lines', [
            'journal_entry_id' => $entryId,
            'account_id' => $revenueAccountId,
            'debit' => 0.00,
            'credit' => 1000.00
        ]);

        // Test balance calculation
        $stmt = $this->db->prepare("
            SELECT
                SUM(debit) as total_debit,
                SUM(credit) as total_credit
            FROM journal_entry_lines
            WHERE journal_entry_id = ?
        ");
        $stmt->execute([$entryId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1000.00, $totals['total_debit']);
        $this->assertEquals(1000.00, $totals['total_credit']);
        $this->assertEquals(0, $totals['total_debit'] - $totals['total_credit']);
    }

    public function testTransactionIsolation() {
        // Test that transactions are properly isolated
        $this->cleanTable('customers');

        // Insert initial data
        $count1 = $this->db->query("SELECT COUNT(*) as count FROM customers")->fetch()['count'];

        // Start a transaction and insert data
        $this->db->beginTransaction();
        $this->insertTestData('customers', [
            'customer_code' => 'ISOLATION001',
            'company_name' => 'Isolation Test'
        ]);

        // Check count within transaction
        $count2 = $this->db->query("SELECT COUNT(*) as count FROM customers")->fetch()['count'];
        $this->assertEquals($count1 + 1, $count2);

        // Rollback transaction
        $this->db->rollBack();

        // Check count after rollback
        $count3 = $this->db->query("SELECT COUNT(*) as count FROM customers")->fetch()['count'];
        $this->assertEquals($count1, $count3);
    }

    public function testForeignKeyConstraints() {
        // Test that foreign key constraints work
        $this->expectException('PDOException');

        // Try to insert invoice with non-existent customer
        $this->insertTestData('invoices', [
            'invoice_number' => 'FK-TEST-001',
            'customer_id' => 99999, // Non-existent customer
            'total_amount' => 100.00
        ]);
    }

    public function testUniqueConstraints() {
        // Test unique constraints
        $this->insertTestData('customers', [
            'customer_code' => 'UNIQUE001',
            'company_name' => 'Unique Test Company'
        ]);

        // Try to insert duplicate customer code
        try {
            $this->insertTestData('customers', [
                'customer_code' => 'UNIQUE001', // Duplicate
                'company_name' => 'Another Company'
            ]);
            $this->fail('Expected unique constraint violation');
        } catch (PDOException $e) {
            // Expected - unique constraint violation
            $this->assertStringContains('Duplicate entry', $e->getMessage());
        }
    }

    public function testDataTypes() {
        // Test various data types
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'TYPES001',
            'company_name' => 'Data Types Test',
            'credit_limit' => 12345.67,
            'status' => 'active'
        ]);

        // Retrieve and check data types
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsString($customer['customer_code']);
        $this->assertIsString($customer['company_name']);
        $this->assertIsFloat($customer['credit_limit']);
        $this->assertEquals(12345.67, $customer['credit_limit']);
        $this->assertIsString($customer['status']);
        $this->assertEquals('active', $customer['status']);
    }

    public function testIndexingPerformance() {
        // Create test data
        $this->cleanTable('customers');

        for ($i = 1; $i <= 100; $i++) {
            $this->insertTestData('customers', [
                'customer_code' => sprintf('PERF%03d', $i),
                'company_name' => "Performance Test Company $i",
                'email' => "test$i@example.com"
            ]);
        }

        // Test indexed query performance
        $start = microtime(true);
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE customer_code = ?");
        $stmt->execute(['PERF050']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $end = microtime(true);

        $this->assertNotNull($result);
        $this->assertEquals('PERF050', $result['customer_code']);

        // Query should complete in reasonable time (< 0.1 seconds)
        $this->assertTrue(($end - $start) < 0.1, "Query took too long: " . ($end - $start) . " seconds");
    }

    public function testComplexQueries() {
        // Create test data for complex queries
        $customerId = $this->insertTestData('customers', [
            'customer_code' => 'COMPLEX001',
            'company_name' => 'Complex Query Test'
        ]);

        $invoiceId = $this->insertTestData('invoices', [
            'invoice_number' => 'INV-COMPLEX-001',
            'customer_id' => $customerId,
            'total_amount' => 2000.00,
            'status' => 'paid'
        ]);

        // Test complex join query
        $stmt = $this->db->prepare("
            SELECT
                i.invoice_number,
                i.total_amount,
                i.status,
                c.company_name,
                c.customer_code
            FROM invoices i
            INNER JOIN customers c ON i.customer_id = c.id
            WHERE i.status = ? AND i.total_amount > ?
            ORDER BY i.total_amount DESC
        ");
        $stmt->execute(['paid', 1000.00]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($results);
        $this->assertEquals('INV-COMPLEX-001', $results[0]['invoice_number']);
        $this->assertEquals('Complex Query Test', $results[0]['company_name']);
        $this->assertEquals(2000.00, $results[0]['total_amount']);
    }
}
