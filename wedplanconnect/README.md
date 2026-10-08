# WedPlanConnect

**A Wedding Planning Management System for FMT Weddings & Events**
Capstone project by Team Clover: Codera, Tio, and Pilar. BS Information Systems, Cebu Technological University – Main Campus.

WedPlanConnect is a role-based web application built with Laravel, MySQL, Blade, and Tailwind CSS. It replaces FMT's manual spreadsheets and messaging with one platform for bookings, suppliers, tasks, inventory, payments, reports, a QR + OTP client status portal, and a FAQ chatbot.

---

## Tech stack (per manuscript, Development Phase)

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+ · Laravel 13 (MVC, Eloquent, middleware RBAC, scheduler) |
| Database | MySQL / MariaDB via XAMPP (phpMyAdmin) |
| Frontend | Blade templates · Tailwind CSS 4 (built with Vite, fonts self-hosted) · vanilla JS |
| QR codes | `simplesoftwareio/simple-qrcode` (SVG, 64-char hex token) |
| PDF export | `barryvdh/laravel-dompdf` (reports, contract summary) |
| Chatbot | Keyword-matched FAQ knowledge base + optional Google Gemini fallback |

---

## Setup (XAMPP + MySQL)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. In phpMyAdmin, create a database named `wedplanconnect` (collation `utf8mb4_unicode_ci`).
2. In this folder:
   ```bash
   composer install
   copy .env.example .env        # (cp on macOS/Linux) — then check the DB_* values
   php artisan key:generate
   php artisan migrate --seed
   npm install && npm run build  # only needed if you change CSS/JS; public/build is already compiled
   php artisan serve
   ```
3. Open http://localhost:8000.

Overdue tasks, upcoming-wedding counts, and balances are calculated live whenever a page loads, so they're correct even if no scheduler is running. The `tasks:flag-overdue` scheduler only keeps the stored `is_overdue` column in sync for record-keeping (`php artisan schedule:run` every minute via Windows Task Scheduler or cron).

### First login

`php artisan migrate --seed` creates **only the administrator account**, with no sample records, so every dashboard figure and report comes from data FMT enters itself.

- Email: `ADMIN_EMAIL` in `.env` (default `admin@fmtweddings.test`)
- Password: `ADMIN_PASSWORD` in `.env`, or the temporary `Password123` (change it under **My Account**)

The admin dashboard shows a setup checklist until planners, suppliers, inventory, and FAQ answers have been added.

### Sample data (demonstrations only)

For a defense demo, load fictional sample data into a **separate or throwaway** database:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Demo accounts use the password `Password123`: `admin@`, `planner@`, `client@`, `vendor@fmtweddings.test`. The sample FAQ answers are placeholders, not FMT's actual policies.

### Scanning QR codes with a real phone

1. Start the app with `C:\Capstone\start-wedplanconnect.bat`. It detects this PC's Wi-Fi address, saves it as `APP_URL` (the address inside every QR code), and listens on the network.
2. The first time, Windows Firewall asks about **php**. Click **Allow** for private networks.
3. The phone must be on the same Wi-Fi. If this PC's address changes, the planner's QR page shows a warning. Restart with the `.bat` file and reprint.
4. **Test mode:** while `MAIL_MAILER=log`, PIN emails aren't delivered. Admins and planners read them under **Test mode → Email outbox**. Once real SMTP email is configured, the outbox turns itself off.

### Trying the QR + OTP status portal

1. Log in as the planner, open a **confirmed** booking, and click **Print** under *Client QR status code* (or scan the QR with a phone on the same network).
2. The status link emails a 6-digit PIN to the couple. With the default `MAIL_MAILER=log`, the email (and PIN) is written to `storage/logs/laravel.log`.
3. Enter the PIN and the read-only status page opens. Set real SMTP credentials in `.env` for production.

---

## Modules → manuscript mapping

| # | Module | Use case | Where |
|---|---|---|---|
| 1 | User Management + RBAC, lockout after 5 failed logins | UC-01, UC-02 | `Auth/LoginController`, `Admin/UserController`, `EnsureRole` middleware |
| 2 | Booking Management, double-booking detection | UC-03 | `BookingController`, `Booking::findConflict()` |
| 3 | Supplier Management + client preferences | UC-04, UC-07 | `SupplierController`, `BookingSupplierController`, `Client/CatalogController` |
| 4 | Task Assignment & Monitoring, auto-overdue scheduler | UC-05 | `TaskController`, `routes/console.php` |
| 5 | Inventory Management, Model Observer | UC-06 | `InventoryController`, `Observers/InventoryItemObserver` |
| 6 | Payment & Contract Management, PDF contract | UC-08 | `PaymentController`, `pdf/contract.blade.php` |
| 7 | QR Code Status Tracking with OTP | UC-04, UC-10 | `BookingController@confirm`, `QrStatusController` |
| 8 | Chatbot / FAQ with Gemini fallback, session logs | UC-11 | `Services/ChatbotService`, `Admin/FaqController` |
| 9 | Reporting: 5 strategic + 5 operational, PDF export | UC-09 | `Services/ReportService`, `ReportController` |

Vendor portal: `Vendor/DashboardController` (assigned bookings, own profile, availability).

**Vendor products & wedding set-up** (added after the manuscript):
- Vendors list their products, supplies, and services (price, unit, optional photo, availability) under **My Products**. Admins and planners can do the same for suppliers without a login (**Suppliers → Products**). See `SupplierProductController`.
- Planners pick products for each wedding under **Set-up items** on the booking page, with quantity and notes. The vendor is assigned to the booking automatically. See `BookingProductController`.
- Couples browse each supplier's offerings in the catalog and see their chosen set-up on their dashboard and QR status page. Vendors see which of their products were ordered for each wedding.
- Photos are stored privately (`storage/app/private/product-images`) and served only to signed-in users.

## Security measures implemented (Security Requirements section)

- Route-level RBAC middleware returning HTTP 403; planners are scoped to their own bookings.
- Bcrypt hashing (`BCRYPT_ROUNDS=12`), time-limited password reset tokens, lockout after 5 consecutive failures (15 minutes).
- QR token: 64-char hex from `random_bytes(32)`. OTP: 6 digits, stored as a SHA-256 hash, 10-minute expiry, single use, max 5 attempts, throttled routes. The token is invalidated when a booking is cancelled.
- Soft deletes on every core table; `created_at` / `updated_at` audit timestamps; chatbot sessions logged.
- Eloquent parameterized queries, Blade auto-escaping, CSRF on all state-changing requests.
- Supplier contact details are hidden from client and vendor views; client contact details are hidden from vendors.
- For production: set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and serve over HTTPS (Let's Encrypt).

## Configuration

`config/wedplan.php` and `.env`:

| Key | Default | Purpose |
|---|---|---|
| `WEDPLAN_CONFLICT_SCOPE` | `date_venue` | `date_venue` (manuscript definition) or `date` (one wedding per day) |
| `GEMINI_API_KEY` | empty | Enables the Gemini fallback for questions the FAQ can't answer |
| `CHATBOT_THRESHOLD` | `0.5` | Keyword-match confidence required to answer from the FAQ |

## Tests

```bash
php artisan test
```

Tests run against a separate MySQL database, `wedplanconnect_test`, which is wiped on every run, so your real data is never touched. MySQL must be running in XAMPP. Create the test database once in phpMyAdmin (collation `utf8mb4_unicode_ci`) on a new machine.

The feature tests cover authentication and lockout, RBAC, double-booking, QR/OTP flow, inventory observer, payment balance and overpayment guard, the overdue scheduler, the chatbot, report access, and a render check of every screen per role.
