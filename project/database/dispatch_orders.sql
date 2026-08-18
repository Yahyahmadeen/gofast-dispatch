-- GOFAST core dispatch module
-- Run this in phpMyAdmin against gofast_dispatch.

CREATE TABLE IF NOT EXISTS dispatch_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(32) NOT NULL UNIQUE,
  customer_user_id INT UNSIGNED NOT NULL,
  rider_user_id INT UNSIGNED NULL,
  branch VARCHAR(80) NOT NULL DEFAULT 'Yola',
  pickup_address VARCHAR(255) NOT NULL,
  dropoff_address VARCHAR(255) NOT NULL,
  recipient_name VARCHAR(120) NOT NULL,
  recipient_phone VARCHAR(30) NOT NULL,
  package_description VARCHAR(255) NOT NULL,
  cod_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','assigned','picked_up','in_transit','delivered','failed','returned') NOT NULL DEFAULT 'pending',
  proof_type ENUM('none','photo','otp','signature') NOT NULL DEFAULT 'none',
  proof_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dispatch_customer (customer_user_id),
  INDEX idx_dispatch_rider (rider_user_id),
  INDEX idx_dispatch_status (status),
  INDEX idx_dispatch_branch (branch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  changed_by_user_id INT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_order (order_id),
  INDEX idx_history_user (changed_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
