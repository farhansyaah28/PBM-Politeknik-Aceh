USE pmb_politeknik_aceh;

CREATE TABLE retention_default_policies (
  policy_code VARCHAR(40) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  retention_years TINYINT UNSIGNED NOT NULL,
  updated_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_retention_policy_editor FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE participant_retention_overrides (
  id CHAR(36) PRIMARY KEY,
  participant_id CHAR(36) NOT NULL,
  retention_years TINYINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_by CHAR(36) NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_retention_override_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_retention_override_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_retention_override_editor FOREIGN KEY (updated_by) REFERENCES users(id),
  UNIQUE KEY uq_retention_override_participant (participant_id)
) ENGINE=InnoDB;

INSERT INTO retention_default_policies (policy_code, label, retention_years, updated_by, updated_at) VALUES
('PROCTORING_PHOTOS', 'Foto proctoring', 1, NULL, UTC_TIMESTAMP()),
('SECURITY_EVENTS', 'Event keamanan ujian', 1, NULL, UTC_TIMESTAMP()),
('PAYMENT_PROOFS', 'Bukti pembayaran', 5, NULL, UTC_TIMESTAMP()),
('CONSENT_RECORDS', 'Persetujuan peserta', 5, NULL, UTC_TIMESTAMP()),
('AUDIT_LOGS', 'Audit log', 5, NULL, UTC_TIMESTAMP());
