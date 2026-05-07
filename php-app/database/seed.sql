-- ============================================================
-- Connect IT - Sample Seed Data
-- Run AFTER schema.sql
-- ============================================================

USE connectit_db;

-- ============================================================
-- USERS (passwords are all: Password123!)
-- ============================================================
INSERT IGNORE INTO users (uid, email, password_hash, name, role, department, title, is_active, points, tickets_resolved) VALUES
('u_admin001',  'admin@connectit.com',   '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'System Administrator', 'ultra_super_admin', 'IT',         'System Administrator', 1, 9999, 0),
('u_agent001',  'alice@connectit.com',   '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Alice Johnson',        'agent',             'IT Support', 'Senior IT Agent',      1, 2450, 142),
('u_agent002',  'bob@connectit.com',     '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Bob Smith',            'agent',             'IT Support', 'IT Agent',             1, 2100, 98),
('u_agent003',  'carol@connectit.com',   '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Carol Williams',       'agent',             'Network',    'Network Engineer',     1, 1850, 87),
('u_user001',   'john@connectit.com',    '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'John Doe',             'user',              'Finance',    'Financial Analyst',    1, 120,  0),
('u_user002',   'jane@connectit.com',    '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Jane Wilson',          'user',              'HR',         'HR Manager',           1, 85,   0),
('u_user003',   'mike@connectit.com',    '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Mike Brown',           'user',              'Sales',      'Sales Executive',      1, 60,   0),
('u_sadmin001', 'sarah@connectit.com',   '$2y$12$oUZKNHMQWrNZu7kbOqi5kuVtaunsT9BgeQj1fV0HwfuHmPgQ/9GgG', 'Sarah Davis',          'admin',             'IT',         'IT Manager',           1, 500,  25);

-- ============================================================
-- TICKETS
-- ============================================================
INSERT IGNORE INTO tickets
    (ticket_number, caller, caller_user_id, title, description, category, subcategory, status, priority, impact, urgency,
     assignment_group, assigned_to, assigned_to_name, created_by, created_by_name,
     response_deadline, resolution_deadline, response_sla_status, resolution_sla_status, created_at)
VALUES
('INC1000001', 'John Doe',    'u_user001', 'Email server is completely down',
 'Users across the organization cannot send or receive emails. The mail server appears to be offline. This is affecting all departments.',
 'Email', 'Server', 'In Progress', '1 - Critical', '1 - High', '1 - High',
 'IT Support', 'u_agent001', 'Alice Johnson', 'u_user001', 'John Doe',
 DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 4 HOUR),
 'In Progress', 'At Risk', DATE_SUB(NOW(), INTERVAL 2 HOUR)),

('INC1000002', 'Jane Wilson', 'u_user002', 'Cannot access VPN from home',
 'I am unable to connect to the company VPN from my home network. I get an authentication error after entering my credentials.',
 'Network', 'VPN', 'New', '2 - High', '2 - Medium', '1 - High',
 'Network', 'u_agent003', 'Carol Williams', 'u_user002', 'Jane Wilson',
 DATE_ADD(NOW(), INTERVAL 3 HOUR), DATE_ADD(NOW(), INTERVAL 8 HOUR),
 'In Progress', 'In Progress', DATE_SUB(NOW(), INTERVAL 1 HOUR)),

('INC1000003', 'Mike Brown',  'u_user003', 'Laptop screen flickering intermittently',
 'My laptop screen has been flickering for the past two days. It happens randomly and makes it difficult to work.',
 'Hardware', 'Laptop', 'In Progress', '3 - Moderate', '3 - Low', '2 - Medium',
 'IT Support', 'u_agent002', 'Bob Smith', 'u_user003', 'Mike Brown',
 DATE_ADD(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 24 HOUR),
 'Completed', 'In Progress', DATE_SUB(NOW(), INTERVAL 5 HOUR)),

('INC1000004', 'John Doe',    'u_user001', 'Microsoft Office activation error',
 'Office 365 is showing an activation error and some features are disabled. Error code: 0x8004FC12.',
 'Software', 'Microsoft Office', 'Resolved', '3 - Moderate', '3 - Low', '3 - Low',
 'IT Support', 'u_agent001', 'Alice Johnson', 'u_user001', 'John Doe',
 DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR),
 'Completed', 'Completed', DATE_SUB(NOW(), INTERVAL 1 DAY)),

('INC1000005', 'Jane Wilson', 'u_user002', 'Printer not responding on 3rd floor',
 'The shared printer on the 3rd floor is not responding to print jobs. The printer shows as online but jobs are stuck in queue.',
 'Hardware', 'Printer', 'On Hold', '4 - Low', '3 - Low', '3 - Low',
 'IT Support', 'u_agent002', 'Bob Smith', 'u_user002', 'Jane Wilson',
 DATE_ADD(NOW(), INTERVAL 20 HOUR), DATE_ADD(NOW(), INTERVAL 70 HOUR),
 'In Progress', 'In Progress', DATE_SUB(NOW(), INTERVAL 3 HOUR)),

('INC1000006', 'Mike Brown',  'u_user003', 'Request for new monitor',
 'I need a second monitor for my workstation to improve productivity. My current single monitor setup is limiting my workflow.',
 'Hardware', 'Monitor', 'New', '4 - Low', '3 - Low', '3 - Low',
 'IT Support', NULL, NULL, 'u_user003', 'Mike Brown',
 DATE_ADD(NOW(), INTERVAL 23 HOUR), DATE_ADD(NOW(), INTERVAL 71 HOUR),
 'In Progress', 'In Progress', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),

('INC1000007', 'John Doe',    'u_user001', 'Database connection timeout errors',
 'The CRM application is throwing database connection timeout errors. Multiple users are affected and cannot access customer records.',
 'Database', 'CRM', 'Closed', '2 - High', '1 - High', '2 - Medium',
 'Database', 'u_agent001', 'Alice Johnson', 'u_user001', 'John Doe',
 DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR),
 'Completed', 'Completed', DATE_SUB(NOW(), INTERVAL 2 DAY)),

('INC1000008', 'Jane Wilson', 'u_user002', 'Password reset request',
 'I have forgotten my password and need it reset. I have tried the self-service portal but it is not sending the reset email.',
 'Access', 'Password', 'Resolved', '3 - Moderate', '2 - Medium', '2 - Medium',
 'IT Support', 'u_agent002', 'Bob Smith', 'u_user002', 'Jane Wilson',
 DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR),
 'Completed', 'Completed', DATE_SUB(NOW(), INTERVAL 6 HOUR));

-- ============================================================
-- COMMENTS
-- ============================================================
INSERT IGNORE INTO comments (ticket_id, user_id, user_name, user_role, message, is_internal, created_at) VALUES
(1, 'u_agent001', 'Alice Johnson', 'agent', 'I am investigating the mail server issue. Initial checks show the SMTP service has stopped. Attempting to restart.', 0, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(1, 'u_agent001', 'Alice Johnson', 'agent', 'SMTP service restarted but mail queue is backed up. Estimated 30 minutes to clear.', 1, DATE_SUB(NOW(), INTERVAL 60 MINUTE)),
(1, 'u_user001',  'John Doe',      'user',  'Thank you for the update. Please let us know when it is fully resolved.', 0, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(3, 'u_agent002', 'Bob Smith',     'agent', 'Checked the display driver - it appears to be outdated. Updating now.', 0, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 'u_agent002', 'Bob Smith',     'agent', 'Driver updated. Please monitor and let me know if the flickering continues.', 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'u_agent001', 'Alice Johnson', 'agent', 'Resolved by re-activating Office 365 with the correct license key. Issue was caused by a license server change.', 0, DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(5, 'u_agent002', 'Bob Smith',     'agent', 'Printer is on hold pending replacement toner cartridge. ETA 2 business days.', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(7, 'u_agent001', 'Alice Johnson', 'agent', 'Root cause identified: connection pool exhaustion. Increased pool size and optimized slow queries. Issue resolved.', 0, DATE_SUB(NOW(), INTERVAL 47 HOUR));

-- ============================================================
-- TICKET HISTORY
-- ============================================================
INSERT IGNORE INTO ticket_history (ticket_id, action, field_name, old_value, new_value, user_id, user_name, created_at) VALUES
(1, 'Ticket Created',  NULL,     NULL,          NULL,          'u_user001',  'John Doe',     DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 'Status Changed',  'status', 'New',         'In Progress', 'u_agent001', 'Alice Johnson',DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(1, 'Assigned',        'assigned_to', NULL,     'Alice Johnson','u_sadmin001','Sarah Davis',  DATE_SUB(NOW(), INTERVAL 100 MINUTE)),
(3, 'Ticket Created',  NULL,     NULL,          NULL,          'u_user003',  'Mike Brown',   DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(3, 'Status Changed',  'status', 'New',         'In Progress', 'u_agent002', 'Bob Smith',    DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(4, 'Ticket Created',  NULL,     NULL,          NULL,          'u_user001',  'John Doe',     DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 'Status Changed',  'status', 'In Progress', 'Resolved',    'u_agent001', 'Alice Johnson',DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(7, 'Ticket Created',  NULL,     NULL,          NULL,          'u_user001',  'John Doe',     DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 'Status Changed',  'status', 'Resolved',    'Closed',      'u_user001',  'John Doe',     DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ============================================================
-- KNOWLEDGE ARTICLES
-- ============================================================
INSERT IGNORE INTO knowledge_articles
    (article_number, title, category, content, summary, tags, author, author_name, status, visibility, views, helpful_count, published_at)
VALUES
('KB0000001', 'How to Reset Your Password',
 'Access',
 '## Password Reset Guide\n\n### Self-Service Portal\n1. Navigate to the login page\n2. Click "Forgot Password"\n3. Enter your email address\n4. Check your email for the reset link\n5. Click the link and enter a new password\n\n### Requirements\n- Minimum 8 characters\n- At least one uppercase letter\n- At least one number\n- At least one special character\n\n### Contact IT Support\nIf you do not receive the reset email within 5 minutes, contact IT Support at ext. 1234.',
 'Step-by-step guide for resetting your account password through the self-service portal.',
 'password,reset,access,login',
 'u_agent001', 'Alice Johnson', 'Published', 'Public', 245, 89, DATE_SUB(NOW(), INTERVAL 30 DAY)),

('KB0000002', 'VPN Setup and Troubleshooting',
 'Network',
 '## VPN Configuration Guide\n\n### Installation\n1. Download the VPN client from the IT portal\n2. Run the installer as administrator\n3. Enter the server address: vpn.company.com\n4. Use your domain credentials to authenticate\n\n### Common Issues\n**Cannot connect:**\n- Verify your internet connection\n- Check that your credentials are correct\n- Ensure MFA is configured\n\n**Slow connection:**\n- Try connecting to a different VPN server\n- Check your local internet speed',
 'Complete guide for setting up and troubleshooting VPN access for remote work.',
 'vpn,network,remote,access',
 'u_agent003', 'Carol Williams', 'Published', 'Internal', 189, 72, DATE_SUB(NOW(), INTERVAL 20 DAY)),

('KB0000003', 'Office 365 Activation Troubleshooting',
 'Software',
 '## Office 365 Activation Issues\n\n### Common Error Codes\n- **0x8004FC12**: License server unreachable\n- **0xC004F074**: KMS server not available\n\n### Resolution Steps\n1. Open Command Prompt as Administrator\n2. Run: `cscript ospp.vbs /act`\n3. If that fails, sign out and back into Office\n4. Check your Microsoft 365 subscription status\n\n### Contact Support\nIf issues persist, contact IT Support with your error code.',
 'Troubleshooting guide for Microsoft Office 365 activation errors.',
 'office365,microsoft,activation,software',
 'u_agent001', 'Alice Johnson', 'Published', 'Internal', 156, 61, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- ============================================================
-- ASSETS / CMDB
-- ============================================================
INSERT IGNORE INTO assets (asset_tag, name, type, status, owner, owner_name, location, model, manufacturer, ip_address, description) VALUES
('SRV-001', 'Mail Server (SMTP)',    'Server',      'Degraded',    'u_sadmin001', 'Sarah Davis',   'Data Center A', 'PowerEdge R740', 'Dell',    '10.0.1.10', 'Primary SMTP mail server'),
('SRV-002', 'Web Server (Apache)',   'Server',      'Operational', 'u_sadmin001', 'Sarah Davis',   'Data Center A', 'PowerEdge R640', 'Dell',    '10.0.1.11', 'Primary web application server'),
('SRV-003', 'Database Server (MySQL)','Database',   'Operational', 'u_sadmin001', 'Sarah Davis',   'Data Center A', 'PowerEdge R740', 'Dell',    '10.0.1.12', 'Primary MySQL database server'),
('NET-001', 'Core Switch',           'Network',     'Operational', 'u_agent003',  'Carol Williams','Network Room',  'Catalyst 9300',  'Cisco',   '10.0.0.1',  'Core network switch'),
('APP-001', 'CRM Application',       'Application', 'Operational', 'u_sadmin001', 'Sarah Davis',   'Cloud',         'Salesforce',     'Salesforce', NULL,      'Customer relationship management system'),
('HW-001',  'Laptop - John Doe',     'Hardware',    'Operational', 'u_user001',   'John Doe',      'Finance Dept',  'ThinkPad X1',    'Lenovo',  NULL,        'User workstation'),
('HW-002',  'Laptop - Jane Wilson',  'Hardware',    'Operational', 'u_user002',   'Jane Wilson',   'HR Dept',       'MacBook Pro',    'Apple',   NULL,        'User workstation'),
('HW-003',  'Printer - 3rd Floor',   'Hardware',    'Degraded',    'u_agent002',  'Bob Smith',     '3rd Floor',     'LaserJet Pro',   'HP',      '10.0.2.50', 'Shared department printer');

-- ============================================================
-- TIMESHEETS
-- ============================================================
INSERT IGNORE INTO timesheets (user_id, week_start, week_end, status, total_hours, submitted_at) VALUES
('u_agent001', DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
               DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY),
               'Draft', 0.00, NULL),
('u_agent002', DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
               DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY),
               'Draft', 0.00, NULL);

-- ============================================================
-- CALENDAR EVENTS
-- ============================================================
INSERT IGNORE INTO calendar_events (title, description, start_date, end_date, all_day, type, color, created_by) VALUES
('Scheduled Maintenance Window',  'Monthly server patching and maintenance',
 DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY + INTERVAL 4 HOUR), 0, 'maintenance', '#f59e0b', 'u_sadmin001'),
('IT Team Meeting',               'Weekly IT team sync',
 DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY + INTERVAL 1 HOUR), 0, 'meeting', '#3b82f6', 'u_sadmin001'),
('Network Upgrade',               'Core switch firmware upgrade',
 DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY + INTERVAL 2 HOUR), 0, 'change', '#8b5cf6', 'u_agent003');

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
INSERT IGNORE INTO notifications (user_id, type, title, message, related_ticket_id, is_read, created_at) VALUES
('u_agent001', 'ticket_assigned', 'New ticket assigned to you',
 'INC1000001 - Email server is completely down has been assigned to you.', 1, 0, DATE_SUB(NOW(), INTERVAL 100 MINUTE)),
('u_agent002', 'ticket_assigned', 'New ticket assigned to you',
 'INC1000003 - Laptop screen flickering has been assigned to you.', 3, 0, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('u_agent001', 'sla_breach', 'SLA At Risk',
 'INC1000001 is approaching its resolution SLA deadline.', 1, 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('u_user001',  'ticket_updated', 'Your ticket has been updated',
 'INC1000001 - A comment has been added to your ticket.', 1, 1, DATE_SUB(NOW(), INTERVAL 90 MINUTE));
