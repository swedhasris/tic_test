-- ============================================================
-- Connect IT - Complete MySQL Schema
-- Version: 2.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS connectit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE connectit_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    uid             VARCHAR(128) UNIQUE NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255),
    name            VARCHAR(255) NOT NULL,
    role            ENUM('user','agent','admin','super_admin','ultra_super_admin') DEFAULT 'user',
    phone           VARCHAR(50),
    department      VARCHAR(100),
    title           VARCHAR(100),
    avatar_url      TEXT,
    is_active       TINYINT(1) DEFAULT 1,
    email_verified  TINYINT(1) DEFAULT 0,
    points          INT DEFAULT 0,
    tickets_resolved INT DEFAULT 0,
    avg_rating      DECIMAL(3,2) DEFAULT 0.00,
    last_login      TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_uid (uid),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TICKETS
-- ============================================================
CREATE TABLE IF NOT EXISTS tickets (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number           VARCHAR(50) UNIQUE NOT NULL,
    caller                  VARCHAR(255) NOT NULL,
    caller_user_id          VARCHAR(128),
    affected_user           VARCHAR(255),
    affected_user_id        VARCHAR(128),
    category                VARCHAR(100),
    subcategory             VARCHAR(100),
    service                 VARCHAR(100),
    service_offering        VARCHAR(100),
    cmdb_item               VARCHAR(100),
    title                   VARCHAR(500) NOT NULL,
    description             TEXT,
    channel                 ENUM('Phone','Email','Self-service','Walk-in','Other') DEFAULT 'Self-service',
    status                  ENUM('New','In Progress','On Hold','Resolved','Closed','Canceled','Pending Approval') DEFAULT 'New',
    impact                  ENUM('1 - High','2 - Medium','3 - Low') DEFAULT '3 - Low',
    urgency                 ENUM('1 - High','2 - Medium','3 - Low') DEFAULT '3 - Low',
    priority                ENUM('1 - Critical','2 - High','3 - Moderate','4 - Low') DEFAULT '4 - Low',
    assignment_group        VARCHAR(100),
    assigned_to             VARCHAR(128),
    assigned_to_name        VARCHAR(255),
    created_by              VARCHAR(128) NOT NULL,
    created_by_name         VARCHAR(255),
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    first_response_at       TIMESTAMP NULL,
    resolved_at             TIMESTAMP NULL,
    closed_at               TIMESTAMP NULL,
    response_deadline       TIMESTAMP NULL,
    resolution_deadline     TIMESTAMP NULL,
    on_hold_start           TIMESTAMP NULL,
    on_hold_reason          VARCHAR(255),
    total_paused_time_ms    BIGINT DEFAULT 0,
    response_sla_status     ENUM('In Progress','Completed','Breached','At Risk') DEFAULT 'In Progress',
    resolution_sla_status   ENUM('In Progress','Completed','Breached','At Risk') DEFAULT 'In Progress',
    points                  INT DEFAULT 0,
    approval_status         ENUM('Not Required','Pending','Approved','Rejected') DEFAULT 'Not Required',
    parent_ticket_id        INT NULL,
    FOREIGN KEY (parent_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_by (created_by),
    INDEX idx_caller (caller),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at),
    INDEX idx_resolved_at (resolved_at),
    INDEX idx_status_priority (status, priority),
    INDEX idx_assigned_status (assigned_to, status),
    FULLTEXT INDEX idx_fulltext (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TICKET HISTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT NOT NULL,
    action      VARCHAR(255) NOT NULL,
    field_name  VARCHAR(100),
    old_value   TEXT,
    new_value   TEXT,
    user_id     VARCHAR(128),
    user_name   VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COMMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT NOT NULL,
    user_id     VARCHAR(128),
    user_name   VARCHAR(255),
    user_role   VARCHAR(50),
    message     TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ATTACHMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS attachments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    entity_type     VARCHAR(50) NOT NULL,
    entity_id       INT NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_size       INT NOT NULL,
    mime_type       VARCHAR(100),
    uploaded_by     VARCHAR(128),
    uploaded_by_name VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SLA POLICIES
-- ============================================================
CREATE TABLE IF NOT EXISTS sla_policies (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    priority                VARCHAR(50) NOT NULL,
    category                VARCHAR(100),
    response_time_hours     INT NOT NULL DEFAULT 4,
    resolution_time_hours   INT NOT NULL DEFAULT 24,
    is_active               TINYINT(1) DEFAULT 1,
    description             TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_priority (priority),
    INDEX idx_active (is_active),
    UNIQUE KEY uq_priority_category (priority, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- APPROVALS
-- ============================================================
CREATE TABLE IF NOT EXISTS approvals (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id           INT NOT NULL,
    status              ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    requested_by        VARCHAR(128) NOT NULL,
    requested_by_name   VARCHAR(255),
    approved_by         VARCHAR(128),
    approved_by_name    VARCHAR(255),
    comments            TEXT,
    approved_at         TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_status (status),
    INDEX idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- KNOWLEDGE BASE
-- ============================================================
CREATE TABLE IF NOT EXISTS knowledge_articles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    article_number  VARCHAR(50) UNIQUE NOT NULL,
    title           VARCHAR(500) NOT NULL,
    category        VARCHAR(100),
    subcategory     VARCHAR(100),
    content         LONGTEXT NOT NULL,
    summary         TEXT,
    tags            TEXT,
    views           INT DEFAULT 0,
    helpful_count   INT DEFAULT 0,
    not_helpful_count INT DEFAULT 0,
    author          VARCHAR(128) NOT NULL,
    author_name     VARCHAR(255),
    status          ENUM('Draft','Published','Archived') DEFAULT 'Draft',
    visibility      ENUM('Internal','Public') DEFAULT 'Internal',
    version         INT DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at    TIMESTAMP NULL,
    INDEX idx_article_number (article_number),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_author (author),
    FULLTEXT INDEX idx_fulltext (title, content, summary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ASSETS / CMDB
-- ============================================================
CREATE TABLE IF NOT EXISTS assets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag       VARCHAR(100) UNIQUE,
    name            VARCHAR(255) NOT NULL,
    type            ENUM('Server','Database','Network','Application','Hardware','Service','Virtual Machine','Storage') DEFAULT 'Hardware',
    status          ENUM('Operational','Degraded','Maintenance','Retired') DEFAULT 'Operational',
    owner           VARCHAR(128),
    owner_name      VARCHAR(255),
    location        VARCHAR(255),
    serial_number   VARCHAR(255),
    model           VARCHAR(255),
    manufacturer    VARCHAR(255),
    purchase_date   DATE,
    warranty_expiry DATE,
    ip_address      VARCHAR(50),
    mac_address     VARCHAR(50),
    os              VARCHAR(100),
    description     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_owner (owner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROBLEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS problems (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    problem_number      VARCHAR(50) UNIQUE NOT NULL,
    title               VARCHAR(500) NOT NULL,
    description         TEXT,
    status              ENUM('Open','Under Investigation','Known Error','Resolved','Closed') DEFAULT 'Open',
    priority            ENUM('1 - Critical','2 - High','3 - Moderate','4 - Low') DEFAULT '4 - Low',
    category            VARCHAR(100),
    root_cause          TEXT,
    workaround          TEXT,
    resolution          TEXT,
    assigned_to         VARCHAR(128),
    assigned_to_name    VARCHAR(255),
    reported_by         VARCHAR(128),
    reported_by_name    VARCHAR(255),
    related_incidents   INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at         TIMESTAMP NULL,
    closed_at           TIMESTAMP NULL,
    INDEX idx_problem_number (problem_number),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned_to (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHANGES
-- ============================================================
CREATE TABLE IF NOT EXISTS changes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    change_number       VARCHAR(50) UNIQUE NOT NULL,
    title               VARCHAR(500) NOT NULL,
    description         TEXT,
    type                ENUM('Normal','Standard','Emergency') DEFAULT 'Normal',
    state               ENUM('Draft','Submitted','Planned','Approved','In Progress','Completed','Closed','Canceled') DEFAULT 'Draft',
    risk                ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
    impact              TEXT,
    rollback_plan       TEXT,
    requester           VARCHAR(128) NOT NULL,
    requester_name      VARCHAR(255),
    assigned_to         VARCHAR(128),
    assigned_to_name    VARCHAR(255),
    planned_start_date  TIMESTAMP NULL,
    planned_end_date    TIMESTAMP NULL,
    actual_start_date   TIMESTAMP NULL,
    actual_end_date     TIMESTAMP NULL,
    category            VARCHAR(100),
    affected_services   TEXT,
    approval_status     ENUM('Not Required','Pending','Approved','Rejected') DEFAULT 'Not Required',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_change_number (change_number),
    INDEX idx_type (type),
    INDEX idx_state (state),
    INDEX idx_requester (requester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SERVICE CATALOG
-- ============================================================
CREATE TABLE IF NOT EXISTS service_catalog (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    category        VARCHAR(100),
    description     TEXT,
    icon            VARCHAR(50),
    sla_hours       INT DEFAULT 24,
    is_active       TINYINT(1) DEFAULT 1,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TIMESHEETS
-- ============================================================
CREATE TABLE IF NOT EXISTS timesheets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         VARCHAR(128) NOT NULL,
    week_start      DATE NOT NULL,
    week_end        DATE NOT NULL,
    status          ENUM('Draft','Submitted','Approved','Rejected') DEFAULT 'Draft',
    total_hours     DECIMAL(10,2) DEFAULT 0.00,
    submitted_at    TIMESTAMP NULL,
    approved_by     VARCHAR(128),
    approved_at     TIMESTAMP NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_week (user_id, week_start),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TIME CARDS
-- ============================================================
CREATE TABLE IF NOT EXISTS time_cards (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    user_id             VARCHAR(128) NOT NULL,
    entry_date          DATE NOT NULL,
    ticket_id           INT,
    task                VARCHAR(255),
    short_description   VARCHAR(255),
    description         TEXT,
    hours_worked        DECIMAL(10,2) DEFAULT 0.00,
    start_time          VARCHAR(20),
    end_time            VARCHAR(20),
    deduct              DECIMAL(10,2) DEFAULT 0.00,
    work_type           VARCHAR(50),
    billable            VARCHAR(50),
    status              ENUM('Draft','Submitted','Approved','Rejected') DEFAULT 'Draft',
    elapsed_seconds     INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    INDEX idx_timesheet_id (timesheet_id),
    INDEX idx_user_date (user_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             VARCHAR(128) NOT NULL,
    type                ENUM('ticket_assigned','ticket_updated','approval_required','sla_breach','comment_added','system') DEFAULT 'system',
    title               VARCHAR(255) NOT NULL,
    message             TEXT,
    related_ticket_id   INT,
    related_entity_type VARCHAR(50),
    related_entity_id   VARCHAR(50),
    is_read             TINYINT(1) DEFAULT 0,
    read_at             TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (related_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id   VARCHAR(50) NOT NULL,
    action      VARCHAR(50) NOT NULL,
    user_id     VARCHAR(128),
    user_name   VARCHAR(255),
    old_values  JSON,
    new_values  JSON,
    ip_address  VARCHAR(50),
    user_agent  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) UNIQUE NOT NULL,
    setting_value   TEXT,
    setting_type    ENUM('string','number','boolean','json') DEFAULT 'string',
    description     TEXT,
    updated_by      VARCHAR(128),
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CALENDAR EVENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS calendar_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    start_date  DATETIME NOT NULL,
    end_date    DATETIME,
    all_day     TINYINT(1) DEFAULT 0,
    type        ENUM('maintenance','change','meeting','holiday','other') DEFAULT 'other',
    color       VARCHAR(20),
    created_by  VARCHAR(128),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_start_date (start_date),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEFAULT DATA
-- ============================================================
INSERT IGNORE INTO sla_policies (name, priority, response_time_hours, resolution_time_hours, description) VALUES
('Critical Priority SLA', '1 - Critical', 1,  4,  'Immediate response for critical issues'),
('High Priority SLA',     '2 - High',     4,  8,  'Urgent response for high priority issues'),
('Moderate Priority SLA', '3 - Moderate', 8,  24, 'Standard response for moderate priority'),
('Low Priority SLA',      '4 - Low',      24, 72, 'Best effort for low priority issues');

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('ticket_number_prefix',      'INC',          'string',  'Prefix for incident ticket numbers'),
('ticket_number_next',        '1000000',       'number',  'Next ticket number sequence'),
('enable_sla_monitoring',     'true',          'boolean', 'Enable automatic SLA monitoring'),
('enable_email_notifications','false',         'boolean', 'Enable email notifications'),
('company_name',              'Connect IT',    'string',  'Company name displayed in portal'),
('maintenance_mode',          'false',         'boolean', 'Enable maintenance mode'),
('allow_registration',        'false',         'boolean', 'Allow public user registration');

INSERT IGNORE INTO service_catalog (name, category, description, icon, sla_hours, sort_order) VALUES
('New Employee Onboarding',   'HR',       'Setup accounts and equipment for new employees', 'fa-user-plus',    48, 1),
('Password Reset',            'Access',   'Reset user account password',                    'fa-key',          2,  2),
('Software Installation',     'Software', 'Install approved software on workstation',        'fa-download',     8,  3),
('Hardware Request',          'Hardware', 'Request new or replacement hardware',             'fa-laptop',       72, 4),
('VPN Access',                'Network',  'Request VPN access for remote work',              'fa-shield-alt',   4,  5),
('Email Account Setup',       'Email',    'Create or configure email account',               'fa-envelope',     4,  6),
('Printer Setup',             'Hardware', 'Configure printer access',                        'fa-print',        8,  7),
('Database Access Request',   'Database', 'Request access to database systems',              'fa-database',     24, 8);
