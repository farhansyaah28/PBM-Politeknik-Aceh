USE pmb_politeknik_aceh;

CREATE TABLE exam_results (
  id CHAR(36) PRIMARY KEY,
  attempt_id CHAR(36) NOT NULL UNIQUE,
  participant_id CHAR(36) NOT NULL,
  session_id CHAR(36) NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  correct_count SMALLINT UNSIGNED NOT NULL,
  incorrect_count SMALLINT UNSIGNED NOT NULL,
  unanswered_count SMALLINT UNSIGNED NOT NULL,
  status ENUM('SCORED') NOT NULL DEFAULT 'SCORED',
  is_visible BOOLEAN NOT NULL DEFAULT TRUE,
  scored_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_result_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  CONSTRAINT fk_result_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_result_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  INDEX idx_result_participant_visible (participant_id, is_visible)
) ENGINE=InnoDB;
