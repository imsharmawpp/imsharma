-- ==========================================================
-- Vastu Home Kundali AI Platform - Database Schema
-- MySQL/MariaDB compatible (works on shared hosting)
-- ==========================================================
--
-- SHARED HOSTING USERS (Hostinger, cPanel, GoDaddy, etc.):
--   1. FIRST create a database via your hosting control panel
--      (e.g., "u770423744_vastu_kundali")
--   2. Create a MySQL user and assign it to that database
--   3. Open phpMyAdmin -> SELECT YOUR DATABASE on the left sidebar
--   4. Go to "Import" tab and import this schema.sql
--   5. Update backend/config/config.php with your actual DB name
--
-- The CREATE DATABASE + USE statements are intentionally omitted
-- because shared hosting users don't have those privileges.
-- ==========================================================

-- ============== USERS ==============
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) UNIQUE NOT NULL,
    phone VARCHAR(20),
    city VARCHAR(80),
    password_hash VARCHAR(255),
    role ENUM('user','admin') DEFAULT 'user',
    avatar VARCHAR(255),
    email_verified TINYINT(1) DEFAULT 0,
    phone_verified TINYINT(1) DEFAULT 0,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB;

-- ============== REPORTS ==============
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(120),
    customer_email VARCHAR(160),
    customer_phone VARCHAR(20),
    image_path VARCHAR(500) NOT NULL,
    image_url VARCHAR(500),
    direction VARCHAR(10),
    plot_size VARCHAR(40),
    floors VARCHAR(10),
    concerns TEXT,
    city VARCHAR(80),
    overall_score INT DEFAULT 0,
    summary TEXT,
    final_verdict TEXT,
    report_json LONGTEXT,
    pdf_path VARCHAR(500),
    pdf_url VARCHAR(500),
    status ENUM('pending','paid','processing','completed','failed') DEFAULT 'pending',
    payment_id VARCHAR(80),
    amount DECIMAL(10,2) DEFAULT 99.00,
    delivered_email TINYINT(1) DEFAULT 0,
    delivered_whatsapp TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_email (customer_email)
) ENGINE=InnoDB;

-- ============== PRODUCTS ==============
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    category VARCHAR(60),
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    inventory INT DEFAULT 100,
    icon VARCHAR(80) DEFAULT 'gem',
    image VARCHAR(500),
    images TEXT,
    rating DECIMAL(3,2) DEFAULT 4.5,
    reviews INT DEFAULT 0,
    badge VARCHAR(40),
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    seo_title VARCHAR(180),
    seo_description VARCHAR(500),
    weight DECIMAL(8,2),
    sku VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ============== ORDERS ==============
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(120),
    customer_email VARCHAR(160),
    customer_phone VARCHAR(20),
    items_json TEXT NOT NULL,
    items_count INT DEFAULT 0,
    subtotal DECIMAL(10,2),
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    coupon_code VARCHAR(40),
    amount DECIMAL(10,2) NOT NULL,
    payment_id VARCHAR(80),
    razorpay_order_id VARCHAR(80),
    status ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
    shipping_name VARCHAR(120),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_city VARCHAR(80),
    shipping_state VARCHAR(80),
    shipping_pincode VARCHAR(10),
    shipping_country VARCHAR(60) DEFAULT 'India',
    tracking_number VARCHAR(80),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_email (customer_email)
) ENGINE=InnoDB;

-- ============== PAYMENTS ==============
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razorpay_order_id VARCHAR(80) NOT NULL,
    razorpay_payment_id VARCHAR(80),
    razorpay_signature VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    status ENUM('created','attempted','captured','failed','refunded') DEFAULT 'created',
    type ENUM('report','order','consultation') DEFAULT 'report',
    reference_id INT,
    user_id INT,
    customer_email VARCHAR(160),
    customer_phone VARCHAR(20),
    method VARCHAR(40),
    error_code VARCHAR(40),
    error_description TEXT,
    refund_id VARCHAR(80),
    raw_response TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_razorpay_order (razorpay_order_id),
    INDEX idx_payment (razorpay_payment_id),
    INDEX idx_status (status),
    INDEX idx_reference (type, reference_id)
) ENGINE=InnoDB;

-- ============== COUPONS ==============
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) UNIQUE NOT NULL,
    description VARCHAR(200),
    type ENUM('percentage','fixed') DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2),
    usage_limit INT DEFAULT 0,
    used_count INT DEFAULT 0,
    valid_from DATETIME,
    valid_until DATETIME,
    applies_to ENUM('all','reports','products') DEFAULT 'all',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============== LEADS ==============
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120),
    email VARCHAR(160),
    phone VARCHAR(20),
    source VARCHAR(60),
    message TEXT,
    status ENUM('new','contacted','converted','closed') DEFAULT 'new',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_source (source)
) ENGINE=InnoDB;

-- ============== SETTINGS (CMS) ==============
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(40) DEFAULT 'general',
    description VARCHAR(200),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB;

-- ============== BLOG POSTS ==============
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(500),
    author VARCHAR(120) DEFAULT 'Vastu Expert',
    category VARCHAR(60),
    tags VARCHAR(300),
    seo_title VARCHAR(180),
    seo_description VARCHAR(500),
    is_published TINYINT(1) DEFAULT 0,
    published_at DATETIME,
    views INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_published (is_published, published_at)
) ENGINE=InnoDB;

-- ============== ADMIN ACTIVITY LOG ==============
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(80),
    target_type VARCHAR(40),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action)
) ENGINE=InnoDB;

-- ==========================================================
-- SEED DATA
-- ==========================================================

-- Default admin (password: admin123 - CHANGE IMMEDIATELY)
INSERT INTO users (name, email, phone, password_hash, role, email_verified)
VALUES ('Admin', 'admin@vastukundali.com', '9999999999',
    '$2y$10$kQqV9iOCJp9JeBCPxgLfCe7CmzYrWWqLqXaJ8pBzYg9F4F8KZwHTu', 'admin', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Default settings
INSERT INTO settings (setting_key, setting_value, setting_group, description) VALUES
('site_name', 'VastuKundali AI', 'general', 'Site name'),
('site_email', 'support@vastukundali.com', 'general', 'Contact email'),
('site_phone', '+919876543210', 'general', 'Contact phone'),
('whatsapp_number', '919876543210', 'general', 'WhatsApp number'),
('report_price', '99', 'pricing', 'Single Vastu report price'),
('premium_price', '499', 'pricing', 'Premium consultation price'),
('full_consult_price', '2999', 'pricing', 'Full consultation price'),
('razorpay_key_id', 'rzp_test_DEMO_KEY', 'payment', 'Razorpay Key ID'),
('razorpay_key_secret', 'DEMO_SECRET', 'payment', 'Razorpay Key Secret'),
('razorpay_webhook_secret', '', 'payment', 'Razorpay webhook secret'),
('aws_access_key', '', 'aws', 'AWS access key (optional - for Bedrock)'),
('aws_secret_key', '', 'aws', 'AWS secret key (optional)'),
('aws_region', 'us-east-1', 'aws', 'AWS region'),
('bedrock_model', 'anthropic.claude-3-sonnet-20240229-v1:0', 'aws', 'Bedrock model ID'),
('claude_api_key', '', 'ai', 'Anthropic Claude API key (alternative to Bedrock)'),
('email_from', 'noreply@vastukundali.com', 'email', 'From email'),
('smtp_host', '', 'email', 'SMTP host'),
('smtp_port', '587', 'email', 'SMTP port'),
('smtp_user', '', 'email', 'SMTP username'),
('smtp_pass', '', 'email', 'SMTP password'),
('shipping_flat', '50', 'shipping', 'Flat shipping rate'),
('shipping_free_above', '999', 'shipping', 'Free shipping above this amount'),
('gst_percent', '18', 'tax', 'GST percentage')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Sample products
INSERT INTO products (title, slug, description, short_description, category, price, original_price, icon, rating, reviews, badge, is_featured) VALUES
('Crystal Vastu Pyramid (9-Set)', 'crystal-vastu-pyramid-9-set',
 'Energize your Brahmasthan zone with this premium 9-pyramid set. Made of natural clear quartz crystal, each pyramid is precisely cut to amplify positive energy. Place in the central area of any home or office for maximum benefits. Reduces negative energies and brings prosperity.',
 'Premium 9-pyramid crystal set for energy amplification', 'pyramid', 899, 1499, 'gem', 4.7, 234, 'Bestseller', 1),

('Brass Vastu Tortoise', 'brass-vastu-tortoise',
 'Hand-crafted brass tortoise symbolizing stability, longevity, and grounded energy. Place on north zone for career growth, family harmony, and steady income. Comes with energizing Vedic prayers performed before dispatch.',
 'Symbol of stability and longevity for career growth', 'brass', 599, 999, 'dharmachakra', 4.6, 187, NULL, 1),

('Himalayan Salt Lamp (Original)', 'himalayan-salt-lamp',
 'Hand-carved natural pink salt from the Khewra mines of Pakistan. Purifies negative energy, releases negative ions, improves air quality, and creates a serene ambiance. Comes with premium wooden base and dimmer switch.',
 'Natural salt lamp for energy purification', 'lamp', 1299, 2499, 'lightbulb', 4.8, 412, 'New', 1),

('Copper Vastu Strip Set (4 pcs)', 'copper-vastu-strip-set',
 'Pure copper strips (99.9% purity) for blocking negative energy flow at thresholds, electrical points, and corners. Set of 4 strips of varying sizes. Easy peel-and-stick installation. Includes detailed placement guide.',
 'Pure copper strips to block negative energy', 'copper', 499, 899, 'minus', 4.5, 156, NULL, 1),

('Sphatik Shree Yantra (Premium)', 'sphatik-shree-yantra',
 'Sacred geometry crystal yantra for wealth, prosperity, and abundance. Hand-carved on natural sphatik (clear quartz) and energized by Vedic priests through 21 days of mantra chanting. The most powerful yantra for material and spiritual gains.',
 'Sacred yantra for wealth and abundance', 'yantra', 1799, 2999, 'star-of-life', 4.9, 567, 'Premium', 0),

('5 Mukhi Rudraksha Mala (108 beads)', '5-mukhi-rudraksha-mala',
 'Authentic 108-bead Rudraksha mala for meditation, spiritual protection, and stress relief. Sourced from Indonesia, certified for purity. Each bead is 8mm. Comes with silk pouch and authenticity certificate.',
 'Sacred 108-bead mala for meditation', 'rudraksha', 1199, 1999, 'circle-notch', 4.7, 298, NULL, 0),

('Money Plant (Marble Queen Pothos)', 'money-plant-pothos',
 'Auspicious Vastu plant for prosperity. Best placed in southeast direction for wealth attraction. Low-maintenance, air-purifying. Comes in decorative ceramic pot with care guide.',
 'Auspicious plant for wealth and prosperity', 'plant', 299, 499, 'seedling', 4.4, 134, NULL, 0),

('Amethyst Crystal Cluster (Brazilian)', 'amethyst-crystal-cluster',
 'Natural Brazilian amethyst cluster, ideal for bedroom for peaceful sleep, calm mind, and stress relief. Reduces electromagnetic radiation. Each piece is unique, weighing 250-350g.',
 'Natural amethyst for peaceful sleep', 'crystal', 2499, 4499, 'gem', 4.8, 198, NULL, 0),

('Brass Laughing Buddha (Sitting)', 'brass-laughing-buddha',
 'Symbol of happiness, laughter, and abundance. Premium brass with antique finish. Place at entrance facing the front door, or on the office desk facing east. Brings positive vibes and wealth.',
 'Symbol of happiness and abundance', 'brass', 799, 1299, 'smile', 4.6, 245, NULL, 0),

('Black Tourmaline Bracelet', 'black-tourmaline-bracelet',
 'Powerful protection stone bracelet. Wards off negative energies, electromagnetic radiation, and psychic attacks. 8mm beads, elastic stretch fit. Comes with cleansing instructions.',
 'Protection stone bracelet', 'crystal', 699, 1199, 'circle', 4.5, 167, NULL, 0),

('Kuber Yantra (Gold Plated)', 'kuber-yantra-gold-plated',
 'Lord Kuber yantra for wealth and financial stability. Gold-plated brass with intricate engravings. Best placed in north zone or wallet/locker. Energized through traditional Vedic rituals.',
 'Wealth yantra for financial stability', 'yantra', 1499, 2499, 'coins', 4.7, 389, NULL, 1),

('Lucky Bamboo Plant (8 Stalks)', 'lucky-bamboo-8-stalks',
 'Lucky bamboo with 8 stalks symbolizing abundance and wealth. Comes in elegant glass vase with decorative pebbles. Maintenance-free, keep in indirect light. Perfect Vastu plant for southeast corner.',
 'Lucky bamboo for abundance', 'plant', 449, 799, 'tree', 4.5, 178, 'Lucky', 0)
ON DUPLICATE KEY UPDATE id=id;

-- Sample coupons
INSERT INTO coupons (code, description, type, value, min_order, applies_to, valid_from, valid_until, is_active) VALUES
('VASTU20', '20% off your first report', 'percentage', 20, 99, 'reports', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('NEWUSER100', '₹100 off on orders above ₹500', 'fixed', 100, 500, 'products', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('FESTIVAL30', '30% off festival sale', 'percentage', 30, 0, 'all', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)
ON DUPLICATE KEY UPDATE id=id;

-- Sample blog post
INSERT INTO blog_posts (title, slug, excerpt, content, category, is_published, published_at) VALUES
('5 Vastu Tips for Wealth and Prosperity in 2024',
 '5-vastu-tips-wealth-prosperity-2024',
 'Discover proven Vastu Shastra tips that bring wealth and prosperity to your home. Simple changes with profound results.',
 '<h2>Introduction</h2><p>Vastu Shastra has been guiding Indian homes for thousands of years...</p>',
 'Vastu Tips', 1, NOW())
ON DUPLICATE KEY UPDATE id=id;
