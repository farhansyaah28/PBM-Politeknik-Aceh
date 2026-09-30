USE pmb_politeknik_aceh;

ALTER TABLE participants
  ADD COLUMN password_ciphertext TEXT NULL AFTER registration_number;
