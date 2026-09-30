CREATE DATABASE IF NOT EXISTS pmb_politeknik_aceh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pmb_politeknik_aceh;

CREATE TABLE users (
  id CHAR(36) PRIMARY KEY, username VARCHAR(32) NOT NULL UNIQUE, email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL, role ENUM('PARTICIPANT','COMMITTEE','ADMIN') NOT NULL,
  status ENUM('PENDING','ACTIVE','DISABLED') NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE participants (
  id CHAR(36) PRIMARY KEY, user_id CHAR(36) NOT NULL UNIQUE, registration_number VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL, phone_number VARCHAR(32) NOT NULL, school_name VARCHAR(190) NOT NULL,
  graduation_year SMALLINT NOT NULL, program_choice VARCHAR(120) NOT NULL, wave VARCHAR(80) NOT NULL,
  account_status ENUM('PENDING','ACTIVE','DISABLED') NOT NULL, verification_status ENUM('PENDING','APPROVED','NEEDS_CORRECTION','REJECTED') NOT NULL,
  registration_submitted_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
  CONSTRAINT fk_participant_user FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_participant_status (account_status, verification_status)
) ENGINE=InnoDB;
CREATE TABLE consent_records (
  id CHAR(36) PRIMARY KEY, participant_id CHAR(36) NOT NULL, consent_version VARCHAR(80) NOT NULL, consent_type VARCHAR(40) NOT NULL,
  accepted_at DATETIME NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT fk_consent_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB;
CREATE TABLE payments (
  id CHAR(36) PRIMARY KEY, participant_id CHAR(36) NOT NULL, fee_code VARCHAR(60) NOT NULL, payment_required BOOLEAN NOT NULL,
  status ENUM('NOT_REQUIRED','UNPAID','PENDING_REVIEW','VERIFIED','NEEDS_REUPLOAD','REJECTED','EXPIRED') NOT NULL,
  amount_expected DECIMAL(14,2) NOT NULL, amount_received DECIMAL(14,2) NULL, currency CHAR(3) NOT NULL, method ENUM('BANK_TRANSFER','CASH','OTHER') NOT NULL,
  sender_name VARCHAR(160) NULL, paid_at DATETIME NULL,
  due_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_participant FOREIGN KEY (participant_id) REFERENCES participants(id), UNIQUE KEY uq_payment_fee (participant_id, fee_code), INDEX idx_payment_status (status, due_at)
) ENGINE=InnoDB;
CREATE TABLE payment_proofs (
  id CHAR(36) PRIMARY KEY, payment_id CHAR(36) NOT NULL, storage_key VARCHAR(500) NOT NULL UNIQUE,
  original_filename VARCHAR(190) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL,
  content_hash CHAR(64) NOT NULL, review_status ENUM('PENDING','VERIFIED','NEEDS_REUPLOAD','REJECTED') NOT NULL,
  submitted_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_proof_payment FOREIGN KEY (payment_id) REFERENCES payments(id), INDEX idx_payment_proof_status (payment_id, review_status)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
  id CHAR(36) PRIMARY KEY, actor_type VARCHAR(30) NOT NULL, actor_id CHAR(36) NULL, participant_id CHAR(36) NULL,
  action VARCHAR(100) NOT NULL, target_type VARCHAR(60) NOT NULL, target_id VARCHAR(64) NOT NULL, request_id VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL, INDEX idx_audit_participant (participant_id, created_at)
) ENGINE=InnoDB;
