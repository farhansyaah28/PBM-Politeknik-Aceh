USE pmb_politeknik_aceh;

CREATE TABLE study_programs (
  id CHAR(36) PRIMARY KEY,
  code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_program_active (is_active, name)
) ENGINE=InnoDB;

CREATE TABLE admission_waves (
  id CHAR(36) PRIMARY KEY,
  code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  payment_required BOOLEAN NOT NULL DEFAULT TRUE,
  registration_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_method ENUM('BANK_TRANSFER','CASH','OTHER') NOT NULL DEFAULT 'BANK_TRANSFER',
  payment_instruction TEXT NULL,
  payment_due_hours SMALLINT UNSIGNED NOT NULL DEFAULT 72,
  exam_question_count SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  exam_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  passing_grade DECIMAL(5,2) NOT NULL DEFAULT 60.00,
  security_mode ENUM('WEB_STRICT','PROCTORING_LITE') NOT NULL DEFAULT 'PROCTORING_LITE',
  registration_opens_at DATETIME NULL,
  registration_closes_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_wave_active (is_active, registration_opens_at, registration_closes_at)
) ENGINE=InnoDB;

ALTER TABLE participants ADD COLUMN program_id CHAR(36) NULL AFTER graduation_year;
ALTER TABLE participants ADD COLUMN wave_id CHAR(36) NULL AFTER program_choice;

INSERT IGNORE INTO study_programs (id, code, name, is_active, created_at, updated_at) VALUES
  ('00000000-0000-4000-8000-000000000101', 'TI', 'Teknik Informatika', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('00000000-0000-4000-8000-000000000102', 'TRK', 'Teknologi Rekayasa Komputer', TRUE, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT IGNORE INTO admission_waves (id, code, name, is_active, payment_required, registration_fee, payment_method, payment_instruction, payment_due_hours, exam_question_count, exam_duration_minutes, passing_grade, security_mode, created_at, updated_at) VALUES
  ('00000000-0000-4000-8000-000000000201', 'GEL-1-2026', 'Gelombang 1', TRUE, TRUE, 250000, 'BANK_TRANSFER', 'Konfigurasi rekening dan instruksi pembayaran resmi harus diisi Admin sebelum produksi.', 72, 20, 30, 60.00, 'PROCTORING_LITE', UTC_TIMESTAMP(), UTC_TIMESTAMP());

UPDATE participants SET program_id = '00000000-0000-4000-8000-000000000101' WHERE program_choice = 'Teknik Informatika' AND program_id IS NULL;
UPDATE participants SET program_id = '00000000-0000-4000-8000-000000000102' WHERE program_choice = 'Teknologi Rekayasa Komputer' AND program_id IS NULL;
UPDATE participants SET wave_id = '00000000-0000-4000-8000-000000000201' WHERE wave = 'Gelombang 1' AND wave_id IS NULL;

ALTER TABLE participants ADD CONSTRAINT fk_participant_program FOREIGN KEY (program_id) REFERENCES study_programs(id);
ALTER TABLE participants ADD CONSTRAINT fk_participant_wave FOREIGN KEY (wave_id) REFERENCES admission_waves(id);
CREATE INDEX idx_participant_program_wave ON participants (program_id, wave_id);

-- Akun seed ini hanya untuk review lokal dengan data sintetis. Jangan dipakai di produksi.
INSERT IGNORE INTO users (id, username, email, password_hash, role, status, created_at, updated_at) VALUES
  ('00000000-0000-4000-8000-000000000301', 'admin.uji', 'admin.uji@example.test', '$2y$10$h7T.M/5l3I3/nXlCxxQe6uQwDnodOVG0y/esqD6.hV4kxusFESJuS', 'ADMIN', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('00000000-0000-4000-8000-000000000302', 'panitia.uji', 'panitia.uji@example.test', '$2y$10$zmOhwwRcQN8n/kRWN2q8Ye1GAfWofUnjRWOrUf1sDJbqrYO0.Nkim', 'COMMITTEE', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP());
