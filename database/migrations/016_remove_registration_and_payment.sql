USE pmb_politeknik_aceh;

DELETE FROM retention_default_policies WHERE policy_code = 'PAYMENT_PROOFS';
DROP TABLE payment_review_decisions;
DROP TABLE payment_proofs;
DROP TABLE payments;
ALTER TABLE admission_waves
  DROP COLUMN payment_required,
  DROP COLUMN registration_fee,
  DROP COLUMN payment_method,
  DROP COLUMN payment_instruction,
  DROP COLUMN payment_due_hours;
