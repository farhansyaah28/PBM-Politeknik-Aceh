USE pmb_politeknik_aceh;

ALTER TABLE participants
  ADD COLUMN admission_path VARCHAR(32) NOT NULL DEFAULT 'Reguler' AFTER graduation_year;

ALTER TABLE exam_sessions
  MODIFY COLUMN token_hash VARCHAR(255) NULL,
  MODIFY COLUMN token_hint VARCHAR(8) NULL,
  ADD COLUMN token_required BOOLEAN NOT NULL DEFAULT TRUE AFTER token_hint;

ALTER TABLE exam_results
  ADD COLUMN passing_grade DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER score,
  ADD COLUMN automatic_decision ENUM('PASSED','NOT_PASSED') NOT NULL DEFAULT 'NOT_PASSED' AFTER passing_grade;

UPDATE exam_results er
INNER JOIN exam_sessions es ON es.id = er.session_id
SET er.passing_grade = es.passing_grade,
    er.automatic_decision = CASE WHEN er.score >= es.passing_grade THEN 'PASSED' ELSE 'NOT_PASSED' END;
