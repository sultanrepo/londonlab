# LondonLab — Lab Management SaaS Platform

A complete multi-tenant SaaS platform for managing multiple pathology laboratories.

---

## 📁 Project Structure

```
londonlab/
├── install/          ← Run once to install
├── master/           ← Core config + SQL templates
│   ├── config.php    ← Master DB + LabProvisioner classes
│   └── sql/
│       ├── master.sql        ← Your control database
│       └── lab_template.sql  ← Template for each new lab DB
├── superadmin/       ← YOUR admin panel (you only)
│   ├── login.php
│   ├── index.php     ← Dashboard
│   └── modules/
│       ├── labs/         ← Add/manage/suspend labs
│       ├── billing/      ← Invoices + mark paid
│       ├── subscriptions/← Subscription periods
│       ├── plans/        ← Manage pricing plans
│       └── reports/      ← Revenue analytics
└── lab_app/          ← Per-lab application
    ├── login.php
    ├── index.php     ← Lab dashboard
    └── modules/
        ├── patients/     ← Patient registration
        ├── doctors/      ← Referral doctors + commissions
        ├── orders/       ← Test orders + results
        ├── tests/        ← Test catalog
        ├── expenses/     ← Expense management
        ├── reports/      ← Financial reports
        ├── users/        ← Staff management
        └── settings/     ← Lab configuration
```

---

## 🚀 Installation

### Step 1 — Upload files
Place the `londonlab` folder in your `htdocs/` or `www/` directory.

### Step 2 — Run install wizard
Open in browser:
```
http://localhost/londonlab/install/
```

Fill in:
- MySQL host, username, password
- Master database name (e.g. `londonlab_master`)
- Platform base URL
- Your super admin email + password

Click **Install LondonLab** — it will:
- Create the master database and all tables
- Insert default subscription plans
- Create your super admin account
- Update `master/config.php` automatically

### Step 3 — Delete install folder
**⚠️ Important:** Delete `/install/` after setup for security.

### Step 4 — Login
```
Super Admin: http://localhost/londonlab/superadmin/
Lab App:     http://localhost/londonlab/lab_app/?lab={slug}
```

---

## 🏗️ Adding a New Lab

1. Login to Super Admin panel
2. Go to **Labs → Add New Lab**
3. Fill in lab details, owner email, password, select plan
4. Click **Provision Lab**

This automatically:
- Creates a dedicated database `londonlab_{slug}`
- Runs the lab template SQL (all tables + default tests)
- Creates the lab admin user
- Configures default settings
- Starts a 14-day trial

Lab owner logs in at:
```
http://localhost/londonlab/lab_app/login.php?lab={slug}
```

---

## 💰 Default Subscription Plans

| Plan       | Price    | Users | Patients/mo |
|------------|----------|-------|-------------|
| Basic      | ₹999/mo  | 3     | 500         |
| Pro        | ₹1,999/mo| 10    | 2,000       |
| Enterprise | ₹3,999/mo| ∞     | ∞           |

Manage plans at: **Super Admin → Manage Plans**

---

## 🔑 Default Credentials

**Super Admin** (after install — change immediately):
- Email: whatever you set during install
- Password: whatever you set during install

**Lab App** (demo lab — if seeded):
- Email: `admin@londonlab.com`
- Password: `password`

---

## 🗄️ Database Architecture

```
londonlab_master          ← Your control DB
├── super_admins
├── labs                ← All customer labs
├── plans               ← Subscription plans
├── subscriptions       ← Subscription history
├── billing_invoices    ← Payment invoices
└── support_tickets

londonlab_{slug}          ← Per-lab isolated DB
├── users
├── patients
├── doctors
├── test_categories
├── tests
├── orders
├── order_items
├── payments
├── doctor_commissions
├── expenses
└── settings
```

---

## 🛡️ Security Notes

- All pages use CSRF token validation
- Output buffering prevents headers-already-sent errors
- Sessions are isolated per lab via `lab_slug`
- Suspended labs cannot login
- Expired subscriptions auto-suspend

---

## 📞 Tech Stack

- **Backend:** Core PHP 8+, PDO MySQL
- **Frontend:** Bootstrap 5.3, Bootstrap Icons, Chart.js, DataTables
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Server:** Apache/Nginx with PHP 8+
