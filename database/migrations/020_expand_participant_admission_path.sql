USE pmb_politeknik_aceh;

ALTER TABLE participants
  MODIFY COLUMN admission_path VARCHAR(64) NOT NULL DEFAULT 'Reguler';
