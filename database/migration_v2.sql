-- ==========================================================
-- VastuKundali - Migration v2
-- Adds: OTP verification, addresses, COD support, Twilio settings
-- Safe to re-run (uses IF NOT EXISTS)
-- ==========================================================

-- ============== OTP CODES ==============
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(64) NOT NULL,
    purpose VARCHAR(40) DEFAULT 'verification',
    attempts INT DEFAULT 0,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_purpose (phone, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============== ADDRESSES ==============
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    label VARCHAR(40) DEFAULT 'Home',
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(80) NOT NULL,
    state VARCHAR(80) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    country VARCHAR(60) DEFAULT 'India',
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_phone (phone),
    INDEX idx_pincode (pincode)
) ENGINE=InnoDB;

-- ============== ADD COD + ADDRESS_ID TO ORDERS ==============
-- Use procedural block to add columns only if missing
DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE add_column_if_missing(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tbl
          AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_column_if_missing('orders', 'payment_method', "payment_method ENUM('online','cod') DEFAULT 'online' AFTER payment_id");
CALL add_column_if_missing('orders', 'address_id', "address_id INT NULL AFTER shipping_country");
CALL add_column_if_missing('orders', 'phone_verified', "phone_verified TINYINT(1) DEFAULT 0 AFTER status");
CALL add_column_if_missing('orders', 'cod_charge', "cod_charge DECIMAL(10,2) DEFAULT 0 AFTER tax");

DROP PROCEDURE IF EXISTS add_column_if_missing;

-- ============== TWILIO + COD SETTINGS ==============
INSERT INTO settings (setting_key, setting_value, setting_group, description) VALUES
('twilio_sid', '', 'twilio', 'Twilio Account SID'),
('twilio_token', '', 'twilio', 'Twilio Auth Token'),
('twilio_whatsapp_from', 'whatsapp:+14155238886', 'twilio', 'Twilio WhatsApp From number (sandbox: +14155238886)'),
('twilio_content_sid', '', 'twilio', 'Twilio Content Template SID (optional, for production)'),
('cod_enabled', '1', 'shipping', 'Enable Cash on Delivery'),
('cod_charge', '40', 'shipping', 'Extra charge for COD orders (₹)'),
('cod_max_amount', '5000', 'shipping', 'Max order amount for COD (₹)'),
('require_phone_verification', '1', 'general', 'Require phone OTP verification at checkout')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============== ADD CITY/STATE TO USERS ==============
DROP PROCEDURE IF EXISTS add_user_col;
DELIMITER $$
CREATE PROCEDURE add_user_col(IN col VARCHAR(64), IN col_def VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE users ADD COLUMN ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_user_col('state', "state VARCHAR(80) AFTER city");
CALL add_user_col('pincode', "pincode VARCHAR(10) AFTER state");
DROP PROCEDURE IF EXISTS add_user_col;
