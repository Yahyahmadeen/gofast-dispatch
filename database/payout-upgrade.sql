-- GOFAST Rider Payout Upgrade
-- Safe to run after verification-payments.sql.
-- These CREATE statements are idempotent and preserve existing payout data.

CREATE TABLE IF NOT EXISTS rider_payout_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  bank_name VARCHAR(120) NOT NULL,
  account_name VARCHAR(190) NOT NULL,
  account_number_last4 CHAR(4) NOT NULL,
  account_number_encrypted TEXT NOT NULL,
  paystack_recipient_code VARCHAR(120) NULL,
  status ENUM('pending','verified','disabled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payout_account_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payout_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_by BIGINT UNSIGNED NULL,
  processed_at DATETIME NULL,
  payment_reference VARCHAR(120) NULL UNIQUE,
  note VARCHAR(255) NULL,
  CONSTRAINT fk_payout_request_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payout_request_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_payout_rider_status (rider_user_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_wallet_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  payout_request_id BIGINT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  type ENUM('earning','adjustment','payout') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_payout FOREIGN KEY (payout_request_id) REFERENCES payout_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_order FOREIGN KEY (order_id) REFERENCES dispatch_orders(id) ON DELETE SET NULL,
  INDEX idx_wallet_rider (rider_user_id,created_at)
) ENGINE=InnoDB;
