USE pmb_politeknik_aceh;

CREATE TABLE retention_holds (
  id CHAR(36) PRIMARY KEY,
  participant_id CHAR(36) NOT NULL,
  reason TEXT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  applied_by CHAR(36) NOT NULL,
  applied_at DATETIME NOT NULL,
  released_by CHAR(36) NULL,
  released_at DATETIME NULL,
  release_note TEXT NULL,
  CONSTRAINT fk_retention_hold_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_retention_hold_applier FOREIGN KEY (applied_by) REFERENCES users(id),
  CONSTRAINT fk_retention_hold_releaser FOREIGN KEY (released_by) REFERENCES users(id),
  INDEX idx_retention_hold_participant_active (participant_id, is_active, applied_at)
) ENGINE=InnoDB;
