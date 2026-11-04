# UniServe — Business & Service Management Platform

UniServe is a multi-role service marketplace and business operations system designed for Customers, Business Owners, and Administrators.

---

## Standard Project Structure

```text
uniserve/
├── api/                      # REST API endpoints (JSON responses)
│   ├── account.php
│   ├── admin_dashboard.php
│   ├── businesses.php
│   ├── login.php
│   ├── register.php
│   ├── reports.php
│   ├── services.php
│   ├── service_requests.php
│   └── transactions.php
├── assets/                   # Static frontend assets
│   └── css/                  # Page-specific modular stylesheets
├── config/                   # Configuration files
│   └── database.php          # Universal MySQL & Supabase PostgreSQL adapter
├── database/                 # Database schemas & migrations
│   ├── supabase_schema.sql   # PostgreSQL DDL for Supabase
│   └── mysql_schema.sql      # MySQL / MariaDB dump
├── docs/                     # Documentation & guides
│   └── DEPLOYMENT_GUIDE.md   # Step-by-step cloud deployment manual
├── includes/                 # Shared helper & authentication modules
│   ├── auth.php              # Role-based route guards
│   └── helpers.php           # Sanitization & JSON utility functions
├── config.php                # Legacy compatibility loader for config/database.php
├── index.php                 # Role-based router & session entry point
├── login.php                 # Account access portal
├── register.php              # Account creation portal (Customer & Business Owner)
├── ping.php                  # Keep-alive & health-check endpoint
├── Dockerfile                # Production container specification (PHP 8.2 + Apache)
├── render.yaml               # Render Blueprint service definition
└── .env.example              # Environment variables template
```

---

## Quick Start (Local Development)

1. **Import Database:**
   Import `database/mysql_schema.sql` into your local MySQL server (via phpMyAdmin or MySQL CLI) into a database named `uniserve_db`.
2. **Start Web Server:**
   Using XAMPP, start Apache and MySQL, then place the folder in `htdocs` or run via PHP built-in server:
   ```bash
   php -S localhost:8000
   ```
3. **Open in Browser:**
   Visit `http://localhost:8000/login.php`.

---

## Production Deployment

- **Database:** Hosted on **Supabase** (PostgreSQL). Run `database/supabase_schema.sql` in the Supabase SQL Editor.
- **Web Host:** Hosted on **Render.com** (Docker runtime). Set `DATABASE_URL` and `PORT=10000` in the environment.
- **Cron Job:** Pings `https://<your-render-url>/ping.php` every 10 minutes on `cron-job.org` to keep the free instance active.

Refer to [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) for full instructions.
