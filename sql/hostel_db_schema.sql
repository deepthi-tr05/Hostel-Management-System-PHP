-- ============================================================
--  Hostel Management System — Database Schema
--  Database: hostel_db
--  Encoding: UTF-8
-- ============================================================

CREATE DATABASE IF NOT EXISTS hostel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hostel_db;

-- ------------------------------------------------------------
-- Table: users
-- Stores portal accounts (students who can submit applications)
-- Emails are AES-256-CBC encrypted; a SHA-256 hash is kept
-- for fast duplicate-check lookups without decrypting all rows.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT          NOT NULL AUTO_INCREMENT,
    email_id   TEXT         NOT NULL COMMENT 'AES-256-CBC encrypted email',
    email_hash VARCHAR(64)  NOT NULL UNIQUE COMMENT 'SHA-256 of lower-cased email for duplicate checks',
    password   VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed password',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: register
-- Student hostel application records.
-- All PII fields are AES-256-CBC encrypted at rest.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS register (
    id           INT          NOT NULL AUTO_INCREMENT,
    student_name VARCHAR(500) NOT NULL COMMENT 'Encrypted',
    father_name  VARCHAR(500) NOT NULL COMMENT 'Encrypted',
    mobile1      VARCHAR(20)  NOT NULL COMMENT 'Encrypted',
    mobile2      VARCHAR(20)           COMMENT 'Encrypted (optional)',
    email        VARCHAR(255) NOT NULL COMMENT 'Encrypted',
    country      VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    state        VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    district     VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    native_place VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    admission    VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    degree       VARCHAR(100) NOT NULL COMMENT 'Encrypted',
    pincode      VARCHAR(20)  NOT NULL COMMENT 'Encrypted',
    front_aadhaar VARCHAR(500)          COMMENT 'File path to uploaded front Aadhaar image',
    back_aadhaar  VARCHAR(500)          COMMENT 'File path to uploaded back Aadhaar image',
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes  TEXT                  COMMENT 'Notes added by admin during review',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: dashboard
-- Approved student records mirrored from `register`.
-- Populated automatically when admin approves an application.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dashboard (
    id            INT          NOT NULL AUTO_INCREMENT,
    original_id   INT          NOT NULL COMMENT 'Foreign key reference to register.id',
    student_name  VARCHAR(500) NOT NULL,
    father_name   VARCHAR(500) NOT NULL,
    mobile1       VARCHAR(20)  NOT NULL,
    mobile2       VARCHAR(20),
    email         VARCHAR(255) NOT NULL,
    country       VARCHAR(100) NOT NULL,
    state         VARCHAR(100) NOT NULL,
    district      VARCHAR(100) NOT NULL,
    native_place  VARCHAR(100) NOT NULL,
    admission     VARCHAR(100) NOT NULL,
    degree        VARCHAR(100) NOT NULL,
    pincode       VARCHAR(20)  NOT NULL,
    front_aadhaar VARCHAR(500),
    back_aadhaar  VARCHAR(500),
    admin_notes   TEXT,
    approved_by   VARCHAR(100) NOT NULL DEFAULT 'admin',
    approval_date TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_original_id (original_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: distance  (optional — pre-cached distances)
-- Stores Google Maps Distance Matrix results so repeated
-- pincode lookups don't consume API quota.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS distance (
    id                  INT          NOT NULL AUTO_INCREMENT,
    origin_pincode      VARCHAR(20)  NOT NULL,
    destination_pincode VARCHAR(20)  NOT NULL,
    distance            VARCHAR(50)  NOT NULL COMMENT 'e.g. "895 km"',
    fetched_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_route (origin_pincode, destination_pincode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
