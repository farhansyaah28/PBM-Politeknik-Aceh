USE pmb_politeknik_aceh;

CREATE TABLE question_categories (
  id CHAR(36) PRIMARY KEY,
  code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE questions (
  id CHAR(36) PRIMARY KEY,
  category_id CHAR(36) NOT NULL,
  question_text TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_question_category FOREIGN KEY (category_id) REFERENCES question_categories(id),
  CONSTRAINT fk_question_creator FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_question_category_active (category_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE exam_sessions (
  id CHAR(36) PRIMARY KEY,
  wave_id CHAR(36) NOT NULL,
  name VARCHAR(160) NOT NULL,
  status ENUM('DRAFT','SCHEDULED','CLOSED') NOT NULL DEFAULT 'DRAFT',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  token_hint VARCHAR(8) NOT NULL,
  question_count SMALLINT UNSIGNED NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  passing_grade DECIMAL(5,2) NOT NULL,
  security_mode ENUM('WEB_STRICT','PROCTORING_LITE') NOT NULL,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_session_wave FOREIGN KEY (wave_id) REFERENCES admission_waves(id),
  CONSTRAINT fk_session_creator FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_session_wave_status (wave_id, status, starts_at)
) ENGINE=InnoDB;

CREATE TABLE exam_assignments (
  id CHAR(36) PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NOT NULL,
  assignment_status ENUM('ASSIGNED','STARTED','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'ASSIGNED',
  assigned_by CHAR(36) NOT NULL,
  assigned_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_assignment_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  CONSTRAINT fk_assignment_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_assignment_assigner FOREIGN KEY (assigned_by) REFERENCES users(id),
  UNIQUE KEY uq_assignment_once (session_id, participant_id),
  INDEX idx_assignment_participant (participant_id, assignment_status)
) ENGINE=InnoDB;

INSERT IGNORE INTO question_categories (id, code, name, is_active, created_at, updated_at) VALUES
  ('00000000-0000-4000-8000-000000000401', 'VERBAL', 'Kemampuan Verbal', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('00000000-0000-4000-8000-000000000402', 'NUMERIK', 'Kemampuan Numerik', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('00000000-0000-4000-8000-000000000403', 'LOGIKA', 'Penalaran Logis', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('00000000-0000-4000-8000-000000000404', 'UMUM', 'Pengetahuan Umum', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP());
