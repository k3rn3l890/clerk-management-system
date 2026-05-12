-- Ghana Court Clerk Management System Database Schema

-- Drop database if exists
DROP DATABASE IF EXISTS court_management;

-- Create database
CREATE DATABASE court_management;

-- Use database
USE court_management;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('court_clerk', 'judge', 'admin', 'it_admin', 'lawyer', 'litigant') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    profile_picture VARCHAR(255) DEFAULT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cases table
CREATE TABLE cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) UNIQUE NOT NULL,
    case_title VARCHAR(255) NOT NULL,
    case_type ENUM('civil', 'criminal', 'family', 'commercial', 'other') NOT NULL,
    filing_date DATE NOT NULL,
    status ENUM('pending', 'active', 'closed', 'appealed', 'archived') DEFAULT 'pending',
    description TEXT,
    assigned_judge INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_judge) REFERENCES users(user_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Case parties table
CREATE TABLE case_parties (
    party_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    party_type ENUM('plaintiff', 'defendant', 'appellant', 'respondent', 'witness', 'other') NOT NULL,
    party_name VARCHAR(100) NOT NULL,
    contact_info VARCHAR(255),
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Lawyers table
CREATE TABLE lawyers (
    lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bar_number VARCHAR(50) UNIQUE NOT NULL,
    law_firm VARCHAR(100),
    specialization VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Case lawyers table
CREATE TABLE case_lawyers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    party_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(lawyer_id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES case_parties(party_id) ON DELETE CASCADE
);

-- Documents table
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

-- Hearings table
CREATE TABLE hearings (
    hearing_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    hearing_date DATETIME NOT NULL,
    hearing_type VARCHAR(50) NOT NULL,
    location VARCHAR(100) NOT NULL,
    status ENUM('scheduled', 'completed', 'postponed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Notifications table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    related_case INT DEFAULT NULL,
    related_hearing INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (related_case) REFERENCES cases(case_id) ON DELETE SET NULL,
    FOREIGN KEY (related_hearing) REFERENCES hearings(hearing_id) ON DELETE SET NULL
);

-- Messages table
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    related_case INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id),
    FOREIGN KEY (related_case) REFERENCES cases(case_id) ON DELETE SET NULL
);

-- Fees and payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    payment_type ENUM('filing_fee', 'fine', 'other_fee') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money', 'other') NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    received_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(user_id)
);

-- Audit logs table
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- System settings table
CREATE TABLE settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO users (username, password, email, first_name, last_name, role)
VALUES ('admin', '$2y$10$8zf0SXIrLBRUQ5dUJwdw8.XZNm5zSJU0Vd.lOZJj.fRXIXVxjUMmK', 'admin@court.gov.gh', 'System', 'Administrator', 'admin');
-- Password is 'admin123' (hashed with bcrypt)

-- Insert system settings
INSERT INTO settings (setting_name, setting_value, description)
VALUES 
('system_name', 'Ghana Court Clerk Management System', 'Name of the system'),
('court_name', 'High Court of Ghana', 'Name of the court'),
('court_address', 'Accra, Ghana', 'Address of the court'),
('court_contact', '+233 XX XXX XXXX', 'Contact number of the court'),
('court_email', 'info@court.gov.gh', 'Email address of the court'),
('maintenance_mode', 'false', 'Whether the system is in maintenance mode');
