CREATE DATABASE IF NOT EXISTS web_scraper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE web_scraper;

CREATE TABLE migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE cache (
  `key` VARCHAR(255) PRIMARY KEY,
  value MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE cache_locks (
  `key` VARCHAR(255) PRIMARY KEY,
  owner VARCHAR(255) NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id VARCHAR(255) PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX sessions_user_id_index (user_id),
  INDEX sessions_last_activity_index (last_activity)
) ENGINE=InnoDB;

CREATE TABLE jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  INDEX jobs_queue_index (queue)
) ENGINE=InnoDB;

CREATE TABLE job_batches (
  id VARCHAR(255) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  total_jobs INT NOT NULL,
  pending_jobs INT NOT NULL,
  failed_jobs INT NOT NULL,
  failed_job_ids LONGTEXT NOT NULL,
  options MEDIUMTEXT NULL,
  cancelled_at INT NULL,
  created_at INT NOT NULL,
  finished_at INT NULL
) ENGINE=InnoDB;

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE projects (
  id VARCHAR(80) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  url TEXT NOT NULL,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  config JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  INDEX projects_deleted_at_index (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE scraping_elements (
  id VARCHAR(80) PRIMARY KEY,
  project_id VARCHAR(80) NOT NULL,
  name VARCHAR(255) NOT NULL,
  selector_css TEXT NOT NULL,
  tag_name VARCHAR(100) NOT NULL DEFAULT '',
  attributes JSON NULL,
  extraction_method VARCHAR(40) NOT NULL DEFAULT 'visible_text',
  attribute_name VARCHAR(255) NULL,
  data_type VARCHAR(40) NOT NULL DEFAULT 'text',
  config JSON NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  INDEX elements_project_active_index (project_id, is_active),
  CONSTRAINT elements_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE scraping_tasks (
  id VARCHAR(80) PRIMARY KEY,
  project_id VARCHAR(80) NOT NULL,
  name VARCHAR(255) NOT NULL,
  interval_value INT UNSIGNED NOT NULL,
  interval_unit VARCHAR(16) NOT NULL DEFAULT 'minutes',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  last_run_at TIMESTAMP NULL,
  next_run_at TIMESTAMP NULL,
  schedule_config JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  INDEX tasks_next_run_index (next_run_at),
  INDEX tasks_project_status_index (project_id, status),
  CONSTRAINT tasks_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE executions (
  id VARCHAR(80) PRIMARY KEY,
  project_id VARCHAR(80) NOT NULL,
  task_id VARCHAR(80) NULL,
  scheduled_at TIMESTAMP NULL,
  started_at TIMESTAMP NOT NULL,
  finished_at TIMESTAMP NULL,
  duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'running',
  fields_processed INT UNSIGNED NOT NULL DEFAULT 0,
  fields_succeeded INT UNSIGNED NOT NULL DEFAULT 0,
  fields_failed INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX executions_project_started_index (project_id, started_at),
  CONSTRAINT executions_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT executions_task_fk FOREIGN KEY (task_id) REFERENCES scraping_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE history_values (
  id VARCHAR(80) PRIMARY KEY,
  project_id VARCHAR(80) NOT NULL,
  execution_id VARCHAR(80) NOT NULL,
  element_id VARCHAR(80) NULL,
  element_name VARCHAR(255) NOT NULL,
  data_type VARCHAR(40) NOT NULL DEFAULT 'text',
  raw_value LONGTEXT NULL,
  normalized_value LONGTEXT NULL,
  numeric_value DECIMAL(20,6) NULL,
  currency VARCHAR(12) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'valid',
  captured_at TIMESTAMP NOT NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX history_project_type_capture_index (project_id, data_type, captured_at),
  INDEX history_element_capture_status_index (element_id, captured_at, status),
  CONSTRAINT history_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT history_execution_fk FOREIGN KEY (execution_id) REFERENCES executions(id) ON DELETE CASCADE,
  CONSTRAINT history_element_fk FOREIGN KEY (element_id) REFERENCES scraping_elements(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  email VARCHAR(255) PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
) ENGINE=InnoDB;

INSERT INTO migrations (migration, batch) VALUES
  ('0001_01_01_000000_create_users_table', 1),
  ('0001_01_01_000001_create_cache_table', 1),
  ('0001_01_01_000002_create_jobs_table', 1),
  ('2026_10_15_000000_create_scraping_tables', 1);