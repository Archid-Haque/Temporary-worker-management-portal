CREATE DATABASE IF NOT EXISTS workerledger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE workerledger;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','employer','supervisor','worker') NOT NULL DEFAULT 'worker',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  worker_id BIGINT UNSIGNED NOT NULL,
  employer_id BIGINT UNSIGNED NOT NULL,
  supervisor_id BIGINT UNSIGNED NULL,
  task VARCHAR(255) NOT NULL,
  work_date DATE NOT NULL,
  rate_type ENUM('daily','per_task') NOT NULL DEFAULT 'daily',
  rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  units DECIMAL(10,2) NOT NULL DEFAULT 1,
  extra_work DECIMAL(12,2) NOT NULL DEFAULT 0,
  advance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  attendance_status ENUM('pending','marked','confirmed','disputed') NOT NULL DEFAULT 'pending',
  record_status ENUM('pending','confirmed','disputed') NOT NULL DEFAULT 'pending',
  created_by BIGINT UNSIGNED NOT NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_record_worker FOREIGN KEY (worker_id) REFERENCES users(id),
  CONSTRAINT fk_record_employer FOREIGN KEY (employer_id) REFERENCES users(id),
  CONSTRAINT fk_record_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_record_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_record_confirmer FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_records_worker (worker_id),
  INDEX idx_records_employer (employer_id),
  INDEX idx_records_date (work_date),
  INDEX idx_records_status (record_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  action ENUM('marked','confirmed','disputed') NOT NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (record_id) REFERENCES work_records(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id),
  INDEX idx_attendance_record (record_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  type ENUM('advance','payment','extra') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (record_id) REFERENCES work_records(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id),
  INDEX idx_payment_record (record_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;
