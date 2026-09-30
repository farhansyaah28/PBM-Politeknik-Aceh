USE pmb_politeknik_aceh;

ALTER TABLE exam_sessions
  ADD COLUMN token_ciphertext TEXT NULL AFTER token_hash;

ALTER TABLE official_decisions
  ADD COLUMN scheduled_publish_at DATETIME NULL AFTER communication_status,
  ADD COLUMN scheduled_by CHAR(36) NULL AFTER scheduled_publish_at,
  ADD CONSTRAINT fk_official_decision_scheduler FOREIGN KEY (scheduled_by) REFERENCES users(id),
  ADD INDEX idx_official_decision_schedule (communication_status, is_current, scheduled_publish_at);
