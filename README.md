# Sip & Pulse — Cafe Web Application

Final Project for **Web Development**, **Human–Computer Interaction (HCI)**, and **Information Management (IM)**.

Sip & Pulse is a full-stack cafe ordering and management system with a customer-facing website, admin dashboard, MySQL database, and Power BI analytics integration.

---

## Live Demo

| View | URL |
|------|-----|
| **Client (GitHub Pages)** | https://final-project-webdev-hci-im.github.io/Client%20Side/index.html |
| **Admin (GitHub Pages)** | https://final-project-webdev-hci-im.github.io/Admin%20Side/admin.html |
| **Client (Local XAMPP)** | http://localhost/final-project-webdev-hci-im.github.io/Client%20Side/index.html |
| **Admin (Local XAMPP)** | http://localhost/final-project-webdev-hci-im.github.io/Admin%20Side/admin.html |

> **Note:** GitHub Pages serves static HTML/JS only. PHP APIs and MySQL require **XAMPP** running locally for full functionality (orders, menu CRUD, analytics sync, etc.).

---

## Features

### Client Side (`Client Side/index.html`)

- Responsive landing page with hero, menu preview, cafe tour, and reviews
- Full menu browsing with categories (coffee, food, desserts, and more)
- Product detail modal (image, description, price) on item click
- Shopping cart, checkout, pickup/delivery options, and order tracking
- Guest reviews with star ratings
- User sign-in / sign-up flow
- Admin access via secure login redirect
- Multi-device support (mobile, tablet, desktop, TV, iOS, keyboard-only)

### Admin Side (`Admin Side/admin.html`)

- **Order Queue** — accept, prepare, complete, or cancel live orders
- **Order History** — full order log with filters and refund handling
- **Menu Management** — add, edit, delete items; Pinterest image URL support; required-field validation
- **Sales & Performance** — live KPI cards (orders, sales, anomalies) + embedded Power BI dashboard
- **Guest Reviews** — reply to, edit, or delete review responses
- **Device & URL access** — database-driven breakpoints and platform routing

### Analytics & Power BI

- Live KPIs pulled directly from MySQL on every page load
- Power BI **Publish to web** embed in admin dashboard (auto-reloads on refresh)
- Optional **one-time Azure API setup** for automatic cloud dataset refresh (no manual iframe copy needed)
- Data feeds for Power BI Desktop via Web connector:
  - **One-file import:** `api/powerbi_all.php` (6 sheets: Orders, Order Lines, Menu, Reviews, Daily, Calendar)
  - **Per-table CSV/JSON:** `api/powerbi_feed.php?resource=orders&format=csv`
  - **Feed catalog:** `api/powerbi_index.php`
- Google Sheets sync pipeline (optional, via Apps Script URL)
- Order changes automatically trigger analytics sync in the background

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5, Material Icons |
| Backend | PHP 8.x (XAMPP) |
| Database | MySQL / MariaDB (`sip_and_pulse_db`) |
| Analytics | Power BI Desktop & Service, Google Sheets (optional) |
| Hosting | XAMPP (local), GitHub Pages (static client) |

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- Web browser (Chrome recommended)
- [Power BI Desktop](https://powerbi.microsoft.com/desktop/) (for BI report development)
- Microsoft account with Power BI Service access (for publish & embed)

---

## Installation (Local / XAMPP)

### 1. Clone the repository

```bash
git clone https://github.com/final-project-webdev-hci-im/final-project-webdev-hci-im.github.io.git
```

Place the project in your XAMPP `htdocs` folder:

```
C:\xampp\htdocs\final-project-webdev-hci-im.github.io\
```

### 2. Start XAMPP

Open **XAMPP Control Panel** and start **Apache** and **MySQL**.

### 3. Create the database

The schema is created automatically on first API call via `database/db_connect.php`. You can also:

**Option A — Visit any API endpoint** (e.g. open the client site and browse the menu):

```
http://localhost/final-project-webdev-hci-im.github.io/api/get_menu.php
```

**Option B — Run the database repair script** (if tables are missing or corrupted):

```bash
C:\xampp\php\php.exe database\fix_db.php
```

**Option C — Seed demo data** (menu items, reviews, sample orders, default admin):

```bash
C:\xampp\php\php.exe database\demo_seed.php
```

### 4. Database configuration

Default connection settings in `database/db_connect.php`:

| Setting | Value |
|---------|-------|
| Host | `localhost` |
| User | `root` |
| Password | *(empty)* |
| Database | `sip_and_pulse_db` |

Change these if your MySQL setup differs.

### 5. Open the app

- **Client:** http://localhost/final-project-webdev-hci-im.github.io/Client%20Side/index.html
- **Admin login:** http://localhost/final-project-webdev-hci-im.github.io/api/admin_login.php

---

## Default Admin Credentials

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |
| Email | `admin@sipandpulse.com` |

> Change the default password after first login in a production environment.

---

## Power BI Setup

### Connect Power BI Desktop to the database (recommended: Web feed)

Direct MySQL connection from Power BI may fail with **Connector Error state 18** on some setups. Use the Web feed instead:

1. Open **Power BI Desktop** → **Get data** → **Web**
2. Paste this URL (adjust host if needed):

   ```
   http://localhost/final-project-webdev-hci-im.github.io/api/powerbi_all.php
   ```

3. In Navigator, check all 6 tables: **Orders**, **Order Lines**, **Menu**, **Reviews**, **Daily**, **Calendar** → **Load**
4. Create relationships in Model view (see `api/powerbi_index.php` for the relationship diagram)
5. Build visuals, DAX measures, and slicers per your BI rubric
6. **Publish** to Power BI Service → **Publish to web** for the admin embed

### View all feed URLs

```
http://localhost/final-project-webdev-hci-im.github.io/api/powerbi_index.php
```

### Optional: Power BI read-only MySQL user

```bash
mysql -u root < database/setup_powerbi_user.sql
```

| User | Password |
|------|----------|
| `powerbi` | `SipPulse_PBI_2026` |

### Auto chart updates in Admin (no manual iframe copy)

The admin dashboard automatically:

- Reloads the Power BI iframe on every page refresh (cache-busted)
- Triggers a cloud dataset refresh when new orders are placed (if API credentials are configured)
- Shows live order count from the database above the chart

**One-time optional setup** (Admin → Sales & Performance → *One-time setup: auto chart updates*):

1. Create an Azure AD app registration with Power BI API permissions
2. Enter **Client ID** and **Client Secret** in the admin panel
3. Tenant ID is auto-detected from the embed URL

After setup, the chart updates automatically — no need to copy a new iframe URL after each publish.

---

## API Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/get_menu.php` | GET | Fetch all menu items |
| `api/save_menu.php` | POST | Create/update/delete menu items (admin) |
| `api/upload_menu_image.php` | POST | Upload menu image file |
| `api/resolve_menu_image.php` | GET | Resolve Pinterest image URL |
| `api/orders.php` | GET/POST | List, create, update orders |
| `api/reviews.php` | GET/POST | Guest reviews CRUD |
| `api/save_user.php` | POST | User registration |
| `api/admin_login.php` | POST | Admin login (session) |
| `api/admin_auth.php` | GET | Check admin session |
| `api/admin_session.php` | GET | Admin session info |
| `api/logout.php` | GET | End session |
| `api/analytics.php?action=summary` | GET | Live KPIs + auto Power BI refresh |
| `api/analytics.php?action=sync` | POST | Full analytics pipeline sync |
| `api/analytics.php?action=refresh_powerbi` | POST | Trigger Power BI dataset refresh |
| `api/analytics.php?action=config` | GET | Analytics configuration |
| `api/powerbi_all.php` | GET | Excel bundle (6 sheets) for Power BI |
| `api/powerbi_feed.php` | GET | Per-table CSV/JSON feed |
| `api/powerbi_index.php` | GET | Feed URL catalog (HTML) |
| `api/device_access.php` | GET | Device/platform routing config |

---

## Project Structure

```
final-project-webdev-hci-im.github.io/
├── Client Side/
│   ├── index.html          # Customer-facing website
│   └── assets/             # Images, video, static media
├── Admin Side/
│   └── admin.html          # Admin dashboard (orders, menu, analytics, reviews)
├── api/                    # PHP REST endpoints
├── database/
│   ├── db_connect.php      # DB connection + schema migration
│   ├── analytics_helper.php
│   ├── demo_seed.php       # Demo menu, reviews, admin user
│   ├── powerbi_data_helper.php
│   └── setup_powerbi_user.sql
├── uploads/menu/           # Uploaded menu images
├── .github/workflows/      # GitHub Pages deployment
├── LICENSE                 # Apache 2.0
└── README.md
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Menu or orders not loading | Ensure Apache and MySQL are running in XAMPP |
| `Connection failed` error | Check `database/db_connect.php` credentials; create DB via `fix_db.php` |
| Admin login fails | Run `demo_seed.php` to create default admin user |
| Power BI shows old data | Refresh the admin page; enable auto-sync with Azure API credentials |
| Power BI MySQL connector error | Use Web feed (`powerbi_all.php`) instead of direct MySQL |
| Pinterest image not showing | Use `resolve_menu_image.php` or paste a direct image URL |
| GitHub Pages site has no orders | PHP/MySQL only works locally — use XAMPP for full backend |

---

## Course Context

This project demonstrates:

- **Web Development** — responsive full-stack web app with PHP API and MySQL
- **HCI** — multi-device layouts, touch targets, keyboard navigation, accessible UI patterns
- **Information Management** — structured data model, ETL pipeline, Power BI dashboards, KPI tracking

---

## License

This project is licensed under the [Apache License 2.0](LICENSE).
