SET FOREIGN_KEY_CHECKS=0;

-- 1. Core Structure
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'finance_manager', 'accountant', 'auditor') NOT NULL,
    default_branch_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (default_branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

-- 2. Accounting Core
CREATE TABLE fiscal_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    branch_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_locked TINYINT(1) DEFAULT 0,
    locked_at TIMESTAMP NULL,
    locked_by INT,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE(company_id, code)
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    branch_id INT NOT NULL,
    date DATE NOT NULL,
    reference_no VARCHAR(50) NOT NULL,
    description TEXT,
    status ENUM('draft', 'submitted', 'approved', 'posted', 'rejected', 'voided') DEFAULT 'draft',
    created_by INT NOT NULL,
    approved_by INT,
    posted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE journal_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    memo VARCHAR(255),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- 3. Workflow & Attachments
CREATE TABLE workflow_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'journal', 'invoice'
    entity_id INT NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'submit', 'approve', 'reject'
    reason TEXT,
    prev_status VARCHAR(50),
    new_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50),
    entity_id INT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_hash VARCHAR(64) NOT NULL, -- SHA256 of file content
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Immutable Audit Log (Hash Chained)
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    before_json JSON,
    after_json JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    prev_hash VARCHAR(64) NOT NULL,
    curr_hash VARCHAR(64) NOT NULL,
    INDEX (curr_hash),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;

-- SEEDS
INSERT INTO companies (name) VALUES ('Acme Corp');
INSERT INTO branches (company_id, name) VALUES (1, 'Headquarters'), (1, 'West Branch');
-- Pass: admin123 (Hash this in real usage)
INSERT INTO users (company_id, username, email, password_hash, role, default_branch_id) 
VALUES (1, 'admin', 'admin@acme.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance_manager', 1);
INSERT INTO users (company_id, username, email, password_hash, role, default_branch_id) 
VALUES (1, 'clerk', 'clerk@acme.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'accountant', 1);

INSERT INTO accounts (company_id, code, name, type) VALUES 
(1, '1000', 'Cash', 'asset'),
(1, '4000', 'Sales Revenue', 'revenue'),
(1, '5000', 'Office Expense', 'expense');