# 🏛️ VastuKundali - Complete SaaS Platform

> **India's leading AI-powered Vastu analysis and remedy ecommerce platform.**
> Built with vanilla HTML/CSS/JS frontend + pure PHP backend - works on any shared hosting.

[![PHP](https://img.shields.io/badge/PHP-7.0+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/license-Proprietary-green.svg)]()

---

## ✨ Features

### 🎨 Frontend (HTML / CSS / JS)
- World-class luxury spiritual design (gold + dark + cream theme)
- Fully responsive (mobile, tablet, desktop)
- Animated landing page with hero, features, pricing, testimonials, FAQ
- Multi-step upload wizard (5 steps with progress bar)
- 16-zone Vastu compass and direction selector
- Premium PDF-style report viewing with animated score circle, heatmap, etc.
- Full ecommerce store (filters, search, cart drawer, checkout)
- Login / Register / Dashboard pages
- WhatsApp float button + exit-intent popup

### 🤖 AI Engine
- **Claude AI integration** (Anthropic API or AWS Bedrock with SigV4)
- **Built-in rule-based VastuEngine** — works WITHOUT external AI
- 16-zone directional analysis with elemental associations
- Room placement scoring (kitchen, bedroom, toilet, pooja room, etc.)
- Personalized remedies prioritized by impact
- Life impact analysis (health, wealth, relations, career)
- Product recommendations linked to detected defects
- Energy heatmap (4×4 grid visualization)

### 💳 Payment Integration
- **Razorpay** (UPI, cards, net banking, wallets, EMI)
- Order creation, signature verification, webhooks
- Refund support, payment fetch, subscription-ready
- Demo mode (works without real keys for testing)

### 🛍️ Ecommerce Store
- Product catalog with filters (category, price, rating)
- Product detail modal with images, descriptions, ratings
- Shopping cart (slide-out drawer with localStorage persistence)
- Razorpay checkout integration
- Inventory management

### 📄 Report Generation
- Beautiful multi-page A4 PDF reports
- Auto-fallback chain: dompdf → TCPDF → wkhtmltopdf → HTML
- Email + WhatsApp delivery
- Premium cover page, executive summary, life impacts, heatmap, room analysis, remedies, recommended products, final verdict

### 👤 User System
- JWT-based authentication (no external library needed)
- User dashboard with reports, orders, profile
- Bcrypt password hashing
- Session management

### 🛡️ Admin Panel (CMS)
- Full dashboard with KPIs (revenue, reports, users, orders)
- Reports management (filter, search, regenerate AI analysis)
- Orders management (status updates, tracking numbers)
- Products CRUD (with categories, inventory, featured flag)
- Users management with stats
- Leads tracking (from form submissions)
- Coupons system (percentage/fixed, usage limits)
- Settings panel (all credentials & CMS configs)
- Activity logs

### 🔒 Security
- .htaccess blocks PHP execution in uploads/
- Library files blocked from direct access
- SQL injection prevention (PDO prepared statements)
- XSS protection (output escaping, htmlspecialchars)
- CSRF protection on admin forms
- Bcrypt password hashing
- Razorpay signature verification

---

## 📦 Project Structure

```
imsharma/
├── frontend/                       # Vanilla HTML/CSS/JS frontend
│   ├── index.html                  # Landing page
│   ├── css/
│   │   ├── style.css              # Main theme (1000+ lines)
│   │   └── animations.css         # Page-specific styles
│   ├── js/
│   │   ├── main.js                # Core JS (navbar, toast, auth, etc.)
│   │   ├── upload.js              # Wizard + Razorpay integration
│   │   ├── store.js               # Store + cart logic
│   │   └── report.js              # Report rendering
│   ├── images/
│   └── pages/
│       ├── upload.html            # 5-step wizard
│       ├── report.html            # Report viewer
│       ├── store.html             # Ecommerce store
│       ├── login.html
│       ├── register.html
│       └── dashboard.html         # User dashboard
│
├── backend/                       # PHP backend (works on any shared hosting)
│   ├── config/
│   │   ├── config.php             # All credentials & constants
│   │   └── database.php           # PDO singleton
│   ├── includes/
│   │   ├── helpers.php            # Helper functions
│   │   └── auth.php               # JWT + session auth
│   ├── lib/
│   │   ├── Razorpay.php           # Razorpay client (no deps)
│   │   ├── VastuEngine.php        # Rule-based AI engine
│   │   ├── ClaudeAI.php           # Claude/Bedrock integration
│   │   └── PDFReport.php          # PDF generator
│   ├── api/                       # REST API endpoints
│   │   ├── auth_register.php
│   │   ├── auth_login.php
│   │   ├── upload.php
│   │   ├── payment_create_order.php
│   │   ├── payment_verify.php
│   │   ├── generate_report.php
│   │   ├── get_report.php
│   │   ├── download_pdf.php
│   │   ├── email_report.php
│   │   ├── products.php
│   │   ├── order_create.php
│   │   ├── order_verify.php
│   │   ├── my_reports.php
│   │   ├── my_orders.php
│   │   └── webhook.php            # Razorpay webhook
│   ├── admin/                     # Admin panel (CMS)
│   │   ├── index.php              # Login
│   │   ├── dashboard.php          # KPI dashboard
│   │   ├── reports.php
│   │   ├── report_view.php
│   │   ├── orders.php
│   │   ├── products.php
│   │   ├── users.php
│   │   ├── leads.php
│   │   ├── coupons.php
│   │   ├── settings.php
│   │   └── logout.php
│   ├── uploads/                   # House plan uploads (auto-created)
│   ├── reports/                   # Generated PDFs (auto-created)
│   ├── install.php                # One-click installer
│   └── .htaccess                  # Security rules
│
├── database/
│   └── schema.sql                 # Full database schema + seed data
│
├── .htaccess                      # Root rules
├── index.php                      # Redirects to frontend
└── README.md                      # This file
```

---

## 🚀 Installation Guide

### Prerequisites
- **PHP 7.0+** (PHP 8.x recommended)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache** with mod_rewrite (or **Nginx**)
- **PHP extensions:** PDO, MySQL, cURL, mbstring, JSON, OpenSSL
- A web hosting account with cPanel/Plesk/SSH

### Step 1: Upload Files

**Via cPanel File Manager:**
1. Log into your cPanel
2. Navigate to `public_html` (or your site root)
3. Upload all files from this project
4. Extract if you uploaded a ZIP

**Via FTP (FileZilla, etc.):**
1. Connect to your hosting via FTP
2. Upload entire project folder to your web root

### Step 2: Create Database

**For Hostinger / cPanel / GoDaddy / shared hosting:**

1. Login to your hosting control panel (e.g., **hPanel** for Hostinger)
2. Find **MySQL Databases** (sometimes under "Databases" section)
3. Click **"Create New Database"**
4. Enter a name like `vastu_kundali` — your hosting will **automatically prefix it** (e.g., `u770423744_vastu_kundali`)
5. **Create a new MySQL user** (also auto-prefixed, e.g., `u770423744_vastu`)
6. **Assign the user to the database** with **ALL PRIVILEGES**
7. **Note down the FULL prefixed names** — you'll need them in the next step

**⚠️ Common Mistake:** Trying to use `vastu_kundali` instead of `u770423744_vastu_kundali`
— always use the **full prefixed name** that the hosting created.

### Step 2b: Import Schema

1. Open **phpMyAdmin** from your hosting panel
2. **CRITICAL:** Click on your database name in the **LEFT SIDEBAR** to select it first
   (do NOT just go to the Import tab from the home page!)
3. Click the **"Import"** tab at the top
4. Click **"Choose File"** and select `database/schema.sql`
5. Click **"Go"** at the bottom
6. You should see "Import has been successfully finished, X queries executed"

> If you see error `#1044 - Access denied for user '...' to database '...'`,
> it means you didn't select your database first in the left sidebar before clicking Import.
> The SQL file does NOT contain `CREATE DATABASE` statements — your hosting won't allow that.

### Step 3: Configure Backend

Edit `backend/config/config.php`:

```php
// Database config
define('DB_HOST', 'localhost');
define('DB_NAME', 'u770423744_vastu_kundali'); // Use the FULL prefixed name from hosting!
define('DB_USER', 'u770423744_vastu');         // Use the FULL prefixed user name!
define('DB_PASS', 'your_database_password');   // Update

// Site URL (no trailing slash)
define('SITE_URL', 'https://yourdomain.com');

// JWT secret (CHANGE THIS to a random 64-char string!)
define('JWT_SECRET', 'CHANGE_TO_RANDOM_STRING_64_CHARS');

// Razorpay keys (get from https://dashboard.razorpay.com/app/keys)
define('RAZORPAY_KEY_ID', 'rzp_test_xxxxxxxxxxxxx');
define('RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxx');

// Optional: AI keys for premium reports
define('CLAUDE_API_KEY', 'sk-ant-api03-...'); // From console.anthropic.com
// OR use AWS Bedrock:
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET_KEY', '');
define('AWS_REGION', 'us-east-1');
```

### Step 4: Run Installer

1. Visit: `https://yourdomain.com/backend/install.php`
2. Verify all system requirements pass (green checkmarks)
3. Click **"Run Installation"**
4. Database tables and seed data will be created
5. **DELETE `install.php` immediately** for security!

### Step 5: First Login to Admin

1. Visit: `https://yourdomain.com/backend/admin/`
2. Login with default credentials:
   - **Email:** `admin@vastukundali.com`
   - **Password:** `admin123`
3. **Change the password immediately!** (Settings → Account)
4. Configure all settings (Razorpay, Claude API, etc.) via the **Settings page**

### Step 6: Test the Flow

1. Visit your site: `https://yourdomain.com`
2. Click **"Generate Your Vastu Kundali - ₹99"**
3. Upload any image (test with a sample floor plan)
4. Select direction, fill details
5. Pay (use Razorpay test mode or demo mode)
6. View the generated report

---

## ⚙️ Configuration Reference

### Razorpay Setup

1. Sign up at [razorpay.com](https://razorpay.com)
2. Go to **Dashboard → Settings → API Keys**
3. Generate test keys (`rzp_test_...`) for development
4. Switch to live keys (`rzp_live_...`) for production
5. Configure webhook URL: `https://yourdomain.com/backend/api/webhook.php`
6. Webhook events to enable:
   - `payment.captured`
   - `payment.failed`
   - `order.paid`
   - `refund.processed`

### AI Configuration (Optional)

The platform works **without any AI configuration** — the built-in `VastuEngine` produces high-quality reports.

For premium AI-powered reports with computer vision on floor plans:

**Option A: Direct Anthropic API (recommended)**
1. Sign up at [console.anthropic.com](https://console.anthropic.com)
2. Get API key (starts with `sk-ant-api03-...`)
3. Add to `config.php` or via Admin Settings: `claude_api_key`
4. Cost: ~₹2-5 per report (Claude 3.5 Sonnet)

**Option B: AWS Bedrock**
1. Enable Claude in AWS Bedrock console
2. Create IAM user with `bedrock:InvokeModel` permission
3. Add credentials to `config.php` or Admin Settings:
   - `aws_access_key`
   - `aws_secret_key`
   - `aws_region` (e.g., `us-east-1`)
   - `bedrock_model` (e.g., `anthropic.claude-3-sonnet-20240229-v1:0`)

### Email Configuration

For production, configure SMTP via Admin Settings:
- `smtp_host` (e.g., `smtp.gmail.com`)
- `smtp_port` (587)
- `smtp_user` (your email)
- `smtp_pass` (app password)
- `smtp_secure` (`tls`)

For testing, basic PHP `mail()` works on most shared hosts.

### PDF Generation

The platform auto-detects available PDF libraries:

1. **HTML output** (default, always works) - browser prints to PDF perfectly
2. **dompdf** (install via Composer): `composer require dompdf/dompdf`
3. **TCPDF** (install via Composer): `composer require tecnickcom/tcpdf`
4. **wkhtmltopdf** (binary): `apt-get install wkhtmltopdf` (best quality)

---

## 🎯 API Endpoints

All endpoints are at `/backend/api/`:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `auth_register.php` | POST | Register new user |
| `auth_login.php` | POST | Login (returns JWT) |
| `upload.php` | POST | Upload house plan + form data |
| `payment_create_order.php` | POST | Create Razorpay order for report |
| `payment_verify.php` | POST | Verify payment signature |
| `generate_report.php` | POST | Trigger AI report generation |
| `get_report.php?id=X` | GET | Fetch report data (JSON) |
| `download_pdf.php?id=X` | GET | Download PDF |
| `email_report.php` | POST | Email report to customer |
| `products.php` | GET | List products (with filters) |
| `order_create.php` | POST | Create product order |
| `order_verify.php` | POST | Verify product order payment |
| `my_reports.php` | GET | User's report history |
| `my_orders.php` | GET | User's order history |
| `webhook.php` | POST | Razorpay webhook receiver |

---

## 💰 Pricing & Revenue Model

The platform supports multiple revenue streams (configured in **Admin Settings**):

1. **AI Vastu Reports** - ₹99 per report (configurable)
2. **Premium Consultation** - ₹499 (with expert)
3. **Full Vastu Consultation** - ₹2,999 (45-min expert call)
4. **Ecommerce Store** - Vastu products (pyramids, yantras, crystals, etc.)
5. **Affiliate Revenue** - Add Amazon affiliate links to product descriptions

---

## 🎨 Customization

### Branding
- Logo: Edit emoji `🏛️` in HTML files (or replace with image)
- Primary color: `#D4AF37` (gold)
- Update in `frontend/css/style.css`:
  ```css
  --gold: #D4AF37;
  --dark: #0A0E27;
  ```

### Add Pages
- Create new file in `frontend/pages/`
- Include the navbar/footer markup from existing pages
- Link CSS: `<link rel="stylesheet" href="../css/style.css">`

### Add Products
- Admin Panel → Products → Add New Product
- Or insert directly into `products` table

### Add Blog Posts
- The `blog_posts` table is seeded; add your own blog UI by extending the dashboard

---

## 🐛 Troubleshooting

### "#1044 - Access denied for user '...' to database '...'" (during SQL import)
This is the **most common shared hosting error**. It means:
- Your MySQL user doesn't have `CREATE DATABASE` privileges (normal on shared hosting)
- You're trying to import SQL while NOT selecting your database first

**Fix:**
1. In phpMyAdmin, **click your database name in the left sidebar** to select it
2. THEN click the "Import" tab and import `database/schema.sql`
3. The schema.sql file does NOT contain `CREATE DATABASE` statements anymore — it only creates tables inside your existing database

### "#1146 - Table '...' doesn't exist" (after import)
- The import may have partially failed. Drop all tables and re-import
- Make sure your database name in `config.php` matches the prefixed name (e.g., `u770423744_vastu_kundali`)

### "Database connection failed"
- Verify credentials in `backend/config/config.php`
- Check that the database exists and user has permissions
- Try `localhost` vs `127.0.0.1` for DB_HOST

### "Failed to upload"
- Check `backend/uploads/` is writable: `chmod 755 backend/uploads`
- Increase PHP `upload_max_filesize` and `post_max_size` (12M+)
- Check file is JPG, PNG, or PDF under 10MB

### "Razorpay error"
- Verify keys are correct (test keys start with `rzp_test_`)
- Check that the keys match the secret
- Ensure cURL is enabled in PHP

### "PDF not generating"
- The HTML version always works (browser print-to-PDF)
- For real PDF, install dompdf via Composer
- Check `backend/reports/pdf/` is writable

### "AI not responding"
- The system falls back to rule-based engine automatically
- Check API keys in Admin Settings
- Check `backend/debug.log` for errors

### "Admin panel not loading"
- Default credentials: `admin@vastukundali.com` / `admin123`
- If forgotten, reset via SQL:
  ```sql
  UPDATE users SET password_hash = '$2y$10$kQqV9iOCJp9JeBCPxgLfCe7CmzYrWWqLqXaJ8pBzYg9F4F8KZwHTu'
  WHERE email = 'admin@vastukundali.com';
  ```
  (This sets password back to `admin123`)

---

## 📝 Production Checklist

Before going live:

- [ ] Change `JWT_SECRET` to a random 64-char string
- [ ] Change admin password from `admin123`
- [ ] Set `APP_ENV` to `production` in config.php
- [ ] Delete `install.php`
- [ ] Update Razorpay keys to **live** keys
- [ ] Configure SMTP for transactional emails
- [ ] Set up SSL certificate (Let's Encrypt)
- [ ] Configure Razorpay webhook URL
- [ ] Update `SITE_URL` in config.php
- [ ] Add Google Analytics / Facebook Pixel to HTML
- [ ] Test full flow: upload → pay → report → download
- [ ] Test ecommerce: add to cart → checkout → order placed
- [ ] Set up daily database backups
- [ ] Add `robots.txt` and `sitemap.xml`
- [ ] Submit sitemap to Google Search Console

---

## 🆘 Support

- **Documentation:** This README
- **Admin Panel:** `/backend/admin/`
- **Default Admin:** `admin@vastukundali.com` / `admin123`
- **Razorpay Docs:** https://razorpay.com/docs
- **Anthropic Docs:** https://docs.anthropic.com

---

## 📄 License

Proprietary - All rights reserved.

This software is delivered as a custom build. You have full rights to deploy and modify for your own business.

---

## 🙏 Built With Love

Designed for **Indian homeowners** seeking traditional Vastu wisdom enhanced by modern AI.

May your home be filled with positive energy, prosperity, and harmony. 🕉️

---

**Generated with VastuKundali - India's leading AI-powered Vastu analysis platform.**
