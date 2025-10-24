-- Add missing columns to payments_received table to support supplier refunds/credits
-- This migration adds vendor_id and bill_id columns to support collections from vendors

USE atiera_finance;

-- Add vendor_id column to payments_received table
ALTER TABLE payments_received
ADD COLUMN vendor_id INT NULL AFTER customer_id,
ADD CONSTRAINT fk_payments_received_vendor
FOREIGN KEY (vendor_id) REFERENCES vendors(id);

-- Add bill_id column to payments_received table
ALTER TABLE payments_received
ADD COLUMN bill_id INT NULL AFTER invoice_id,
ADD CONSTRAINT fk_payments_received_bill
FOREIGN KEY (bill_id) REFERENCES bills(id);

-- Optional: Add indexes for performance
CREATE INDEX idx_payments_received_vendor_id ON payments_received(vendor_id);
CREATE INDEX idx_payments_received_bill_id ON payments_received(bill_id);

-- Check if the table structure is now correct
DESCRIBE payments_received;
