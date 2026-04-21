START TRANSACTION;

SET @billing_id := 363;
SET @user_id := 7;
SET @family_code := 'SSP-0361';

UPDATE family_billings
SET paid_amount = fee_amount,
    status = 'paid',
    updated_at = NOW()
WHERE id = @billing_id;

SET @order_main := CONCAT('MOCK-YURAN-', DATE_FORMAT(NOW(), '%y%m%d%H%i%s'), '-', LPAD(FLOOR(RAND()*9000)+1000, 4, '0'));
SET @bill_main := CONCAT('MOCKBILL', LPAD(FLOOR(RAND()*900000)+100000, 6, '0'));

INSERT INTO family_payment_transactions (
    family_billing_id, user_id, payment_provider, external_order_id, receipt_uuid,
    provider_bill_code, provider_ref_no, provider_invoice_no, amount, fee_amount_paid, donation_amount,
    payer_name, payer_email, payer_phone, donation_intention, status, return_status, status_reason,
    paid_at, raw_return, raw_callback, created_at, updated_at
)
SELECT
    @billing_id, @user_id, 'toyyibpay', @order_main, UUID(),
    @bill_main, CONCAT('REF', FLOOR(RAND()*900000)+100000), CONCAT('INV', FLOOR(RAND()*900000)+100000),
    100.00, 100.00, 0.00,
    'Parent SSP-0361', 'parent.ssp0361@example.com', '0146364001', NULL,
    'success', 'successful', 'Mock initial yuran payment',
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    JSON_OBJECT('donation', 0, 'outstanding_at_checkout', 100, 'tester_mode', FALSE),
    JSON_OBJECT('status', '1', 'reason', 'Mock callback paid'),
    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
WHERE NOT EXISTS (
    SELECT 1
    FROM family_payment_transactions
    WHERE family_billing_id = @billing_id
      AND status = 'success'
      AND fee_amount_paid >= 100
);

SET @order_extra := CONCAT('MOCK-SUMBANGAN-', DATE_FORMAT(NOW(), '%y%m%d%H%i%s'), '-', LPAD(FLOOR(RAND()*9000)+1000, 4, '0'));
SET @bill_extra := CONCAT('MOCKBILL', LPAD(FLOOR(RAND()*900000)+100000, 6, '0'));

INSERT INTO family_payment_transactions (
    family_billing_id, user_id, payment_provider, external_order_id, receipt_uuid,
    provider_bill_code, provider_ref_no, provider_invoice_no, amount, fee_amount_paid, donation_amount,
    payer_name, payer_email, payer_phone, donation_intention, status, return_status, status_reason,
    paid_at, raw_return, raw_callback, created_at, updated_at
)
SELECT
    @billing_id, @user_id, 'toyyibpay', @order_extra, UUID(),
    @bill_extra, CONCAT('REF', FLOOR(RAND()*900000)+100000), CONCAT('INV', FLOOR(RAND()*900000)+100000),
    60.00, 0.00, 60.00,
    'Parent SSP-0361', 'parent.ssp0361@example.com', '0146364001',
    'Sumbangan tambahan untuk aktiviti kelas dan kebajikan murid.',
    'success', 'successful', 'Mock additional donation after paid yuran',
    NOW(),
    JSON_OBJECT('donation', 60, 'outstanding_at_checkout', 0, 'tester_mode', FALSE, 'donation_intention', 'Sumbangan tambahan untuk aktiviti kelas dan kebajikan murid.'),
    JSON_OBJECT('status', '1', 'reason', 'Mock callback paid'),
    NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM family_payment_transactions
    WHERE family_billing_id = @billing_id
      AND status = 'success'
      AND donation_amount = 60
      AND donation_intention = 'Sumbangan tambahan untuk aktiviti kelas dan kebajikan murid.'
);

COMMIT;

SELECT id, family_code, billing_year, fee_amount, paid_amount, status
FROM family_billings
WHERE id = @billing_id;

SELECT id, external_order_id, amount, fee_amount_paid, donation_amount, donation_intention, status, paid_at
FROM family_payment_transactions
WHERE family_billing_id = @billing_id
ORDER BY id DESC
LIMIT 5;
