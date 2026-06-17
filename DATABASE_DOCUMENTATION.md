# Sip & Pulse Café Management Database
## Technical Documentation

**Database Name:** `sip_and_pulse_db`  
**DBMS:** MariaDB 10.4 (XAMPP)  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_general_ci  
**Project:** Sip & Pulse — Web Development, HCI & IM Final Project  
**Connection File:** `database/db_connect.php`

---

## a. Database Title

**Sip & Pulse Café Management Database (`sip_and_pulse_db`)**

---

## b. Brief Description of the Database Application

The **Sip & Pulse Café Management Database** is the central data store for the **Sip & Pulse** web application — a café ordering and administration system developed as a final project for Web Development, Human-Computer Interaction (HCI), and Information Management (IM).

The database runs on **MariaDB** through **XAMPP** and is accessed by PHP REST APIs that power two main interfaces:

| Interface | Purpose |
|-----------|---------|
| **Client Side** | Menu browsing, online ordering, customer reviews, and user sign-in |
| **Admin Side** | Menu management, live order queue, order history, review moderation, sales analytics, and multi-device access configuration |

### Core Functional Areas

| Function | Tables |
|----------|--------|
| Customer account storage | `users` |
| Administrator authentication and role management | `admin_users` |
| Food and beverage catalog | `menu_items` |
| Online and walk-in order processing | `orders` |
| Customer feedback and admin replies | `reviews` |
| Sales reporting, Power BI, and Google Sheets integration | `sales_analytics_rows`, `analytics_config`, `analytics_sync_log` |
| Responsive design and multi-device HCI configuration | `device_access_platforms`, `screen_breakpoints`, `site_access_urls` |

### Technology Stack

- **Backend:** PHP (mysqli)
- **Database:** MariaDB / MySQL
- **Local Environment:** XAMPP (Apache + MariaDB)
- **Analytics:** Power BI embed, Google Sheets sync via Apps Script
- **Frontend:** HTML, CSS, JavaScript (Client & Admin interfaces)

The schema is automatically created and maintained through `ensureDatabaseSchema()` in `db_connect.php`, which runs on every database connection to guarantee table consistency across environments.

---

## c. ERD & Database Schema

### Entity-Relationship Diagram (ERD)

```
┌─────────────────┐         ┌─────────────────┐
│     USERS       │         │  ADMIN_USERS    │
├─────────────────┤         ├─────────────────┤
│ PK id           │         │ PK id           │
│    name         │         │    username (UK)│
│ UK email        │         │    password     │
│    created_at   │         │    email        │
└────────┬────────┘         │    role         │
         │                  │    created_at   │
         │ (logical)        └─────────────────┘
         │ via customer_email
         ▼
┌─────────────────┐         ┌──────────────────────┐
│     ORDERS      │────────▶│ SALES_ANALYTICS_ROWS │
├─────────────────┤  1 : 1  ├──────────────────────┤
│ PK id           │         │ PK id                │
│    customer_name│         │ UK order_id          │
│    customer_email        │    order_num           │
│    items (JSON) │         │    order_date          │
│    total_amount │         │    customer_name       │
│    status       │         │    total_amount        │
│    fulfillment  │         │    status              │
│    order_num    │         │    payment_method      │
│    order_source │         │    is_anomaly          │
│    payment_method        │    anomaly_reason      │
│    refund_*     │         │    updated_at          │
│    created_at   │         └──────────────────────┘
└────────┬────────┘
         │ items reference menu (JSON snapshot)
         ▼
┌─────────────────┐
│   MENU_ITEMS    │
├─────────────────┤
│ PK id           │
│    name         │
│    category     │
│    description  │
│    price        │
│    image_url    │
│    available    │
│    item_type    │
│    created_at   │
└─────────────────┘

┌─────────────────┐   ┌──────────────────────┐   ┌─────────────────────┐
│    REVIEWS      │   │  ANALYTICS_CONFIG    │   │ ANALYTICS_SYNC_LOG  │
├─────────────────┤   ├──────────────────────┤   ├─────────────────────┤
│ PK id           │   │ PK id                │   │ PK id               │
│    name         │   │    powerbi_*         │   │    sync_status      │
│    rating       │   │    google_*          │   │    message          │
│    comment      │   │    last_sync_*       │   │    row_count        │
│    admin_reply  │   │    created_at        │   │    created_at       │
│    created_at   │   └──────────────────────┘   └─────────────────────┘
└─────────────────┘

┌────────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐
│ DEVICE_ACCESS_PLATFORMS│  │ SCREEN_BREAKPOINTS  │  │ SITE_ACCESS_URLS │
├────────────────────────┤  ├─────────────────────┤  ├──────────────────┤
│ PK id                  │  │ PK id               │  │ PK id            │
│ UK platform_key        │  │ UK breakpoint_key   │  │ UK url_key       │
│    platform_name       │  │    label            │  │    label         │
│    min_width           │  │    min_width        │  │    url_value     │
│    max_width           │  │    max_width        │  │    view_target   │
│    navigation_mode     │  │    css_class        │  └──────────────────┘
│    touch_target_min    │  └─────────────────────┘
│    is_enabled          │
└────────────────────────┘
```

**Legend:** PK = Primary Key, UK = Unique Key

### Relationship Summary

| Relationship | Type | Description |
|--------------|------|-------------|
| `orders` → `sales_analytics_rows` | One-to-One (logical) | Each order maps to one analytics row via `order_id` (UNIQUE) |
| `users` → `orders` | One-to-Many (logical) | Linked by `orders.customer_email` = `users.email` (no formal FK) |
| `menu_items` → `orders` | Many-to-Many (logical) | Order line items stored as JSON snapshot in `orders.items` |
| `reviews` | Standalone | Guest reviews allowed; no FK to `users` |
| HCI / config tables | Standalone | Reference data for responsive and device-access behavior |

> **Note:** The database uses **logical relationships** enforced in the application layer (PHP APIs). Formal `FOREIGN KEY` constraints are not declared in MySQL, which allows flexible data migration and seeding during development.

### Database Schema — Table List

| # | Table Name | Primary Key | Unique Keys | Record Purpose |
|---|------------|-------------|-------------|----------------|
| 1 | `users` | `id` | `email` | Registered client accounts |
| 2 | `admin_users` | `id` | `username` | Administrator and supervisor accounts |
| 3 | `menu_items` | `id` | — | Café menu catalog (food & drinks) |
| 4 | `orders` | `id` | — | Customer and walk-in orders |
| 5 | `reviews` | `id` | — | Customer ratings and feedback |
| 6 | `sales_analytics_rows` | `id` | `order_id` | Denormalized sales data for reporting |
| 7 | `analytics_config` | `id` | — | Power BI and Google Sheets integration settings |
| 8 | `analytics_sync_log` | `id` | — | Analytics sync audit trail |
| 9 | `device_access_platforms` | `id` | `platform_key` | HCI device/platform definitions |
| 10 | `screen_breakpoints` | `id` | `breakpoint_key` | Responsive screen breakpoint rules |
| 11 | `site_access_urls` | `id` | `url_key` | Local and production access URLs |

---

## d. Normalization Process

### Starting Point (Unnormalized Concept)

In an initial unnormalized design, all data could exist in a single flat table containing customer information, menu items, order line items, payment details, reviews, and admin replies. This would cause:

- **Repeating groups** (multiple items per order in one row)
- **Update anomalies** (changing a menu price would not update past orders)
- **Insert anomalies** (cannot add a menu item without an order)
- **Delete anomalies** (deleting an order could remove customer history)

### First Normal Form (1NF) — Atomic Values, No Repeating Groups

**Rules applied:**
- Data is split into separate tables per entity: `users`, `admin_users`, `menu_items`, `orders`, `reviews`
- Every column holds a **single atomic value** (e.g., one price, one status, one rating)
- Each row is uniquely identified by a primary key (`id`)

**Designed exception:**
- `orders.items` stores order line items as a **JSON text array**. This is a pragmatic denormalization that preserves an accurate snapshot of what was ordered at the time of purchase, even if menu prices change later.

### Second Normal Form (2NF) — Full Functional Dependency on Primary Key

**Rules applied:**
- All tables use a **single-column surrogate primary key** (`id`)
- No composite primary keys exist, so partial dependencies are eliminated by design
- Example: `menu_items.price` depends entirely on `menu_items.id`, not on any subset of a composite key

### Third Normal Form (3NF) — No Transitive Dependencies

**Rules applied:**
- Non-key attributes depend only on the primary key, not on other non-key attributes
- `menu_items.category` and `item_type` are direct attributes of the menu item
- `admin_users.role` is a direct attribute of the admin account
- `orders.status`, `payment_method`, and `fulfillment` belong directly to the order entity
- Configuration and lookup tables (`device_access_platforms`, `screen_breakpoints`, `site_access_urls`, `analytics_config`) are separated from transactional tables to eliminate redundancy

### Intentional Denormalization

| Table / Column | Normal Form | Justification |
|----------------|-------------|---------------|
| `users` | 3NF | Clean single-entity table |
| `admin_users` | 3NF | Clean single-entity table |
| `menu_items` | 3NF | Clean single-entity table |
| `orders` | 2NF* | `items` JSON column is a deliberate snapshot denormalization |
| `reviews` | 3NF | Standalone; guest reviews do not require a `users` FK |
| `sales_analytics_rows` | Denormalized | Reporting copy of `orders` for fast analytics and Google Sheets export |
| `analytics_config` | 3NF | Single-row configuration entity |
| `analytics_sync_log` | 3NF | Append-only audit log |
| `device_access_platforms` | 3NF | Independent reference/lookup table |
| `screen_breakpoints` | 3NF | Independent reference/lookup table |
| `site_access_urls` | 3NF | Independent reference/lookup table |

### Normalization Summary

The database is designed to **3NF** for all core entity tables. Two intentional denormalizations support real-world café operations:

1. **`orders.items` (JSON)** — Preserves historical order accuracy
2. **`sales_analytics_rows`** — Enables efficient reporting, anomaly detection, and external sync (Power BI / Google Sheets) without expensive joins at query time

---

## e. Data Dictionary

---

### Table 1: `users`
**Description:** Stores registered client (customer) accounts from the Client Side sign-in flow.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier for each user |
| `name` | VARCHAR(255) | NOT NULL | Full name of the customer |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | Email address used for identification and login |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Date and time the account was created |

---

### Table 2: `admin_users`
**Description:** Stores administrator and supervisor accounts for the Admin Side dashboard.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier for each admin |
| `username` | VARCHAR(100) | NOT NULL, UNIQUE | Login username |
| `password` | VARCHAR(255) | NOT NULL | Bcrypt-hashed password |
| `email` | VARCHAR(255) | NULL | Admin email address (used for login) |
| `role` | VARCHAR(50) | DEFAULT 'admin' | Access role: `admin`, `supervisor`, etc. |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation timestamp |

**Example accounts:** `admin@sipandpulse.com` (admin), `nica@sipandpulse.com` (supervisor)

---

### Table 3: `menu_items`
**Description:** Café menu catalog containing all food and beverage items displayed on the Client and Admin interfaces.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique menu item identifier |
| `name` | VARCHAR(255) | NOT NULL | Item name (e.g., Caramel Macchiato) |
| `category` | VARCHAR(100) | NOT NULL | Category key (espresso, burgers, pasta, etc.) |
| `description` | TEXT | NULL | Item description shown to customers |
| `price` | DECIMAL(10,2) | NOT NULL | Price in Philippine Peso (PHP) |
| `image_url` | VARCHAR(500) | DEFAULT '' | URL of the item image |
| `available` | TINYINT(1) | DEFAULT 1 | Availability flag: 1 = available, 0 = unavailable |
| `item_type` | VARCHAR(20) | DEFAULT 'food' | Item classification: `food` or `drink` |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Date the item was added to the menu |

---

### Table 4: `orders`
**Description:** Stores all customer orders (online) and walk-in/franchise orders placed through the Admin Side.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Internal order identifier |
| `customer_name` | VARCHAR(255) | NOT NULL | Name of the customer or walk-in guest |
| `customer_email` | VARCHAR(255) | DEFAULT '' | Customer email address |
| `items` | TEXT | NOT NULL | JSON array of ordered items (name, qty, price) |
| `total_amount` | DECIMAL(10,2) | NOT NULL | Total order amount in PHP |
| `status` | VARCHAR(50) | DEFAULT 'pending' | Order status: pending, preparing, ready, done, cancelled |
| `fulfillment` | VARCHAR(50) | DEFAULT 'pickup' | Fulfillment method: pickup or delivery |
| `delivery_location` | VARCHAR(255) | DEFAULT '' | Delivery address (if applicable) |
| `order_num` | VARCHAR(20) | DEFAULT '' | Display order number (e.g., O1001, F1001) |
| `order_source` | VARCHAR(5) | DEFAULT 'O' | O = Online order, F = Franchise/walk-in |
| `payment_method` | VARCHAR(80) | DEFAULT '' | Payment type: Cash, E-Wallet, GCash, Maya |
| `notes` | TEXT | NULL | Special instructions from the customer |
| `cancel_num` | VARCHAR(20) | DEFAULT '' | Cancellation reference number (e.g., C1001) |
| `refund_amount` | DECIMAL(10,2) | DEFAULT 0.00 | Amount refunded (if cancelled) |
| `refund_status` | VARCHAR(50) | DEFAULT '' | Refund status (e.g., refunded) |
| `cancelled_at` | TIMESTAMP | NULL | Timestamp when the order was cancelled |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Date and time the order was placed |

---

### Table 5: `reviews`
**Description:** Customer reviews and ratings submitted from the Client Side, with optional admin replies.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique review identifier |
| `name` | VARCHAR(255) | NOT NULL | Name of the reviewer |
| `rating` | TINYINT | NOT NULL | Star rating from 1 to 5 |
| `comment` | TEXT | NOT NULL | Review text content |
| `avatar` | VARCHAR(500) | DEFAULT '' | Profile image URL of the reviewer |
| `admin_reply` | TEXT | NULL | Administrator's response to the review |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Date and time the review was submitted |

---

### Table 6: `sales_analytics_rows`
**Description:** Denormalized sales data derived from the `orders` table, used for analytics dashboards, anomaly detection, and Google Sheets / Power BI synchronization.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Analytics row identifier |
| `order_id` | INT | NOT NULL, UNIQUE | Reference to `orders.id` |
| `order_num` | VARCHAR(20) | | Copy of order display number |
| `order_date` | DATETIME | NULL | Copy of order creation date/time |
| `customer_name` | VARCHAR(255) | | Copy of customer name |
| `customer_email` | VARCHAR(255) | | Copy of customer email |
| `total_amount` | DECIMAL(10,2) | DEFAULT 0.00 | Copy of order total |
| `status` | VARCHAR(50) | | Copy of order status |
| `payment_method` | VARCHAR(80) | | Copy of payment method |
| `fulfillment` | VARCHAR(50) | | Copy of fulfillment type |
| `order_source` | VARCHAR(5) | DEFAULT 'O' | O = Online, F = Franchise/walk-in |
| `item_count` | INT | DEFAULT 0 | Number of items in the order |
| `refund_amount` | DECIMAL(10,2) | DEFAULT 0.00 | Refund amount |
| `refund_status` | VARCHAR(50) | | Refund status |
| `is_anomaly` | TINYINT(1) | DEFAULT 0 | Anomaly flag: 1 = flagged, 0 = normal |
| `anomaly_reason` | VARCHAR(255) | | Description of detected anomaly |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update/sync timestamp |

---

### Table 7: `analytics_config`
**Description:** Stores integration settings for Power BI dashboards and Google Sheets sales synchronization.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Configuration record identifier |
| `powerbi_title` | VARCHAR(120) | NOT NULL | Title of the embedded Power BI report |
| `powerbi_embed_url` | VARCHAR(700) | NOT NULL | Power BI embed URL |
| `google_spreadsheet_id` | VARCHAR(120) | NOT NULL | Google Spreadsheet ID |
| `google_sheet_name` | VARCHAR(80) | DEFAULT 'Sales Records' | Target sheet tab name |
| `google_sheet_url` | VARCHAR(500) | NOT NULL | Full URL to the Google Spreadsheet |
| `google_apps_script_url` | VARCHAR(700) | DEFAULT '' | Google Apps Script web app endpoint |
| `service_account_email` | VARCHAR(255) | DEFAULT '' | Google service account email |
| `private_key_pem` | TEXT | NULL | Service account private key (PEM format) |
| `last_sync_at` | TIMESTAMP | NULL | Timestamp of last successful sync |
| `last_sync_status` | VARCHAR(40) | DEFAULT 'pending' | Status of last sync operation |
| `powerbi_tenant_id` | VARCHAR(80) | DEFAULT '' | Microsoft Power BI tenant ID |
| `powerbi_report_id` | VARCHAR(80) | DEFAULT '' | Power BI report ID |
| `powerbi_group_id` | VARCHAR(80) | DEFAULT '' | Power BI workspace (group) ID |
| `powerbi_dataset_id` | VARCHAR(80) | DEFAULT '' | Power BI dataset ID |
| `powerbi_client_id` | VARCHAR(80) | DEFAULT '' | OAuth client ID for Power BI API |
| `powerbi_client_secret` | VARCHAR(255) | DEFAULT '' | OAuth client secret |
| `powerbi_last_refresh_at` | TIMESTAMP | NULL | Last Power BI dataset refresh time |
| `powerbi_last_refresh_status` | VARCHAR(40) | DEFAULT 'pending' | Power BI refresh status |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Configuration creation timestamp |

---

### Table 8: `analytics_sync_log`
**Description:** Audit log recording each analytics synchronization attempt to Google Sheets or Power BI.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Log entry identifier |
| `sync_status` | VARCHAR(40) | DEFAULT '' | Result status: success, error, pending |
| `message` | TEXT | NULL | Detailed sync result message |
| `row_count` | INT | DEFAULT 0 | Number of rows synced in this operation |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Log entry timestamp |

---

### Table 9: `device_access_platforms`
**Description:** HCI reference table defining supported device platforms and their navigation requirements (touch targets, screen widths, navigation modes).

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Platform record identifier |
| `platform_key` | VARCHAR(40) | NOT NULL, UNIQUE | Short key: mobile, ios, desktop, tv, etc. |
| `platform_name` | VARCHAR(120) | NOT NULL | Human-readable platform name |
| `min_width` | INT | DEFAULT 0 | Minimum screen width in pixels |
| `max_width` | INT | DEFAULT 99999 | Maximum screen width in pixels |
| `client_path` | VARCHAR(255) | NOT NULL | Relative path to the Client Side page |
| `admin_path` | VARCHAR(255) | NOT NULL | Relative path to the Admin Side page |
| `navigation_mode` | VARCHAR(40) | DEFAULT 'standard' | Input mode: touch, pointer-keyboard, remote-dpad, etc. |
| `touch_target_min` | INT | DEFAULT 44 | Minimum recommended touch target size (px) |
| `is_enabled` | TINYINT(1) | DEFAULT 1 | Whether this platform profile is active |
| `sort_order` | INT | DEFAULT 0 | Display ordering in the admin UI |
| `notes` | TEXT | NULL | Additional HCI notes for the platform |

---

### Table 10: `screen_breakpoints`
**Description:** Defines responsive CSS breakpoints used for adaptive layout across screen sizes (HCI requirement).

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Breakpoint record identifier |
| `breakpoint_key` | VARCHAR(20) | NOT NULL, UNIQUE | Short key: xs, sm, md, lg, xl, tv |
| `label` | VARCHAR(80) | NOT NULL | Human-readable label (e.g., Mobile portrait) |
| `min_width` | INT | NOT NULL | Minimum viewport width in pixels |
| `max_width` | INT | NOT NULL | Maximum viewport width in pixels |
| `css_class` | VARCHAR(40) | DEFAULT '' | CSS class applied at this breakpoint (e.g., bp-md) |

---

### Table 11: `site_access_urls`
**Description:** Stores canonical local (XAMPP) and live (GitHub Pages) URLs for Client and Admin interfaces.

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | URL record identifier |
| `url_key` | VARCHAR(40) | NOT NULL, UNIQUE | Short key: local_client, live_admin, etc. |
| `label` | VARCHAR(120) | NOT NULL | Display label for the URL |
| `url_value` | VARCHAR(500) | NOT NULL | Full URL value |
| `view_target` | VARCHAR(20) | DEFAULT 'both' | Target view: client, admin, or both |

---

## Appendix: Current Database Statistics

| Table | Approximate Records |
|-------|---------------------|
| `admin_users` | 2 |
| `menu_items` | 53 |
| `orders` | 14 |
| `reviews` | 8 |
| `sales_analytics_rows` | 14 |
| `analytics_config` | 1 |
| `analytics_sync_log` | 17 |
| `device_access_platforms` | 6 |
| `screen_breakpoints` | 6 |
| `site_access_urls` | 4 |
| `users` | 0 |

---

*Document generated for the Sip & Pulse Final Project — Web Development, HCI & IM.*  
*Database: `sip_and_pulse_db` | Environment: XAMPP / MariaDB 10.4*
