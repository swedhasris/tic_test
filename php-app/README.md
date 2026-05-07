# Connect IT — PHP MVC Ticketing System

A complete, production-ready IT Service Management (ITSM) ticketing system built with PHP 8+ MVC architecture, MySQL, and Tailwind CSS.

---

## Features

| Module | Description |
|---|---|
| **Tickets** | Create, view, edit, delete, assign — full lifecycle management |
| **Dashboard** | Real-time stats, recent incidents, leaderboard, SLA overview |
| **Users** | Role-based user management (user / agent / admin / super_admin) |
| **Timesheets** | Weekly time tracking with entry management and approval workflow |
| **Reports** | Analytics: status/priority breakdown, agent performance, SLA compliance, monthly trends |
| **Knowledge Base** | Searchable articles with draft/published/archived states |
| **CMDB** | Asset inventory with status tracking |
| **SLA Management** | Automatic deadline calculation per priority/category |
| **Comments** | Public comments + internal notes (agents only) |
| **Attachments** | File uploads on tickets (10 MB limit) |
| **Notifications** | In-app notification bell with unread count |
| **AI Chatbot** | OpenAI-powered IT assistant (configurable) |
| **REST API** | JSON endpoints for AJAX/SPA integration |

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` enabled (or Nginx with equivalent config)
- PHP extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`

---

## Quick Start

### 1. Clone / copy files

Place the `php-app/` directory inside your web server's document root (e.g., `/var/www/html/php-app/`).

### 2. Configure environment

```bash
cp php-app/.env.example php-app/.env
```

Edit `php-app/.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=connectit_db
DB_USER=your_db_user
DB_PASS=your_db_password
APP_URL=http://localhost/php-app
```

### 3. Create the database

```sql
CREATE DATABASE connectit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations

```bash
mysql -u root -p connectit_db < php-app/database/schema.sql
mysql -u root -p connectit_db < php-app/database/seed.sql
```

Or use the setup script:

```bash
php php-app/setup.php
```

### 5. Set permissions

```bash
chmod -R 755 php-app/
chmod -R 777 php-app/public/uploads/
chmod -R 777 php-app/logs/
```

### 6. Configure Apache

Enable `mod_rewrite` and ensure `AllowOverride All` is set for the directory:

```apache
<Directory /var/www/html/php-app/public>
    AllowOverride All
    Require all granted
</Directory>
```

### 7. Access the application

Navigate to: `http://localhost/php-app/public/`

**Default credentials:**

| Email | Password | Role |
|---|---|---|
| admin@connectit.com | password | Ultra Super Admin |
| alice@connectit.com | password | Agent |
| bob@connectit.com | password | Agent |
| john@connectit.com | password | User |

> ⚠️ Change all passwords immediately after first login.

---

## Project Structure

```
php-app/
├── .env                    # Environment variables (not committed)
├── .env.example            # Environment template
├── config/
│   ├── app.php             # Application configuration
│   └── routes.php          # All route definitions
├── core/                   # Framework core
│   ├── Application.php     # App bootstrap
│   ├── Auth.php            # Authentication helper
│   ├── Controller.php      # Base controller
│   ├── CSRF.php            # CSRF token management
│   ├── Database.php        # PDO singleton
│   ├── Middleware.php      # Middleware base class
│   ├── Model.php           # Base model with CRUD
│   ├── Request.php         # HTTP request wrapper
│   ├── Response.php        # HTTP response (redirect/JSON)
│   ├── Router.php          # URL router
│   ├── Session.php         # Secure session handling
│   └── Validator.php       # Input validation
├── app/
│   ├── Controllers/        # Request handlers
│   ├── Middlewares/        # Auth & role guards
│   ├── Models/             # Database models
│   └── Views/              # PHP templates
│       ├── layout/         # main.php + sidebar.php
│       ├── auth/           # login.php, register.php
│       ├── dashboard/      # index.php
│       ├── tickets/        # index, create, detail, edit
│       ├── users/          # index, create, edit
│       ├── timesheet/      # index.php
│       └── reports/        # index.php
├── database/
│   ├── schema.sql          # Complete MySQL schema
│   └── seed.sql            # Sample data
├── public/
│   ├── index.php           # Front controller
│   ├── .htaccess           # Apache rewrite rules
│   ├── css/app.css         # Application styles
│   ├── js/app.js           # Application JavaScript
│   └── uploads/            # File attachments
└── logs/                   # Application logs
```

---

## Security

- **CSRF protection** on all POST forms via `CSRF::field()` / `CSRF::validate()`
- **XSS protection** via `htmlspecialchars()` on all output
- **SQL injection prevention** via PDO prepared statements throughout
- **Password hashing** with `password_hash(PASSWORD_DEFAULT)`
- **Session security**: `httponly` cookies, `SameSite=Lax`, `session_regenerate_id()` on login
- **Role-based access control** via `AuthMiddleware` + `RoleMiddleware`
- **File upload validation**: extension whitelist + size limit

---

## REST API

All endpoints require authentication (session cookie).

| Method | Endpoint | Description |
|---|---|---|
| GET | `/php-app/api/tickets` | List tickets (filterable) |
| GET | `/php-app/api/tickets/stats` | Dashboard statistics |
| GET | `/php-app/api/users` | List agents |
| GET | `/php-app/api/notifications` | User notifications |
| POST | `/php-app/api/notifications/read` | Mark notification(s) read |
| GET | `/php-app/api/sla/status` | At-risk SLA tickets |
| GET | `/php-app/api/leaderboard` | Agent leaderboard |
| POST | `/php-app/api/ai/chat` | AI chatbot (requires `AI_API_KEY`) |

---

## AI Chatbot

Set in `.env`:

```env
AI_API_KEY=sk-...
AI_MODEL=gpt-3.5-turbo
AI_ENDPOINT=https://api.openai.com/v1/chat/completions
```

The chatbot bubble appears in the bottom-right corner of every page.

---

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/php-app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(env|sql|md|log)$ {
        deny all;
    }
}
```

---

## License

MIT — free to use and modify.
