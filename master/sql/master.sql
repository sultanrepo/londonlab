-- ============================================================
-- LondonLab Master Database
-- This is YOUR control database — not visible to any lab
-- ============================================================

CREATE DATABASE IF NOT EXISTS londonlab_master
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE londonlab_master;

-- ---- SUPER ADMINS (you and your team) ----
CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin','support','sales') DEFAULT 'support',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- SUBSCRIPTION PLANS ----
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly',
    max_users INT DEFAULT 3,
    max_patients_per_month INT DEFAULT 500,
    features JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- LABS (each customer) ----
CREATE TABLE IF NOT EXISTS labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(80) UNIQUE NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    owner_email VARCHAR(100) UNIQUE NOT NULL,
    owner_phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    gstin VARCHAR(20),
    logo VARCHAR(255),
    db_name VARCHAR(100) UNIQUE NOT NULL,
    plan_id INT,
    status ENUM('trial','active','suspended','cancelled') DEFAULT 'trial',
    trial_ends_at DATE,
    subscription_ends_at DATE,
    last_payment_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
);

-- ---- SUBSCRIPTIONS HISTORY ----
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- ---- BILLING INVOICES ----
CREATE TABLE IF NOT EXISTS billing_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) UNIQUE NOT NULL,
    lab_id INT NOT NULL,
    plan_id INT,
    amount DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','overdue','cancelled') DEFAULT 'pending',
    due_date DATE,
    paid_at DATETIME,
    payment_method ENUM('upi','bank_transfer','cash','card','cheque') DEFAULT 'upi',
    transaction_ref VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
);

-- ---- SUPPORT TICKETS ----
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    priority ENUM('low','medium','high','critical') DEFAULT 'medium',
    assigned_to INT,
    resolved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES super_admins(id) ON DELETE SET NULL
);

-- ---- ACTIVITY LOG ----
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    lab_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default super admin (password: SuperAdmin@123)
INSERT INTO super_admins (name, email, password, role) VALUES
('Super Admin', 'admin@londonlab.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');

-- Subscription Plans
INSERT INTO plans (name, slug, price, max_users, max_patients_per_month, features) VALUES
('Basic',      'basic',      999.00,  3,  500,  '{"whatsapp_reports":false,"custom_logo":false,"backup":"weekly","support":"email"}'),
('Pro',        'pro',        1999.00, 10, 2000, '{"whatsapp_reports":true,"custom_logo":true,"backup":"daily","support":"email_phone"}'),
('Enterprise', 'enterprise', 3999.00, 0,  0,   '{"whatsapp_reports":true,"custom_logo":true,"backup":"realtime","support":"dedicated"}');