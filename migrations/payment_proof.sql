-- Add proof-of-payment image path support for existing databases.
ALTER TABLE payment
  ADD COLUMN IF NOT EXISTS pay_proof VARCHAR(255) DEFAULT NULL AFTER reference_no;
