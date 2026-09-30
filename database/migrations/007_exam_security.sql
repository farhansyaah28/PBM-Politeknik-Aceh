USE pmb_politeknik_aceh;

CREATE TABLE exam_session_consents (
  id CHAR(36) PRIMARY KEY,
  participant_id CHAR(36) NOT NULL,
  session_id CHAR(36) NOT NULL,
  consent_version VARCHAR(80) NOT NULL,
  accepted_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_exam_consent_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_exam_consent_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  UNIQUE KEY uq_exam_consent_once (participant_id, session_id, consent_version)
) ENGINE=InnoDB;

CREATE TABLE exam_security_events (
  id CHAR(36) PRIMARY KEY,
  attempt_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NOT NULL,
  event_type ENUM('FULLSCREEN_ENTERED','FULLSCREEN_EXIT','TAB_HIDDEN','CAMERA_GRANTED','CAMERA_DENIED','PHOTO_CAPTURED') NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_security_event_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  CONSTRAINT fk_security_event_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  INDEX idx_security_event_attempt (attempt_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE proctoring_photos (
  id CHAR(36) PRIMARY KEY,
  attempt_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NOT NULL,
  storage_key VARCHAR(500) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  content_hash CHAR(64) NOT NULL,
  captured_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_proctor_photo_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  CONSTRAINT fk_proctor_photo_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  UNIQUE KEY uq_proctor_photo_attempt (attempt_id)
) ENGINE=InnoDB;

ALTER TABLE exam_attempts MODIFY status ENUM('IN_PROGRESS','PAUSED_REVIEW','SUBMITTED') NOT NULL DEFAULT 'IN_PROGRESS';
