CREATE DATABASE IF NOT EXISTS gofast_dispatch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gofast_dispatch;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL UNIQUE,
  phone VARCHAR(30) NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  role ENUM('customer','rider','dispatcher','admin') NOT NULL DEFAULT 'customer',
  status ENUM('active','pending','suspended','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  customer_type ENUM('individual','business') NOT NULL DEFAULT 'individual',
  business_name VARCHAR(190) NULL,
  business_registration VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO branches (name, city, code) VALUES ('Yola Branch','Yola','YOL');

CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL,
  contact_name VARCHAR(120) NULL,
  contact_phone VARCHAR(30) NULL,
  address_line VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  landmark VARCHAR(190) NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_addresses_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  id_number VARCHAR(100) NULL,
  vehicle_type VARCHAR(80) NULL,
  vehicle_number VARCHAR(80) NULL,
  photo_path VARCHAR(255) NULL,
  verification_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  availability ENUM('available','on_delivery','off_duty') NOT NULL DEFAULT 'off_duty',
  branch_id BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_riders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_riders_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_riders_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(30) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  rider_id BIGINT UNSIGNED NULL,
  branch_id BIGINT UNSIGNED NULL,
  pickup_address_id BIGINT UNSIGNED NULL,
  dropoff_address_id BIGINT UNSIGNED NULL,
  pickup_address VARCHAR(255) NOT NULL,
  dropoff_address VARCHAR(255) NOT NULL,
  recipient_name VARCHAR(120) NULL,
  recipient_phone VARCHAR(30) NULL,
  package_description TEXT NULL,
  cod_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','assigned','picked_up','in_transit','delivered','failed','returned') NOT NULL DEFAULT 'pending',
  assigned_at DATETIME NULL,
  picked_up_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  returned_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_pickup FOREIGN KEY (pickup_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_dropoff FOREIGN KEY (dropoff_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
  INDEX idx_orders_customer_status (customer_id,status),
  INDEX idx_orders_rider_status (rider_id,status),
  INDEX idx_orders_branch_status (branch_id,status),
  INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_history_order (order_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_proofs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  rider_id BIGINT UNSIGNED NOT NULL,
  photo_path VARCHAR(255) NOT NULL,
  proof_method ENUM('signature','otp') NOT NULL,
  signature_data LONGTEXT NULL,
  otp_verified BOOLEAN NOT NULL DEFAULT FALSE,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_proofs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_proofs_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  reported_by BIGINT UNSIGNED NOT NULL,
  incident_type ENUM('damaged_package','theft','accident','other') NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_incident_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_incident_reporter FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_incident_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_reconciliations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  reconciliation_date DATE NOT NULL,
  orders_completed INT UNSIGNED NOT NULL DEFAULT 0,
  cash_collected DECIMAL(12,2) NOT NULL DEFAULT 0,
  reconciled BOOLEAN NOT NULL DEFAULT FALSE,
  reconciled_by BIGINT UNSIGNED NULL,
  reconciled_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rider_day (rider_id,reconciliation_date),
  CONSTRAINT fk_cash_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_user FOREIGN KEY (reconciled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  channel ENUM('sms','whatsapp','email','in_app') NOT NULL,
  title VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
  sent_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  INDEX idx_notifications_user (user_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('brand_name','GOFAST'),
('default_branch','YOL');
