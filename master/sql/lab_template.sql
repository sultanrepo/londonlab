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

-- ---- TEST SUB-PARAMETERS (for panel tests like CBC, LFT, KFT, Lipid, Thyroid) ----
-- Moved AFTER tests table (FK dependency: test_id -> tests.id)
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
-- Moved AFTER order_items AND test_sub_parameters (FK dependencies on both)
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
-- AIMLTA, SIWAN — Price List Import (effective 01-01-2025)
-- Categories, Tests, and Sub-Parameters
-- Reference ranges sourced from Lal PathLabs / Thyrocare / NCBI /
-- standard clinical laboratory references.
-- ============================================================

-- ============================================================
-- 1. TEST CATEGORIES
-- (Replaces/extends default 6 categories — adds Clinical Pathology)
-- ============================================================
INSERT INTO test_categories (name, description) VALUES
('Haematology',        'Blood cell count and related tests'),
('Biochemistry',       'Blood chemistry and metabolic tests'),
('Microbiology',       'Bacterial and infection tests'),
('Immuno-Serology',    'Immune system, infection markers and serology tests'),
('Hormones',           'Hormonal profile tests'),
('Clinical Pathology', 'Urine, stool, semen and body fluid analysis');

-- Capture category IDs for use below
SET @cat_haem  = (SELECT id FROM test_categories WHERE name='Haematology');
SET @cat_bio   = (SELECT id FROM test_categories WHERE name='Biochemistry');
SET @cat_micro = (SELECT id FROM test_categories WHERE name='Microbiology');
SET @cat_immu  = (SELECT id FROM test_categories WHERE name='Immuno-Serology');
SET @cat_horm  = (SELECT id FROM test_categories WHERE name='Hormones');
SET @cat_cp    = (SELECT id FROM test_categories WHERE name='Clinical Pathology');

-- ============================================================
-- 2. TESTS — HAEMATOLOGY
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_haem,'TLCDLC',   'TLC, DLC',                       200,'Multiple','Multiple',4),
(@cat_haem,'HB2',      'Haemoglobin (HB)',               100,'13.0-17.0 (M) / 12.0-15.5 (F)','g/dL',2),
(@cat_haem,'ESR2',     'Erythrocyte Sedimentation Rate',  100,'0-15 (M) / 0-20 (F)','mm/hr',4),
(@cat_haem,'GBPPS',    'GBP / Peripheral Smear',          300,'See report','—',2),
(@cat_haem,'CBC2',     'Complete Blood Count (CBC)',      400,'See report','Multiple',4),
(@cat_haem,'RBCCNT',   'RBC Count',                       200,'4.5-5.5 (M) / 4.0-5.0 (F)','mill/µL',2),
(@cat_haem,'PLTCNT',   'Platelet Count',                  250,'1.5-4.5','lakh/cumm',2),
(@cat_haem,'AEC',      'Absolute Eosinophil Count',       300,'40-440','/cumm',4),
(@cat_haem,'RETIC',    'Reticulocyte Count',              300,'0.5-2.5','%',4),
(@cat_haem,'BTCT',     'Bleeding Time / Clotting Time',   200,'BT: 1-6 / CT: 3-9','min',1),
(@cat_haem,'MPSLIDE',  'Malarial Parasite (Slide)',       200,'Negative','—',2),
(@cat_haem,'BGABORH',  'Blood Group (ABO, Rh)',           100,'—','—',1);

-- ============================================================
-- 3. TESTS — BIOCHEMISTRY
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_bio,'BSUGAR',   'Blood Sugar (Each Sample)',        60,'70-110','mg/dL',1),
(@cat_bio,'UREA2',    'Urea',                            250,'15-40','mg/dL',4),
(@cat_bio,'CREAT2',   'Creatinine',                      250,'0.6-1.3','mg/dL',4),
(@cat_bio,'BILITD',   'Bilirubin (Total & Direct)',      250,'See report','Multiple',4),
(@cat_bio,'SGPT2',    'SGPT (ALT)',                      250,'7-56','U/L',4),
(@cat_bio,'SGOT2',    'SGOT (AST)',                      250,'5-40','U/L',4),
(@cat_bio,'ALP2',     'Alkaline Phosphatase (ALP)',      250,'44-147','U/L',4),
(@cat_bio,'TPAGR',    'Total Protein (A:G Ratio)',       400,'See report','Multiple',4),
(@cat_bio,'SALB',     'S. Albumin',                      200,'3.5-5.0','g/dL',4),
(@cat_bio,'SPROT',    'S. Protein',                      200,'6.0-8.3','g/dL',4),
(@cat_bio,'SCAL',     'Calcium',                         400,'8.5-10.5','mg/dL',4),
(@cat_bio,'ICA',      'Ionised Calcium (iCa)',           600,'1.10-1.35','mmol/L',6),
(@cat_bio,'SNA',      'Sodium (Na+)',                    300,'135-145','mEq/L',4),
(@cat_bio,'SK',       'Potassium (K+)',                  300,'3.5-5.0','mEq/L',4),
(@cat_bio,'SCL',      'Chloride (Cl-)',                  300,'98-106','mEq/L',4),
(@cat_bio,'SELECTRO', 'S. Electrolyte (Na/K/Cl)',        800,'See report','Multiple',4),
(@cat_bio,'TCHOL',    'Total Cholesterol',               300,'<200','mg/dL',4),
(@cat_bio,'TRIG',     'Triglyceride',                    300,'<150','mg/dL',4),
(@cat_bio,'HDL2',     'HDL',                             300,'>40 (M) / >50 (F)','mg/dL',4),
(@cat_bio,'SAMYL',    'S. Amylase',                      700,'28-100','U/L',6),
(@cat_bio,'SLIP',     'S. Lipase',                       800,'13-60','U/L',6),
(@cat_bio,'PTINR2',   'Prothrombin Time (PT, INR)',      600,'See report','Multiple',6),
(@cat_bio,'APTT2',    'APTT',                            700,'25-35','sec',6),
(@cat_bio,'LFT2',     'L.F.T. (Liver Function Test)',    800,'See report','Multiple',6),
(@cat_bio,'KFT2',     'K.F.T. (Kidney Function Test)',   700,'Multiple','Multiple',2),
(@cat_bio,'KFTELEC',  'K.F.T. With Electrolyte',        1200,'Multiple','Multiple',4),
(@cat_bio,'LIPID2',   'Lipid Profile',                   800,'See report','Multiple',6),
(@cat_bio,'URICACID', 'Uric Acid',                       300,'3.4-7.0 (M) / 2.4-6.0 (F)','mg/dL',4),
(@cat_bio,'BSR',      'Blood Sugar Randon',                50,'70-140','mg/dL',1),
(@cat_bio,'BSF',      'Blood Sugar Fasting',                50,'70-110','mg/dL',1),
(@cat_bio,'BSPP',     'Blood Sugar Post-Prandial',          50,'70-140','mg/dL',1);

-- ============================================================
-- 4. TESTS — IMMUNO-SEROLOGY
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_immu,'RAFACT',  'RA Factor (Quantitative)',         600,'<14','IU/mL',6),
(@cat_immu,'ASOTITRE','ASO Titre (Quantitative)',         600,'<200','IU/mL',6),
(@cat_immu,'CRPQ',    'C-RP (Quantitative)',              500,'<6','mg/L',4),
(@cat_immu,'HIV12',   'HIV I & II — Rapid',               400,'Non-reactive','—',2),
(@cat_immu,'HBSAGR',  'HBsAg — Rapid',                    300,'Non-reactive','—',2),
(@cat_immu,'HCVR',    'HCV — Rapid',                      500,'Non-reactive','—',2),
(@cat_immu,'TPHAR',   'TPHA — Rapid',                     300,'Non-reactive','—',2),
(@cat_immu,'KALAAZAR','Kala-azar — Rapid',                700,'Non-reactive','—',4),
(@cat_immu,'MPAG',    'MP (Antigen) — Rapid',             400,'Negative','—',2),
(@cat_immu,'MFR',     'MF (Microfilaria) — Rapid',        700,'Negative','—',4),
(@cat_immu,'TYPHIDOT','Typhi Dot — Rapid',                500,'Negative','—',4),
(@cat_immu,'CHIKUNG', 'Chikunguniya — Rapid',            1000,'Negative','—',6),
(@cat_immu,'DENGCOMB','Dengue Combo Panel — Rapid',      1000,'Negative','—',6),
(@cat_immu,'DENGNS1', 'Dengue NS1 Antigen — Rapid',       800,'Negative','—',4),
(@cat_immu,'UPT',     'Pregnancy Test (UPT)',             100,'Negative','—',1),
(@cat_immu,'ALDEHYDE','Aldehyde Test',                    300,'Negative','—',4),
(@cat_immu,'WIDAL2',  'Widal Test',                       300,'Negative / Titre <1:80','—',4),
(@cat_immu,'MTTEST',  'MT (Mantoux) Test',                300,'<10mm induration','mm',72),
(@cat_immu,'VDRLRPR', 'VDRL / RPR',                       200,'Non-reactive','—',4),
(@cat_immu,'T3T4TSH2','T3, T4, TSH (Thyroid Profile)',    700,'See report','Multiple',8),
(@cat_immu,'FT3FT4TSH','FT3, FT4, TSH',                  1000,'See report','Multiple',8);

-- ============================================================
-- 5. TESTS — HORMONES
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_horm,'TSH2',    'TSH',                              300,'0.45-4.50','mIU/L',6);

-- ============================================================
-- 6. TESTS — CLINICAL PATHOLOGY
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_cp,'SEMEN',     'Semen Analysis',                   500,'See report','Multiple',4),
(@cat_cp,'URINER',    'Urine Routine',                    300,'See report','—',2),
(@cat_cp,'BILESALT',  'Bile Salt',                        200,'Negative','—',2),
(@cat_cp,'BILEPIG',   'Bile Pigment',                     200,'Negative','—',2),
(@cat_cp,'UROBILI',   'Urobilinogen',                     200,'0.2-1.0','mg/dL',2),
(@cat_cp,'CHYLE',     'Chyle Test',                       300,'Negative','—',2),
(@cat_cp,'URKETONE',  'Urine for Ketone',                 300,'Negative','—',2),
(@cat_cp,'STOOLRE',   'Stool R/E',                        500,'See report','—',4),
(@cat_cp,'OCCBLOOD',  'Occult Blood (Stool / Urine)',     400,'Negative','—',2),
(@cat_cp,'REDSUGAR',  'Reducing Sugar',                   200,'Negative','—',2);

-- ============================================================
-- 7. TESTS — MICROBIOLOGY
-- ============================================================
INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES
(@cat_micro,'CULURINE','Culture — Urine',                  500,'No growth','—',48),
(@cat_micro,'CULSTOOL','Culture — Stool',                  600,'No growth','—',72),
(@cat_micro,'CULTHROAT','Culture — Throat Swab',           600,'No growth','—',48),
(@cat_micro,'CULPUS', 'Culture — Pus',                     600,'No growth','—',48),
(@cat_micro,'CULVAG', 'Culture — Vaginal Swab',            600,'No growth','—',48),
(@cat_micro,'AFBSKIN','Skin/Sputum for AFB',               600,'Negative','—',24);

-- ============================================================
-- 8. SUB-PARAMETERS — PANEL TESTS
-- (test_sub_parameters table must already exist after `tests`,
--  per the lab_template.sql fix.)
-- ============================================================
SET @tlcdlc_id = (SELECT id FROM tests WHERE code='TLCDLC');
SET @gbpps_id  = (SELECT id FROM tests WHERE code='GBPPS');
SET @cbc2_id   = (SELECT id FROM tests WHERE code='CBC2');
SET @bilitd_id = (SELECT id FROM tests WHERE code='BILITD');
SET @tpagr_id  = (SELECT id FROM tests WHERE code='TPAGR');
SET @select_id = (SELECT id FROM tests WHERE code='SELECTRO');
SET @ptinr2_id = (SELECT id FROM tests WHERE code='PTINR2');
SET @lft2_id   = (SELECT id FROM tests WHERE code='LFT2');
SET @kft2_id   = (SELECT id FROM tests WHERE code='KFT2');
SET @kftel_id  = (SELECT id FROM tests WHERE code='KFTELEC');
SET @lipid2_id = (SELECT id FROM tests WHERE code='LIPID2');
SET @t3t4_id   = (SELECT id FROM tests WHERE code='T3T4TSH2');
SET @ft3ft4_id = (SELECT id FROM tests WHERE code='FT3FT4TSH');
SET @semen_id  = (SELECT id FROM tests WHERE code='SEMEN');
SET @urine_id  = (SELECT id FROM tests WHERE code='URINER');
SET @stool_id  = (SELECT id FROM tests WHERE code='STOOLRE');

-- ── TLC, DLC ──────────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@tlcdlc_id, 'Total Leucocyte Count (WBC)', 'TLC/WBC', '4000-11000', '4000-11000', '/cumm', 1),
(@tlcdlc_id, 'Neutrophils',                 'NEUT',    '40-70',      '40-70',      '%',     2),
(@tlcdlc_id, 'Lymphocytes',                 'LYMPH',   '20-40',      '20-40',      '%',     3),
(@tlcdlc_id, 'Eosinophils',                 'EOS',     '01-06',      '01-06',      '%',     4),
(@tlcdlc_id, 'Monocytes',                   'MONO',    '02-10',      '02-10',      '%',     5),
(@tlcdlc_id, 'Basophils',                   'BASO',    '00-02',      '00-02',      '%',     6);

-- ── GBP / Peripheral Smear ────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@gbpps_id, 'Total Count of WBC', 'WBC',  '4000-11000', '4000-11000', '/cmm',  0),
(@gbpps_id, 'Polymorphs',         'PLYM', '55-70',      '55-70',      '%',     1),
(@gbpps_id, 'Lymphocytes',        'LYMP', '25-30',      '25-30',      '%',     2),
(@gbpps_id, 'Eosinophils',        'EOSI', '02-06',      '02-06',      '%',     3),
(@gbpps_id, 'Monocytes',          'MONO', '01-06',      '01-06',      '%',     4),
(@gbpps_id, 'Basophils',          'BASO', '00-01',      '00-01',      '%',     5),
(@gbpps_id, 'Haemoglobin',        'HGB',  '11-15',      '11-15',      'g/dL',  6);

-- ── CBC (Complete Blood Count) ────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@cbc2_id, 'Total Leucocyte Count (WBC)', 'TLC/WBC',  '4000-11000', '4000-11000', '/cumm',    1),
(@cbc2_id, 'Neutrophils',                 'NEUT',     '40-70',      '40-70',      '%',        2),
(@cbc2_id, 'Lymphocytes',                 'LYMPH',    '20-40',      '20-40',      '%',        3),
(@cbc2_id, 'Eosinophils',                 'EOS',      '01-06',      '01-06',      '%',        4),
(@cbc2_id, 'Monocytes',                   'MONO',     '02-10',      '02-10',      '%',        5),
(@cbc2_id, 'Basophils',                   'BASO',     '00-02',      '00-02',      '%',        6),
(@cbc2_id, 'Haemoglobin',                 'HGB',      '13.0-17.0',  '12.0-15.5',  'g/dL',     7),
(@cbc2_id, 'RBC Count',                   'RBC',      '4.5-5.5',    '4.0-5.0',    'mill/µL',  8),
(@cbc2_id, 'Haematocrit (PCV)',           'PCV',      '40-50',      '36-46',      '%',        9),
(@cbc2_id, 'Mean Corpuscular Volume (MCV)', 'MCV',    '83-101',     '83-101',     'fL',       10),
(@cbc2_id, 'Mean Corpuscular Hemoglobin (MCH)', 'MCH', '27-32',     '27.0-32.0',  'pg',       11),
(@cbc2_id, 'Mean Corp. Hemo. Conc (MCHC)', 'MCHC',    '31.5-34.5',  '31.5-34.5',  'g/dL',     12),
(@cbc2_id, 'Red Cell Distribution Width - SD (RDW-SD)', 'RDW-SD', '39.0-46.0', '39.0-46.0', 'fL', 13),
(@cbc2_id, 'Red Cell Distribution Width (RDW-CV)', 'RDW-CV', '11.6-14.0', '11.6-14.0', '%',  14),
(@cbc2_id, 'Platelet Count',              'PLT',      '150-410',    '150-410',    'lakh/cumm',15),
(@cbc2_id, 'Mean Platelet Volume (MPV)',  'MPV',      '6.5-12.0',   '6.5-12.0',   'fL',       16),
(@cbc2_id, 'Platelet Distribution Width (PDW)', 'PDW', '9.6-15.2',  '9.6-15.2',   'fL',       17),
(@cbc2_id, 'Platelet to Large Cell Ratio (PLCR)', 'PLCR', '19.7-42.4', '19.7-42.4', '%',       18),
(@cbc2_id, 'Plateletcrit (PCT)',          'PCT',      '0.19-0.39',  '0.19-0.39',  '%',        19),
(@cbc2_id, 'Haemoglobin %',               'HB%',      '91-119',     '84-108.5',   '%',        20);

-- ── Bilirubin (Total & Direct) ────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@bilitd_id, 'Total Bilirubin',    'T.BILI', '0.3-1.2', '0.3-1.2', 'mg/dL', 1),
(@bilitd_id, 'Direct Bilirubin',   'D.BILI', '0.0-0.3', '0.0-0.3', 'mg/dL', 2),
(@bilitd_id, 'Indirect Bilirubin', 'I.BILI', '0.2-0.9', '0.2-0.9', 'mg/dL', 3);

-- ── Total Protein (A:G Ratio) ─────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@tpagr_id, 'Total Protein', 'T.PROT', '6.0-8.3', '6.0-8.3', 'g/dL', 1),
(@tpagr_id, 'Albumin',       'ALB',    '3.5-5.0', '3.5-5.0', 'g/dL', 2),
(@tpagr_id, 'Globulin',      'GLOB',   '2.0-3.5', '2.0-3.5', 'g/dL', 3),
(@tpagr_id, 'A:G Ratio',     'A:G',    '0.8-2.0', '0.8-2.0', 'ratio',4);

-- ── S. Electrolyte (Na/K/Cl) ──────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@select_id, 'Sodium (Na+)',    'Na', '135-145', '135-145', 'mEq/L', 1),
(@select_id, 'Potassium (K+)',  'K',  '3.5-5.0', '3.5-5.0', 'mEq/L', 2),
(@select_id, 'Chloride (Cl-)',  'Cl', '98-106',  '98-106',  'mEq/L', 3);

-- ── Prothrombin Time (PT, INR) ────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@ptinr2_id, 'Prothrombin Time (Test)', 'PT TEST',  '11-13.5', '11-13.5', 'sec', 1),
(@ptinr2_id, 'Control Plasma',          'CONTROL',  '11.0-14.0','11.0-14.0','sec', 2),
(@ptinr2_id, 'Prothrombin Ratio',       'PT RATIO', '0.8-1.2', '0.8-1.2', ':1',  3),
(@ptinr2_id, 'INR',                     'INR',      '0.8-1.2', '0.8-1.2', '—',   4);

-- ── LFT (Liver Function Test) ─────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@lft2_id, 'Total Bilirubin',            'T.BILI', '0.3-1.2',  '0.3-1.2',  'mg/dL', 1),
(@lft2_id, 'Direct Bilirubin',           'D.BILI', '0.0-0.3',  '0.0-0.3',  'mg/dL', 2),
(@lft2_id, 'Indirect Bilirubin',         'I.BILI', '0.2-0.9',  '0.2-0.9',  'mg/dL', 3),
(@lft2_id, 'SGPT (ALT)',                 'ALT',    '7-56',     '7-56',     'U/L',   4),
(@lft2_id, 'SGOT (AST)',                 'AST',    '5-40',     '5-40',     'U/L',   5),
(@lft2_id, 'Alkaline Phosphatase (ALP)', 'ALP',    '44-147',   '33-98',    'U/L',   6),
(@lft2_id, 'Total Protein',              'T.PROT', '6.0-8.3',  '6.0-8.3',  'g/dL',  7),
(@lft2_id, 'Albumin',                    'ALB',    '3.5-5.0',  '3.5-5.0',  'g/dL',  8),
(@lft2_id, 'Globulin',                   'GLOB',   '2.0-3.5',  '2.0-3.5',  'g/dL',  9),
(@lft2_id, 'A/G Ratio',                  'A/G',    '0.8-2.0',  '0.8-2.0',  'ratio', 10);

-- ── KFT (Kidney Function Test) ────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@kft2_id, 'Blood Urea',       'UREA',  '15-40',   '15-40',   'mg/dL', 1),
(@kft2_id, 'BUN',              'BUN',   '6-21',    '6-21',    'mg/dL', 2),
(@kft2_id, 'Serum Creatinine', 'CREAT', '0.7-1.3', '0.6-1.1', 'mg/dL', 3),
(@kft2_id, 'Uric Acid',        'UA',    '3.4-7.0', '2.4-6.0', 'mg/dL', 4),
(@kft2_id, 'Calcium',          'Ca',    '8.5-10.5','8.5-10.5','mg/dL', 4),
(@kft2_id, 'Phosphorus',       'Phos',  '2.5-4.5', '2.5-4.5', 'mg/dL', 5);

-- ── KFT With Electrolyte ──────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@kftel_id, 'Blood Urea',       'UREA',  '15-40',    '15-40',    'mg/dL', 1),
(@kftel_id, 'Serum Creatinine', 'CREAT', '0.7-1.3',  '0.6-1.1',  'mg/dL', 2),
(@kftel_id, 'BUN',              'BUN',   '6-21',     '6-21',     'mg/dL', 2),
(@kftel_id, 'Uric Acid',        'UA',    '3.4-7.0',  '2.4-6.0',  'mg/dL', 3),
(@kftel_id, 'Calcium',          'Ca',    '8.5-10.5', '8.5-10.5', 'mg/dL', 4),
(@kftel_id, 'Phosphorus',       'Phos',  '2.5-4.5',  '2.5-4.5',  'mg/dL', 5),
(@kftel_id, 'Sodium (Na+)',     'Na',    '135-145',  '135-145',  'mEq/L', 6),
(@kftel_id, 'Potassium (K+)',   'K',     '3.5-5.0',  '3.5-5.0',  'mEq/L', 7),
(@kftel_id, 'Chloride (Cl-)',   'Cl',    '98-106',   '98-106',   'mEq/L', 8);

-- ── Lipid Profile ──────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@lipid2_id, 'Total Cholesterol', 'TC',      '<200',  '<200',  'mg/dL', 1),
(@lipid2_id, 'LDL Cholesterol',   'LDL',     '<100',  '<100',  'mg/dL', 2),
(@lipid2_id, 'HDL Cholesterol',   'HDL',     '>40',   '>50',   'mg/dL', 3),
(@lipid2_id, 'Triglycerides',     'TG',      '<150',  '<150',  'mg/dL', 4),
(@lipid2_id, 'VLDL',              'VLDL',    '5-30',  '5-30',  'mg/dL', 5),
(@lipid2_id, 'TC/HDL Ratio',      'TC/HDL',  '<5.0',  '<4.5',  'ratio', 6),
(@lipid2_id, 'LDL/HDL Ratio',     'LDL/HDL', '<3.5',  '<3.5',  'ratio', 7);

-- ── T3, T4, TSH (Thyroid Profile) ─────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@t3t4_id, 'T3 (Triiodothyronine)', 'T3',  '60-200',    '60-200',    'ng/dL', 1),
(@t3t4_id, 'T4 (Thyroxine)',        'T4',  '4.5-12.5',  '4.5-12.5',  'µg/dL', 2),
(@t3t4_id, 'TSH',                   'TSH', '0.45-4.50', '0.45-4.50', 'mIU/L', 3);

-- ── FT3, FT4, TSH ──────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@ft3ft4_id, 'Free T3 (FT3)', 'FT3', '1.71-3.71', '1.71-3.71', 'pg/mL', 1),
(@ft3ft4_id, 'Free T4 (FT4)', 'FT4', '0.70-1.48', '0.70-1.48', 'ng/dL', 2),
(@ft3ft4_id, 'TSH',           'TSH', '0.45-4.50', '0.45-4.50', 'mIU/L', 3);

-- ── Semen Analysis ─────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@semen_id, 'Volume',          'VOL',     '1.5-5.0',  '1.5-5.0',  'mL',     1),
(@semen_id, 'Liquefaction Time','LIQ',    '<30',      '<30',      'min',    2),
(@semen_id, 'pH',              'PH',      '7.2-8.0',  '7.2-8.0',  '—',      3),
(@semen_id, 'Sperm Count',     'COUNT',   '>=15',     '>=15',     'mill/mL',4),
(@semen_id, 'Sperm Motility',  'MOTILITY','>=40',     '>=40',     '%',      5),
(@semen_id, 'Normal Morphology','MORPH',  '>=4',      '>=4',      '%',      6);

-- ── Urine Routine ──────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@urine_id, 'Colour',     'COLOUR',  'Pale Yellow', 'Pale Yellow', '—',  1),
(@urine_id, 'Appearance', 'APPEAR',  'Clear',       'Clear',       '—',  2),
(@urine_id, 'pH',         'PH',      '4.5-8.0',     '4.5-8.0',     '—',  3),
(@urine_id, 'Sp. Gravity','SPGR',    '1.005-1.030', '1.005-1.030', '—',  4),
(@urine_id, 'Protein',    'PROT',    'Nil',         'Nil',         '—',  5),
(@urine_id, 'Glucose',    'GLU',     'Nil',         'Nil',         '—',  6),
(@urine_id, 'Pus Cells',  'PUS',     '0-5',         '0-5',         '/hpf',7),
(@urine_id, 'RBC',        'RBC',     '0-2',         '0-2',         '/hpf',8),
(@urine_id, 'Epithelial Cells', 'EPITH', 'Occasional','Occasional','/hpf',9);

-- ── Stool R/E ──────────────────────────────────────────────
INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES
(@stool_id, 'Colour',       'COLOUR', 'Brown',   'Brown',   '—',   1),
(@stool_id, 'Consistency',  'CONSIS', 'Formed',  'Formed',  '—',   2),
(@stool_id, 'Mucus',        'MUCUS',  'Absent',  'Absent',  '—',   3),
(@stool_id, 'Blood',        'BLOOD',  'Absent',  'Absent',  '—',   4),
(@stool_id, 'Ova/Cysts',    'OVA',    'Not seen','Not seen','—',   5),
(@stool_id, 'Pus Cells',    'PUS',    '0-2',     '0-2',     '/hpf',6),
(@stool_id, 'RBC',          'RBC',    '0-2',     '0-2',     '/hpf',7);