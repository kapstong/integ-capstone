<?php
/**
 * ATIERA Financial Management System - PDF Report Generator
 * Generates professional PDF reports using TCPDF
 */

require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';

class PDFGenerator {
    private static $instance = null;
    private $db;
    private $pdf;

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initializePDF();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializePDF() {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $this->pdf->SetCreator('ATIERA Financial Management System');
        $this->pdf->SetAuthor('ATIERA Finance');
        $this->pdf->SetTitle('Financial Report');
        $this->pdf->SetSubject('Generated Financial Document');

        // Set default header data
        $this->pdf->SetHeaderData('', 0, 'ATIERA FINANCIAL MANAGEMENT SYSTEM', 'Generated on ' . date('M j, Y H:i'));

        // Set header and footer fonts
        $this->pdf->setHeaderFont(['helvetica', '', 10]);
        $this->pdf->setFooterFont(['helvetica', '', 8]);

        // Set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $this->pdf->SetMargins(15, 25, 15);
        $this->pdf->SetHeaderMargin(10);
        $this->pdf->SetFooterMargin(10);

        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, 15);

        // Set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set font
        $this->pdf->SetFont('helvetica', '', 10);
    }

    /**
     * Generate invoice PDF
     */
    public function generateInvoicePDF($invoiceId) {
        try {
            // Get invoice data
            $stmt = $this->db->prepare("
                SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address,
                       u.full_name as created_by_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('Invoice not found');
            }

            // Get invoice items
            $stmt = $this->db->prepare("
                SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC
            ");
            $stmt->execute([$invoiceId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create new PDF document
            $this->pdf->AddPage();

            // Title
            $this->pdf->SetFont('helvetica', 'B', 16);
            $this->pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
            $this->pdf->Ln(5);

            // Invoice details
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, 'Invoice Details', 0, 1, 'L');
            $this->pdf->SetFont('helvetica', '', 10);

            $this->pdf->Cell(50, 6, 'Invoice Number:', 0, 0);
            $this->pdf->Cell(0, 6, $invoice['invoice_number'], 0, 1);

            $this->pdf->Cell(50, 6, 'Invoice Date:', 0, 0);
            $this->pdf->Cell(0, 6, date('M j, Y', strtotime($invoice['invoice_date'])), 0, 1);

            $this->pdf->Cell(50, 6, 'Due Date:', 0, 0);
            $this->pdf->Cell(0, 6, date('M j, Y', strtotime($invoice['due_date'])), 0, 1);

            $this->pdf->Cell(50, 6, 'Status:', 0, 0);
            $this->pdf->Cell(0, 6, ucfirst($invoice['status']), 0, 1);

            $this->pdf->Ln(5);

            // Billing information
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(95, 8, 'Bill To:', 0, 0, 'L');
            $this->pdf->Cell(95, 8, 'Bill From:', 0, 1, 'L');

            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->Cell(95, 6, $invoice['company_name'], 0, 0);
            $this->pdf->Cell(95, 6, 'ATIERA Finance', 0, 1);

            $this->pdf->Cell(95, 6, $invoice['contact_person'], 0, 0);
            $this->pdf->Cell(95, 6, 'Financial Management System', 0, 1);

            $this->pdf->Cell(95, 6, $invoice['email'], 0, 0);
            $this->pdf->Cell(95, 6, 'support@atiera.com', 0, 1);

            $this->pdf->Cell(95, 6, $invoice['phone'], 0, 0);
            $this->pdf->Cell(95, 6, '(02) 123-4567', 0, 1);

            // Address
            $this->pdf->MultiCell(95, 6, $invoice['address'], 0, 'L', false, 0);
            $this->pdf->MultiCell(95, 6, '123 Business District\nManila, Philippines 1000', 0, 'L', false, 1);

            $this->pdf->Ln(10);

            // Items table
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->SetFillColor(240, 240, 240);

            // Table header
            $this->pdf->Cell(80, 8, 'Description', 1, 0, 'L', true);
            $this->pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
            $this->pdf->Cell(30, 8, 'Unit Price', 1, 0, 'R', true);
            $this->pdf->Cell(30, 8, 'Total', 1, 1, 'R', true);

            // Table body
            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->SetFillColor(255, 255, 255);

            foreach ($items as $item) {
                $this->pdf->MultiCell(80, 6, $item['description'], 1, 'L', false, 0);
                $this->pdf->Cell(20, 6, number_format($item['quantity'], 2), 1, 0, 'C');
                $this->pdf->Cell(30, 6, '₱' . number_format($item['unit_price'], 2), 1, 0, 'R');
                $this->pdf->Cell(30, 6, '₱' . number_format($item['line_total'], 2), 1, 1, 'R');
            }

            // Totals
            $this->pdf->Ln(5);
            $this->pdf->SetFont('helvetica', 'B', 10);

            $this->pdf->Cell(130, 8, 'Subtotal:', 0, 0, 'R');
            $this->pdf->Cell(30, 8, '₱' . number_format($invoice['subtotal'], 2), 0, 1, 'R');

            if ($invoice['tax_amount'] > 0) {
                $this->pdf->Cell(130, 8, 'Tax (' . $invoice['tax_rate'] . '%):', 0, 0, 'R');
                $this->pdf->Cell(30, 8, '₱' . number_format($invoice['tax_amount'], 2), 0, 1, 'R');
            }

            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(130, 10, 'Total Amount:', 0, 0, 'R');
            $this->pdf->Cell(30, 10, '₱' . number_format($invoice['total_amount'], 2), 1, 1, 'R');

            // Payment info
            $this->pdf->Ln(10);
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->Cell(0, 8, 'Payment Information', 0, 1, 'L');
            $this->pdf->SetFont('helvetica', '', 9);

            $this->pdf->MultiCell(0, 5, 'Please make payment to: ATIERA Finance\nBank: Sample Bank\nAccount: 123-456-789\nPayment due within 30 days.', 0, 'L');

            // Notes
            if ($invoice['notes']) {
                $this->pdf->Ln(5);
                $this->pdf->SetFont('helvetica', 'B', 10);
                $this->pdf->Cell(0, 8, 'Notes', 0, 1, 'L');
                $this->pdf->SetFont('helvetica', '', 9);
                $this->pdf->MultiCell(0, 5, $invoice['notes'], 0, 'L');
            }

            // Generate filename
            $filename = 'invoice_' . $invoice['invoice_number'] . '_' . date('Y-m-d') . '.pdf';

            // Output PDF
            return $this->outputPDF($filename);

        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            throw new Exception('Failed to generate invoice PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate financial report PDF
     */
    public function generateFinancialReportPDF($startDate, $endDate, $reportType = 'summary') {
        try {
            $this->pdf->AddPage();

            // Title
            $this->pdf->SetFont('helvetica', 'B', 16);
            $this->pdf->Cell(0, 10, 'FINANCIAL REPORT', 0, 1, 'C');
            $this->pdf->SetFont('helvetica', '', 12);
            $this->pdf->Cell(0, 8, 'Period: ' . date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate)), 0, 1, 'C');
            $this->pdf->Ln(10);

            // Get financial data
            $financialData = $this->getFinancialData($startDate, $endDate);

            // Revenue section
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, 'Revenue Summary', 0, 1, 'L');
            $this->pdf->SetFont('helvetica', '', 10);

            $this->pdf->Cell(80, 6, 'Total Invoiced:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['total_invoiced'], 2), 0, 1);

            $this->pdf->Cell(80, 6, 'Total Payments Received:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['total_payments'], 2), 0, 1);

            $this->pdf->Cell(80, 6, 'Outstanding Receivables:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['outstanding_receivables'], 2), 0, 1);

            $this->pdf->Ln(5);

            // Expense section
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, 'Expense Summary', 0, 1, 'L');
            $this->pdf->SetFont('helvetica', '', 10);

            $this->pdf->Cell(80, 6, 'Total Billed:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['total_billed'], 2), 0, 1);

            $this->pdf->Cell(80, 6, 'Total Payments Made:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['total_disbursements'], 2), 0, 1);

            $this->pdf->Cell(80, 6, 'Outstanding Payables:', 0, 0);
            $this->pdf->Cell(0, 6, '₱' . number_format($financialData['outstanding_payables'], 2), 0, 1);

            $this->pdf->Ln(10);

            // Net position
            $this->pdf->SetFont('helvetica', 'B', 14);
            $netPosition = $financialData['total_payments'] - $financialData['total_disbursements'];
            $this->pdf->Cell(0, 10, 'Net Financial Position: ₱' . number_format($netPosition, 2), 1, 1, 'C');

            $filename = 'financial_report_' . date('Y-m-d', strtotime($startDate)) . '_to_' . date('Y-m-d', strtotime($endDate)) . '.pdf';
            return $this->outputPDF($filename);

        } catch (Exception $e) {
            error_log("Financial report PDF generation error: " . $e->getMessage());
            throw new Exception('Failed to generate financial report PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate customer statement PDF
     */
    public function generateCustomerStatementPDF($customerId, $startDate = null, $endDate = null) {
        try {
            // Get customer data
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                throw new Exception('Customer not found');
            }

            $this->pdf->AddPage();

            // Title
            $this->pdf->SetFont('helvetica', 'B', 16);
            $this->pdf->Cell(0, 10, 'CUSTOMER STATEMENT', 0, 1, 'C');
            $this->pdf->Ln(5);

            // Customer info
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, $customer['company_name'], 0, 1, 'L');
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->Cell(0, 6, $customer['contact_person'], 0, 1);
            $this->pdf->Cell(0, 6, $customer['address'], 0, 1);
            $this->pdf->Ln(10);

            // Get customer transactions
            $transactions = $this->getCustomerTransactions($customerId, $startDate, $endDate);

            // Transactions table
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->SetFillColor(240, 240, 240);

            $this->pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Reference', 1, 0, 'C', true);
            $this->pdf->Cell(60, 8, 'Description', 1, 0, 'C', true);
            $this->pdf->Cell(30, 8, 'Debit', 1, 0, 'C', true);
            $this->pdf->Cell(30, 8, 'Credit', 1, 1, 'C', true);

            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->SetFillColor(255, 255, 255);

            $balance = 0;
            foreach ($transactions as $transaction) {
                $debit = $transaction['type'] === 'invoice' ? $transaction['amount'] : 0;
                $credit = $transaction['type'] === 'payment' ? $transaction['amount'] : 0;
                $balance += $credit - $debit;

                $this->pdf->Cell(30, 6, date('M j, Y', strtotime($transaction['date'])), 1, 0, 'C');
                $this->pdf->Cell(40, 6, $transaction['reference'], 1, 0, 'L');
                $this->pdf->Cell(60, 6, $transaction['description'], 1, 0, 'L');
                $this->pdf->Cell(30, 6, $debit > 0 ? '₱' . number_format($debit, 2) : '', 1, 0, 'R');
                $this->pdf->Cell(30, 6, $credit > 0 ? '₱' . number_format($credit, 2) : '', 1, 1, 'R');
            }

            // Balance summary
            $this->pdf->Ln(5);
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(160, 10, 'Current Balance:', 0, 0, 'R');
            $this->pdf->Cell(30, 10, '₱' . number_format($balance, 2), 1, 1, 'R');

            $filename = 'customer_statement_' . $customer['customer_code'] . '_' . date('Y-m-d') . '.pdf';
            return $this->outputPDF($filename);

        } catch (Exception $e) {
            error_log("Customer statement PDF generation error: " . $e->getMessage());
            throw new Exception('Failed to generate customer statement PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get financial data for reports
     */
    private function getFinancialData($startDate, $endDate) {
        // Get total invoiced
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM invoices
            WHERE invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalInvoiced = $stmt->fetch()['total'];

        // Get total payments received
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM payments_received
            WHERE payment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalPayments = $stmt->fetch()['total'];

        // Get outstanding receivables
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(balance), 0) as total
            FROM invoices
            WHERE status IN ('sent', 'overdue')
        ");
        $outstandingReceivables = $stmt->fetch()['total'];

        // Get total billed
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM bills
            WHERE bill_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalBilled = $stmt->fetch()['total'];

        // Get total disbursements
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM payments_made
            WHERE payment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalDisbursements = $stmt->fetch()['total'];

        // Get outstanding payables
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(balance), 0) as total
            FROM bills
            WHERE status IN ('approved', 'overdue')
        ");
        $outstandingPayables = $stmt->fetch()['total'];

        return [
            'total_invoiced' => $totalInvoiced,
            'total_payments' => $totalPayments,
            'outstanding_receivables' => $outstandingReceivables,
            'total_billed' => $totalBilled,
            'total_disbursements' => $totalDisbursements,
            'outstanding_payables' => $outstandingPayables
        ];
    }

    /**
     * Get customer transactions
     */
    private function getCustomerTransactions($customerId, $startDate = null, $endDate = null) {
        $params = [$customerId];
        $dateCondition = '';

        if ($startDate && $endDate) {
            $dateCondition = " AND date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                'invoice' as type,
                invoice_date as date,
                invoice_number as reference,
                CONCAT('Invoice - ', invoice_number) as description,
                total_amount as amount
            FROM invoices
            WHERE customer_id = ? {$dateCondition}
            UNION ALL
            SELECT
                'payment' as type,
                payment_date as date,
                payment_number as reference,
                CONCAT('Payment - ', payment_number) as description,
                amount
            FROM payments_received
            WHERE customer_id = ? {$dateCondition}
            ORDER BY date ASC
        ");

        $stmt->execute(array_merge($params, [$customerId]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Output PDF to browser or file
     */
    private function outputPDF($filename) {
        // Close and output PDF document
        $this->pdf->Output($filename, 'D');
        exit;
    }

    /**
     * Generate PDF content as string (for email attachments)
     */
    public function generatePDFContent($filename) {
        return $this->pdf->Output($filename, 'S');
    }

    /**
     * Reset PDF instance for new document
     */
    public function resetPDF() {
        $this->pdf = null;
        $this->initializePDF();
    }
}
?>
