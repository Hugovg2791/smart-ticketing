CREATE DATABASE IF NOT EXISTS app_db;
USE app_db;

CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tickets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    gmail_thread_id VARCHAR(255) UNIQUE,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'En Proceso',
    category VARCHAR(100),
    sentiment VARCHAR(50),
    assigned_to BIGINT,
    has_new_message TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT NOT NULL,
    sender_id BIGINT,
    sender_email VARCHAR(255) NOT NULL,
    body_text TEXT NOT NULL,
    message_id VARCHAR(255) UNIQUE NOT NULL,
    in_reply_to VARCHAR(255),
    direction ENUM('incoming', 'outgoing') NOT NULL DEFAULT 'incoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE ai_suggestions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    suggested_category VARCHAR(100),
    suggested_sentiment VARCHAR(50),
    suggested_response TEXT NOT NULL,
    is_applied BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);
