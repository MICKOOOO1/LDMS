-- Create database
DROP DATABASE IF EXISTS csu_dorm_db;

CREATE DATABASE csu_dorm_db;

USE csu_dorm_db;

-- USERS TABLE
DROP TABLE IF EXISTS delete_requests;
DROP TABLE IF EXISTS security_questions;
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(15) NOT NULL UNIQUE,        -- ID Number (format xxxx-xxxx)
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    extension_name VARCHAR(10) NULL,
    dob DATE NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    sex ENUM('male', 'female') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,        -- hashed password
    role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
    account_status ENUM('pending','approved','blocked') NOT NULL DEFAULT 'approved',
    privileges TEXT NULL,
    purok_street VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SECURITY QUESTIONS TABLE
CREATE TABLE security_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(15) NOT NULL,           -- links to users.user_id
    question_code VARCHAR(50) NOT NULL,     -- e.g. 'first_pet', 'first_school'
    answer_hash VARCHAR(255) NOT NULL,      -- store hashed answers
    CONSTRAINT fk_sq_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);

-- LOGIN ATTEMPTS TABLE (optional, not strictly required because you use sessions)
DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    lockout_until INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE delete_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT NOT NULL,
    target_user INT NOT NULL,
    reason TEXT NOT NULL,
    user_snapshot TEXT NOT NULL,
    request_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_delete_request_admin FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_delete_request_target FOREIGN KEY (target_user) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_delete_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    employee_id VARCHAR(15) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role ENUM('user','admin','super_admin') NOT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME NULL,
    INDEX idx_login_logs_time_in (time_in),
    INDEX idx_login_logs_role (role),
    INDEX idx_login_logs_user_id (user_id)
);

INSERT INTO users (
    user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
    email, username, password_hash, role, account_status, privileges,
    purok_street, barangay, city_municipality, province, country, zip_code
) VALUES (
    '0000-0001', 'Super Admin', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
    'superadmin@csucc-ldms.local', 'super.admin',
    '$2y$10$gkCEHa80hHpCZONoNg31Iu.gq8ppTlxNpaIYOmzKWJKAXO63LqUhq',
    'super_admin', 'approved', 'manage_accounts,manage_privileges,delete_accounts',
    'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
);

INSERT INTO users (
    user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
    email, username, password_hash, role, account_status, privileges,
    purok_street, barangay, city_municipality, province, country, zip_code
) VALUES (
    '0000-0002', 'Administrator', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
    'admin@csucc-ldms.local', 'system.admin',
    '$2y$10$lMhb/4L9PR9VaZeaw7TlAukkSTvfXJe46jG6T1CI/CVJ7QVQXE7SO',
    'admin', 'approved', 'manage_registration,update_accounts',
    'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
);

DELIMITER $$

CREATE PROCEDURE RegisterUser(
    IN p_user_id        VARCHAR(20),
    IN p_first_name     VARCHAR(50),
    IN p_middle_name    VARCHAR(50),
    IN p_last_name      VARCHAR(50),
    IN p_extension_name VARCHAR(10),
    IN p_dob            DATE,
    IN p_age            INT,
    IN p_sex            VARCHAR(10),
    IN p_username       VARCHAR(50),
    IN p_email          VARCHAR(100),
    IN p_password_hash  VARCHAR(255),
    IN p_purok_street   VARCHAR(100),
    IN p_barangay       VARCHAR(100),
    IN p_city_municipality VARCHAR(100),
    IN p_province       VARCHAR(100),
    IN p_country        VARCHAR(100),
    IN p_zip_code       VARCHAR(10)
)
BEGIN
    INSERT INTO users (
        user_id, first_name, middle_name, last_name, extension_name,
        dob, age, sex, username, email, password_hash,
        purok_street, barangay, city_municipality, province, country, zip_code
    ) VALUES (
        p_user_id, p_first_name, p_middle_name, p_last_name, p_extension_name,
        p_dob, p_age, p_sex, p_username, p_email, p_password_hash,
        p_purok_street, p_barangay, p_city_municipality, p_province, p_country, p_zip_code
    );
END$$

DELIMITER ;
