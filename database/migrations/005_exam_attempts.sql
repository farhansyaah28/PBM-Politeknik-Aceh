USE pmb_politeknik_aceh;

CREATE TABLE exam_attempts (
  id CHAR(36) PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NOT NULL,
  attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('IN_PROGRESS','SUBMITTED') NOT NULL DEFAULT 'IN_PROGRESS',
  started_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_attempt_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  CONSTRAINT fk_attempt_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  UNIQUE KEY uq_attempt_one_per_session (session_id, participant_id),
  INDEX idx_attempt_participant_status (participant_id, status, expires_at)
) ENGINE=InnoDB;

CREATE TABLE attempt_questions (
  id CHAR(36) PRIMARY KEY,
  attempt_id CHAR(36) NOT NULL,
  question_id CHAR(36) NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  options_json JSON NOT NULL,
  option_order JSON NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_attempt_question_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  CONSTRAINT fk_attempt_question_question FOREIGN KEY (question_id) REFERENCES questions(id),
  UNIQUE KEY uq_attempt_question_position (attempt_id, position),
  UNIQUE KEY uq_attempt_question_source (attempt_id, question_id)
) ENGINE=InnoDB;

CREATE TABLE exam_answers (
  id CHAR(36) PRIMARY KEY,
  attempt_id CHAR(36) NOT NULL,
  attempt_question_id CHAR(36) NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  saved_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  CONSTRAINT fk_answer_question FOREIGN KEY (attempt_question_id) REFERENCES attempt_questions(id),
  UNIQUE KEY uq_answer_question (attempt_id, attempt_question_id)
) ENGINE=InnoDB;
