USE pmb_politeknik_aceh;

CREATE TABLE verification_decisions (
  id CHAR(36) PRIMARY KEY, participant_id CHAR(36) NOT NULL, decision ENUM('APPROVED','NEEDS_CORRECTION','REJECTED') NOT NULL,
  checklist_json JSON NOT NULL, note TEXT NULL, reviewed_by CHAR(36) NOT NULL, created_at DATETIME NOT NULL,
  CONSTRAINT fk_verification_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_verification_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id), INDEX idx_verification_participant (participant_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE payment_review_decisions (
  id CHAR(36) PRIMARY KEY, payment_id CHAR(36) NOT NULL, proof_id CHAR(36) NULL,
  decision ENUM('VERIFIED','NEEDS_REUPLOAD','REJECTED') NOT NULL, checklist_json JSON NOT NULL, note TEXT NULL,
  reviewed_by CHAR(36) NOT NULL, created_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_review_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_payment_review_proof FOREIGN KEY (proof_id) REFERENCES payment_proofs(id),
  CONSTRAINT fk_payment_review_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id), INDEX idx_payment_review_payment (payment_id, created_at)
) ENGINE=InnoDB;
