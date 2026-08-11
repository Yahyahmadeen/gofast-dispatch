USE gofast_dispatch;

-- Run once. This migration is written to be safe to re-run.
SET @has_email_verified := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='email_verified');
SET @sql := IF(@has_email_verified=0, 'ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1 AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_verification_user (user_id), INDEX idx_email_verification_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  nin_last4 CHAR(4) NULL, nin_encrypted TEXT NULL,
  bvn_last4 CHAR(4) NULL, bvn_encrypted TEXT NULL,
  vehicle_type VARCHAR(80) NULL, vehicle_number VARCHAR(80) NULL,
  id_document_path VARCHAR(255) NULL,
  verification_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(255) NULL, reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rider_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rider_verification_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_payout_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  bank_name VARCHAR(120) NOT NULL, account_name VARCHAR(190) NOT NULL,
  account_number_last4 CHAR(4) NOT NULL, account_number_encrypted TEXT NOT NULL,
  paystack_recipient_code VARCHAR(120) NULL,
  status ENUM('pending','verified','disabled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payout_account_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL, customer_user_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(120) NOT NULL UNIQUE, amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN', provider VARCHAR(40) NOT NULL DEFAULT 'paystack',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending', authorization_url TEXT NULL,
  paid_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES dispatch_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_payment_customer (customer_user_id), INDEX idx_payment_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payout_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_by BIGINT UNSIGNED NULL,
  processed_at DATETIME NULL, payment_reference VARCHAR(120) NULL UNIQUE, note VARCHAR(255) NULL,
  CONSTRAINT fk_payout_request_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payout_request_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_payout_rider_status (rider_user_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_wallet_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL, payout_request_id BIGINT UNSIGNED NULL, order_id INT UNSIGNED NULL,
  type ENUM('earning','adjustment','payout') NOT NULL, amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_rider FOREIGN KEY (rider_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_payout FOREIGN KEY (payout_request_id) REFERENCES payout_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_order FOREIGN KEY (order_id) REFERENCES dispatch_orders(id) ON DELETE SET NULL,
  INDEX idx_wallet_rider (rider_user_id,created_at)
) ENGINE=InnoDB;


-- Ensure local/demo rider accounts can be reviewed from the admin console even if
-- they were created before the verification feature was installed.
INSERT INTO rider_verifications (user_id, vehicle_type, vehicle_number, verification_status)
SELECT u.id, 'Motorcycle', 'PENDING', 'pending'
FROM users u
LEFT JOIN rider_verifications v ON v.user_id=u.id
WHERE u.role='rider' AND v.user_id IS NULL;

INSERT INTO rider_payout_accounts (rider_user_id, bank_name, account_name, account_number_last4, account_number_encrypted, status)
SELECT u.id, 'Pending bank setup', u.full_name, '0000', 'NOT_CONFIGURED', 'pending'
FROM users u
LEFT JOIN rider_payout_accounts a ON a.rider_user_id=u.id
WHERE u.role='rider' AND a.rider_user_id IS NULL;
