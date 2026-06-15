-- ============================================================
-- Lab Database Template
-- This SQL runs once per new lab signup to create their DB
-- The {DB_NAME} placeholder is replaced dynamically by PHP
-- ============================================================

-- NOTE: Database creation is handled by PHP provisioning script
-- This file contains only table structure + seed data

-- ---- USERS ----
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','receptionist','technician','accountant') DEFAULT 'receptionist',
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---- DOCTORS ----
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    specialty VARCHAR(100),
    clinic_name VARCHAR(150),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    commission_type ENUM('percentage','fixed') DEFAULT 'percentage',
    commission_value DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---- PATIENTS ----
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    age TINYINT UNSIGNED,
    gender ENUM('Male','Female','Other') DEFAULT 'Male',
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    referral_type ENUM('doctor','self') DEFAULT 'self',
    doctor_id INT NULL,
    referred_by VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- ---- TEST SUB-PARAMETERS (for panel tests like CBC, LFT, KFT, Lipid, Thyroid) ----
CREATE TABLE IF NOT EXISTS test_sub_parameters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    parameter_name VARCHAR(150) NOT NULL,
    short_name VARCHAR(50),
    normal_range_male VARCHAR(100),
    normal_range_female VARCHAR(100),
    unit VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- ---- TEST SUB-RESULTS (one row per sub-parameter per order item) ----
CREATE TABLE IF NOT EXISTS test_sub_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    sub_parameter_id INT NOT NULL,
    result_value VARCHAR(255),
    result_status ENUM('normal','abnormal','critical','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_parameter_id) REFERENCES test_sub_parameters(id) ON DELETE CASCADE
);

-- ---- TEST CATEGORIES ----
CREATE TABLE IF NOT EXISTS test_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- TESTS ----
CREATE TABLE IF NOT EXISTS tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    normal_range VARCHAR(100),
    unit VARCHAR(50),
    turnaround_hours INT DEFAULT 24,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES test_categories(id) ON DELETE SET NULL
);

-- ---- ORDERS ----
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(30) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    created_by INT,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    sample_collected_at DATETIME,
    status ENUM('pending','sample_collected','processing','completed','delivered','cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    doctor_discount DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---- ORDER ITEMS ----
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    test_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    result_value VARCHAR(255),
    result_status ENUM('normal','abnormal','critical','pending') DEFAULT 'pending',
    result_notes TEXT,
    completed_at DATETIME,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id)
);

-- ---- PAYMENTS ----
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('cash','card','upi','bank_transfer','insurance') DEFAULT 'cash',
    transaction_ref VARCHAR(100),
    status ENUM('pending','completed','failed','refunded') DEFAULT 'completed',
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ---- DOCTOR COMMISSIONS ----
CREATE TABLE IF NOT EXISTS doctor_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    order_id INT NOT NULL,
    order_amount DECIMAL(10,2) NOT NULL,
    commission_type ENUM('percentage','fixed') NOT NULL,
    commission_rate DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','cancelled') DEFAULT 'pending',
    paid_at DATETIME,
    payment_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ---- EXPENSES ----
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('reagents','equipment','salary','rent','utilities','maintenance','consumables','other') DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    vendor VARCHAR(150),
    invoice_no VARCHAR(100),
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','cheque') DEFAULT 'cash',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- SETTINGS ----
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- DEFAULT SEED DATA (inserted for every new lab)
-- ============================================================

-- Default test categories
INSERT INTO test_categories (name, description) VALUES
('Haematology',   'Blood cell count and related tests'),
('Biochemistry',  'Blood chemistry and metabolic tests'),
('Microbiology',  'Bacterial and infection tests'),
('Immunology',    'Immune system and allergy tests'),
('Hormones',      'Hormonal profile tests'),
('Urine Analysis','Urine routine and microscopy');

-- Default common tests
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(1,'CBC','Complete Blood Count',350,'See report','Multiple',4),
(1,'HB','Haemoglobin',120,'12-17 g/dL','g/dL',2),
(1,'ESR','Erythrocyte Sedimentation Rate',150,'0-20 mm/hr','mm/hr',4),
(2,'RBS','Random Blood Sugar',80,'70-140 mg/dL','mg/dL',1),
(2,'FBS','Fasting Blood Sugar',80,'70-100 mg/dL','mg/dL',2),
(2,'HBA1C','Glycated Haemoglobin HbA1c',550,'<5.7%','%',4),
(2,'LFT','Liver Function Test',650,'See report','Multiple',6),
(2,'KFT','Kidney Function Test',650,'See report','Multiple',6),
(2,'LIPID','Lipid Profile',650,'See report','Multiple',6),
(3,'WIDAL','Widal Test',200,'Negative','—',8),
(3,'MALARIA','Malaria Antigen',300,'Negative','—',2),
(4,'HIV','HIV 1&2 Antibody',400,'Non-reactive','—',4),
(4,'HBsAg','Hepatitis B Surface Antigen',350,'Non-reactive','—',4),
(5,'T3T4TSH','Thyroid Profile (T3,T4,TSH)',950,'See report','Multiple',8),
(5,'TSH','TSH',350,'0.5-5.0 mIU/L','mIU/L',6),
(6,'URINE','Urine Routine Microscopy',100,'See report','—',2);


-- ============================================================
-- SUB-PARAMETERS SEED DATA
-- Based on standard medical laboratory reference ranges
-- Source: CBC Excel format + NCBI/WHO reference ranges
-- ============================================================

-- ---- CBC (Complete Blood Count) Sub-Parameters ----
-- Get the test id for CBC using a stored approach
SET @cbc_id = (SELECT id FROM tests WHERE code='CBC');
SET @lft_id = (SELECT id FROM tests WHERE code='LFT');
SET @kft_id = (SELECT id FROM tests WHERE code='KFT');
SET @lip_id = (SELECT id FROM tests WHERE code='LIPID');
SET @tsh_id = (SELECT id FROM tests WHERE code='T3T4TSH');

-- CBC Sub-Parameters (from uploaded Excel + WHO reference ranges)
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
-- Total Leucocyte Count (WBC)
(@cbc_id, 'Total Leucocyte Count (WBC)', 'TLC/WBC',  '4000-11000', '4000-11000', '/cumm',    1),
-- Differential Leucocyte Count
(@cbc_id, 'Neutrophils',                 'NEUT',     '50-70',      '50-70',      '%',         2),
(@cbc_id, 'Lymphocytes',                 'LYMPH',    '20-40',      '20-40',      '%',         3),
(@cbc_id, 'Eosinophils',                 'EOS',      '02-06',      '02-06',      '%',         4),
(@cbc_id, 'Monocytes',                   'MONO',     '01-06',      '01-06',      '%',         5),
(@cbc_id, 'Basophils',                   'BASO',     '00-01',      '00-01',      '%',         6),
-- Red Blood Cell Parameters
(@cbc_id, 'Haemoglobin',                 'HGB',      '13.5-17.5',  '11.5-16.5',  'g/dL',      7),
(@cbc_id, 'RBC Count',                   'RBC',      '4.5-5.9',    '4.0-5.5',    'mill/µL',   8),
(@cbc_id, 'Haematocrit (HCT)',           'HCT',      '40-54',      '37-47',      '%',         9),
(@cbc_id, 'MCV',                         'MCV',      '76-96',      '76-96',      'fL',        10),
(@cbc_id, 'MCH',                         'MCH',      '27-34',      '27-34',      'pg',        11),
(@cbc_id, 'MCHC',                        'MCHC',     '32-36',      '32-36',      'g/dL',      12),
(@cbc_id, 'RDW-CV',                      'RDW-CV',   '10.0-16.0',  '10.0-16.0',  '%',         13),
(@cbc_id, 'RDW-SD',                      'RDW-SD',   '35-56',      '35-56',      'fL',        14),
-- Platelet Parameters
(@cbc_id, 'Platelet Count',              'PLT',      '1.4-4.0',    '1.4-4.0',    'lakh/cumm', 15),
(@cbc_id, 'MPV',                         'MPV',      '7.0-11.0',   '7.0-11.0',   'fL',        16),
(@cbc_id, 'PDW',                         'PDW',      '9.0-17.0',   '9.0-17.0',   '%',         17),
(@cbc_id, 'PCT',                         'PCT',      '0.10-0.50',  '0.10-0.50',  '%',         18),
(@cbc_id, 'P-LCR',                       'P-LCR',    '11-45',      '11-45',      '%',         19);

-- LFT Sub-Parameters
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@lft_id, 'Total Bilirubin',            'T.Bili',   '0.3-1.2',    '0.3-1.2',    'mg/dL',  1),
(@lft_id, 'Direct Bilirubin',           'D.Bili',   '0.0-0.3',    '0.0-0.3',    'mg/dL',  2),
(@lft_id, 'Indirect Bilirubin',         'I.Bili',   '0.2-0.9',    '0.2-0.9',    'mg/dL',  3),
(@lft_id, 'SGPT (ALT)',                 'ALT',      '7-56',       '7-56',       'U/L',    4),
(@lft_id, 'SGOT (AST)',                 'AST',      '5-40',       '5-40',       'U/L',    5),
(@lft_id, 'Alkaline Phosphatase (ALP)', 'ALP',      '44-147',     '33-98',      'U/L',    6),
(@lft_id, 'GGT',                        'GGT',      '9-48',       '9-48',       'U/L',    7),
(@lft_id, 'Total Protein',              'T.Prot',   '6.0-8.3',    '6.0-8.3',    'g/dL',   8),
(@lft_id, 'Albumin',                    'ALB',      '3.5-5.0',    '3.5-5.0',    'g/dL',   9),
(@lft_id, 'Globulin',                   'GLOB',     '2.0-3.5',    '2.0-3.5',    'g/dL',   10),
(@lft_id, 'A/G Ratio',                  'A/G',      '1.0-2.2',    '1.0-2.2',    'ratio',  11);

-- KFT Sub-Parameters
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@kft_id, 'Blood Urea',                 'UREA',     '15-40',      '15-40',      'mg/dL',  1),
(@kft_id, 'Serum Creatinine',           'CREAT',    '0.7-1.3',    '0.6-1.1',    'mg/dL',  2),
(@kft_id, 'Uric Acid',                  'UA',       '3.4-7.0',    '2.4-6.0',    'mg/dL',  3),
(@kft_id, 'Sodium (Na+)',               'Na',       '135-145',    '135-145',    'mEq/L',  4),
(@kft_id, 'Potassium (K+)',             'K',        '3.5-5.0',    '3.5-5.0',    'mEq/L',  5),
(@kft_id, 'Chloride (Cl-)',             'Cl',       '98-106',     '98-106',     'mEq/L',  6),
(@kft_id, 'Bicarbonate (HCO3-)',        'HCO3',     '22-29',      '22-29',      'mEq/L',  7),
(@kft_id, 'Calcium',                    'Ca',       '8.5-10.5',   '8.5-10.5',   'mg/dL',  8),
(@kft_id, 'Phosphorus',                 'Phos',     '2.5-4.5',    '2.5-4.5',    'mg/dL',  9);

-- Lipid Profile Sub-Parameters
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@lip_id, 'Total Cholesterol',          'TC',       '<200',       '<200',       'mg/dL',  1),
(@lip_id, 'LDL Cholesterol',            'LDL',      '<100',       '<100',       'mg/dL',  2),
(@lip_id, 'HDL Cholesterol',            'HDL',      '>40',        '>50',        'mg/dL',  3),
(@lip_id, 'Triglycerides',              'TG',       '<150',       '<150',       'mg/dL',  4),
(@lip_id, 'VLDL',                       'VLDL',     '5-30',       '5-30',       'mg/dL',  5),
(@lip_id, 'TC/HDL Ratio',               'TC/HDL',   '<5.0',       '<4.5',       'ratio',  6),
(@lip_id, 'LDL/HDL Ratio',              'LDL/HDL',  '<3.5',       '<3.5',       'ratio',  7);

-- Thyroid Profile Sub-Parameters
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@tsh_id, 'T3 (Triiodothyronine)',      'T3',       '60-200',     '60-200',     'ng/dL',  1),
(@tsh_id, 'T4 (Thyroxine)',             'T4',       '4.5-12.5',   '4.5-12.5',   'µg/dL',  2),
(@tsh_id, 'TSH',                        'TSH',      '0.45-4.50',  '0.45-4.50',  'mIU/L',  3);
