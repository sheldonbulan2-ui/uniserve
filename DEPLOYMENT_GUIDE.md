# UniServe Cloud Deployment Guide
### Render.com + Supabase (PostgreSQL) + CronJob Keep-Alive

---

## 1. Supabase Database Setup

1. **Create or Open Supabase Project:**
   * Go to [https://supabase.com/dashboard](https://supabase.com/dashboard) and create or select your project.
2. **Execute Database Schema:**
   * In the left sidebar, click **SQL Editor** -> **New Query**.
   * Open the file `supabase_schema.sql` in this repo, copy its entire content, paste it into the SQL Editor, and click **Run**.
   * Verify all 6 tables (`users`, `categories`, `businesses`, `services`, `service_requests`, `transactions`) are created with initial seed data.
3. **Get Your Database Connection String:**
   * In Supabase, go to **Project Settings** -> **Database**.
   * Under **Connection string**, select **URI** (or **Transaction Pooler** for best serverless connection handling).
   * Note the connection string:
     ```text
     postgresql://postgres.[YOUR-PROJECT-REF]:[YOUR-PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres
     ```
     *(Make sure to replace `[YOUR-PASSWORD]` with your actual database password).*

---

## 2. GitHub Repository Setup

To deploy to Render, push this project to a GitHub repository:

```bash
git init
git add .
git commit -m "feat: complete UniServe setup with Supabase, Docker, and Render support"
git branch -M main
git remote add origin https://github.com/[YOUR-USERNAME]/[YOUR-REPO-NAME].git
git push -u origin main
```

---

## 3. Render.com Deployment

1. Go to [https://dashboard.render.com](https://dashboard.render.com).
2. Click **New +** -> **Web Service**.
3. Connect your GitHub repository.
4. Settings:
   * **Name:** `uniserve-system` (or your preferred name)
   * **Language / Environment:** `Docker` (Render will automatically detect the `Dockerfile`)
   * **Region:** Singapore / Oregon / Frankfurt (select the region closest to your Supabase region)
   * **Branch:** `main`
   * **Instance Type:** `Free`
5. **Environment Variables:**
   Click **Advanced** -> **Add Environment Variable**:
   * Key: `DATABASE_URL`
   * Value: `postgresql://postgres.[YOUR-REF]:[YOUR-PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres`
   * Key: `PORT`
   * Value: `10000`
6. Click **Deploy Web Service**.
7. Once deployed, Render will provide your public URL:
   `https://uniserve-system.onrender.com`

---

## 4. CronJob Keep-Alive Setup (Prevent Free-Tier Sleep)

Render's free tier spins down if there is no web traffic for 15 minutes. To keep it 24/7 active and warm:

### Option A: Using cron-job.org (Recommended & Free)
1. Go to [https://cron-job.org](https://cron-job.org) and create a free account.
2. Click **Create Cronjob**.
3. **Title:** `UniServe KeepAlive`
4. **URL:** `https://your-uniserve-app.onrender.com/ping.php`
5. **Schedule:** Every **10 minutes** (or 14 minutes).
6. **Request Method:** `GET`
7. Click **Save**.

### Option B: Render Cron Job
1. In Render Dashboard, click **New +** -> **Cron Job**.
2. Set Command: `curl -sS https://your-uniserve-app.onrender.com/ping.php`
3. Schedule: `*/10 * * * *` (Every 10 minutes).

---

## 5. Seed Accounts & Default Logins

* **Administrator:**
  * Email: `admin@uniserve.com`
  * Password: `password` (or `password123`)
* **Business Owner:**
  * Email: `maryjoy@gmail.com`
* **Customer:**
  * Email: `joycerine@gmail.com`
