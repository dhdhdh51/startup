# Bharat SEO Lead Finder & Auto Audit CRM Tool

**Internal admin tool for Bharat SEO agency**

> Website + Google Map SEO + WhatsApp Leads for Local Businesses

## Features

- **Lead Search** - Find local business leads via Google Places API, SerpAPI, Apify, or CSV import
- **Auto Website Audit** - 30+ point SEO & online presence audit using cURL
- **Smart Scoring** - 100-point scoring system with opportunity levels
- **CRM Management** - Track lead status from New to Converted
- **Deduplication** - Automatically prevents duplicate leads
- **Outreach Generator** - Auto-generates WhatsApp messages based on audit results
- **Bulk Operations** - Bulk audit and bulk outreach
- **CSV Import/Export** - Easy data import and export
- **Cron Support** - Automated batch auditing
- **Mobile Responsive** - Works on all devices

## Tech Stack

- PHP 8+
- MySQL 5.7+
- HTML5 / CSS3 / JavaScript
- cURL for website auditing
- No frameworks, no Node.js
- Shared hosting & VPS ready

## Installation

### 1. Upload Files
Upload all files to your web server (e.g., `/public_html/crm/` or any directory).

### 2. Create Database
Create a MySQL database named `bharat_seo_crm` (or any name).

### 3. Import SQL
Import the `database.sql` file into your database:
```bash
mysql -u root -p bharat_seo_crm < database.sql
```

### 4. Configure Database
Edit `config.php` and update the database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bharat_seo_crm');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### 5. Set Permissions
```bash
chmod 755 cron/
```

### 6. Login
Open `admin/login.php` in your browser.

**Default Login:**
- Username: `admin`
- Password: `admin123`

### 7. Configure API Keys (Settings page)
Go to Settings and add your API keys:
- Google Places API Key
- SerpAPI Key
- Apify API Token

### 8. Setup Cron Job (Optional)
For automated batch auditing:
```bash
*/10 * * * * php /path/to/cron/audit-pending-leads.php >> /path/to/logs/audit.log 2>&1
```

## Folder Structure

```
├── config.php                 # Database & app configuration
├── functions.php              # Core functions (auth, audit, search, scoring)
├── database.sql               # MySQL schema & default data
├── README.md                  # This file
├── admin/
│   ├── login.php              # Admin login
│   ├── logout.php             # Admin logout
│   ├── dashboard.php          # Dashboard with stats
│   ├── lead-search.php        # Search for new leads
│   ├── leads.php              # CRM lead list
│   ├── lead-view.php          # Full lead profile
│   ├── lead-audit.php         # Single lead audit
│   ├── bulk-audit.php         # Bulk audit leads
│   ├── import-csv.php         # CSV import
│   ├── export.php             # CSV export
│   ├── outreach.php           # Bulk outreach
│   ├── settings.php           # Admin settings
│   └── includes/
│       ├── admin-header.php   # Header template
│       └── admin-footer.php   # Footer template
├── assets/
│   ├── css/admin.css          # Admin styles
│   └── js/admin.js            # Admin JavaScript
└── cron/
    └── audit-pending-leads.php # Cron job for batch audits
```

## Lead Sources

1. **Google Places API** - Search by niche + city
2. **SerpAPI Google Maps** - Alternative search API
3. **Apify Actor** - Automated data collection
4. **CSV Import** - Manual bulk upload
5. **Website Crawl** - Audit from URL

## Audit Score Breakdown (100 points)

| Category | Points |
|----------|--------|
| Website Presence | 15 |
| Website SEO Basics | 20 |
| Mobile Readiness | 10 |
| Local Conversion Elements | 20 |
| Google Profile Strength | 20 |
| Social Presence | 10 |
| Lead Readiness | 5 |

## Opportunity Levels

| Score | Level |
|-------|-------|
| 0-30 | Very High Opportunity |
| 31-50 | High Opportunity |
| 51-70 | Medium Opportunity |
| 71-85 | Low Opportunity |
| 86-100 | Strong Online Presence |

## Security

- PDO prepared statements (SQL injection protection)
- password_hash / password_verify
- CSRF token protection on all forms
- Session-based authentication
- Output escaping (XSS protection)
- API keys stored securely in database

## Important Notes

- Does NOT directly scrape Google Maps HTML
- Does NOT bypass captcha
- Uses only legitimate APIs
- Website audit crawls homepage only
- Respects rate limits and uses delays
- For internal agency use only

## License

Private - Bharat SEO Internal Use Only
