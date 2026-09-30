USE pmb_politeknik_aceh;

CREATE TABLE retention_policies (
  policy_code VARCHAR(40) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  retention_days SMALLINT UNSIGNED NOT NULL,
  updated_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE retention_runs (
  id CHAR(36) PRIMARY KEY,
  mode ENUM('DRY_RUN','EXECUTED') NOT NULL,
  summary_json JSON NOT NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NOT NULL
) ENGINE=InnoDB;

INSERT INTO retention_policies (policy_code,label,retention_days,updated_by,updated_at) VALUES
('PROCTORING_PHOTOS','Foto proctoring',30,NULL,UTC_TIMESTAMP()),
('SECURITY_EVENTS','Event keamanan ujian',365,NULL,UTC_TIMESTAMP()),
('PAYMENT_PROOFS','Bukti pembayaran',1825,NULL,UTC_TIMESTAMP()),
('CONSENT_RECORDS','Persetujuan peserta',1825,NULL,UTC_TIMESTAMP()),
('AUDIT_LOGS','Audit log',1825,NULL,UTC_TIMESTAMP());
