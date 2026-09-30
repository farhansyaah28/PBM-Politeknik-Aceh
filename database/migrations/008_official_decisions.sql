USE pmb_politeknik_aceh;

CREATE TABLE official_decisions (
  id CHAR(36) PRIMARY KEY,
  result_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NOT NULL,
  decision ENUM('PASSED','NOT_PASSED') NOT NULL,
  note TEXT NOT NULL,
  communication_status ENUM('PENDING','PUBLISHED') NOT NULL DEFAULT 'PENDING',
  published_at DATETIME NULL,
  published_by CHAR(36) NULL,
  is_current BOOLEAN NOT NULL DEFAULT TRUE,
  supersedes_decision_id CHAR(36) NULL,
  decided_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_official_decision_result FOREIGN KEY (result_id) REFERENCES exam_results(id),
  CONSTRAINT fk_official_decision_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_official_decision_publisher FOREIGN KEY (published_by) REFERENCES users(id),
  CONSTRAINT fk_official_decision_previous FOREIGN KEY (supersedes_decision_id) REFERENCES official_decisions(id),
  CONSTRAINT fk_official_decision_decider FOREIGN KEY (decided_by) REFERENCES users(id),
  INDEX idx_official_decision_result_current (result_id, is_current, created_at),
  INDEX idx_official_decision_participant_published (participant_id, is_current, communication_status)
) ENGINE=InnoDB;
