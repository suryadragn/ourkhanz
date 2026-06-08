# OurKhanz - SIMRS Khanza Web Version

A web-based patient registration and scheduling system built with **Yii2 Advanced** framework, designed for hospital operations (SIMRS - Sistem Informasi Manajemen Rumah Sakit Khanza).

## Features

- 👨‍⚕️ **Doctor Schedule Management** – View available doctor schedules by date and specialty
- 📋 **Patient Registration** – Online booking for medical appointments with real-time quota management
- 👥 **Patient Database** – Searchable patient records with modal lookup
- 🏥 **Multi-Module Architecture** – Organized frontend (patient view) and backend (admin panel)
- ⚡ **AJAX Workflows** – Smooth, responsive interactions without page reloads
- 📊 **Real-time Queue Display** – Live updates of recent registrations with booking times

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Framework | Yii2 Advanced 2.0.55 |
| Frontend UI | Bootstrap 5 |
| Data Tables | DataTables 1.13.8 |
| Database | MySQL/MariaDB |
| Server | Apache/Nginx + PHP 7.4+ |
| Task Runner | Composer |

## Installation

### Prerequisites

- **PHP** 7.4 or higher
- **MySQL/MariaDB** 5.7+
- **Composer** (for dependency management)
- **Laragon** (recommended, includes Apache + MySQL + PHP)

### Step 1: Clone Repository

```bash
git clone https://github.com/suryadragn/ourkhanz.git
cd ourkhanz
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

### Step 3: Environment Configuration

Copy the example environment file and configure database credentials:

```bash
cp .env.example .env
```

Edit `.env` with your database settings:

```env
DB_HOST=127.0.0.1      # Database host
DB_PORT=3307           # Database port
DB_NAME=sik            # Database name (SIMRS Khanza database)
DB_USERNAME=root       # Database user
DB_PASSWORD=           # Database password
DB_DSN=mysql:host=127.0.0.1;port=3307;dbname=sik
```

**For Remote Database:**
```env
DB_HOST=100.104.190.101
DB_PORT=3306
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Configure Web Server

#### Using Laragon (Recommended)

1. Place project in `C:\laragon\www\ourkhanz`
2. Add virtual host alias:
   ```
   127.0.0.1  ourkhanz.test
   ```
3. In Laragon, enable the site

#### Using Apache/Nginx Manually

**Apache (.htaccess):**
- Ensure `mod_rewrite` is enabled
- DocumentRoot should point to `public/` directory

**Frontend Entry:**
```
http://yourserver/ourkhanz/public/
http://yourserver/ourkhanz/public/index.php
```

**Backend Entry:**
```
http://yourserver/ourkhanz/public/admin/
http://yourserver/ourkhanz/public/admin/index.php
```

### Step 5: Prepare Database

Import the SIMRS Khanza database schema:

```bash
mysql -h 127.0.0.1 -u root -P 3307 sik < /path/to/sik.sql
```

**Note:** For large database dumps (1GB+), import in batches:
```bash
# Split SQL file into chunks
split -l 100000 sik.sql sik_chunk_

# Import each chunk
for file in sik_chunk_*; do
    mysql -h 127.0.0.1 -u root -P 3307 sik < $file
done
```

### Step 6: Run Application

Access the application:

- **Frontend (Patient View):**  
  `http://ourkhanz.test/` or `http://localhost:8080/ourkhanz/public/`

- **Backend (Admin Panel):**  
  `http://ourkhanz.test/admin/` or `http://localhost:8080/ourkhanz/public/admin/`

## Project Structure

```
ourkhanz/
├── backend/                          # Backend admin application
│   ├── modules/                      # Backend modules
│   │   ├── pendaftaran/              # Registration management
│   │   ├── master/                   # Master data
│   │   ├── keuangan/                 # Finance
│   │   └── ... (other modules)
│   ├── controllers/
│   ├── views/
│   └── web/                          # Backend web root
├── frontend/                         # Frontend patient application
│   ├── modules/                      # Frontend modules
│   │   ├── pendaftaran/              # Patient registration (booking)
│   │   ├── jadwal/                   # Schedule view
│   │   ├── pasien/                   # Patient info
│   │   └── ... (other modules)
│   ├── controllers/
│   ├── views/
│   └── web/                          # Frontend web root
├── public/                           # Web server document root
│   ├── index.php                     # Frontend entry point
│   ├── admin/
│   │   └── index.php                 # Backend entry point
│   ├── css/                          # Frontend styles
│   ├── js/                           # Frontend scripts
│   └── uploads/                      # User uploads (if any)
├── console/                          # Console application (CLI tasks)
├── common/                           # Shared code (models, configs)
├── vendor/                           # Composer dependencies (auto-generated)
├── .env                              # Environment config (DO NOT COMMIT)
├── .env.example                      # Environment template
└── composer.json                     # Dependency manifest
```

## Key Modules

### Frontend

**pendaftaran** (Patient Registration)
- View available doctor schedules
- Book appointments with real-time quota tracking
- Search and select patients
- View recent registrations in queue

**jadwal** (Schedule Dashboard)
- Display all doctor schedules for a given date
- Show specialties and available slots

**pasien** (Patient Management)
- Patient information lookup

### Backend

Full admin modules for hospital operations:
- pendaftaran, master, setting, keuangan (finance)
- farmasi (pharmacy), lab, radiologi
- igd (emergency), rawatjalan (outpatient), rawatinap (inpatient)
- laporan (reporting), bridging

## API Endpoints

### Patient Registration (Frontend)

**GET** `/pendaftaran/default/schedules`
- Query Parameters:
  - `tanggal_periksa` – Appointment date (YYYY-MM-DD)
  - `recent_page` – Recent registrations page (default: 0)
  - `recent_search` – Search filter for recent queue
- Response: JSON with available schedules + recent registrations

**GET** `/pendaftaran/default/patients`
- Query Parameters:
  - `draw`, `start`, `length` – DataTables pagination
  - `search[value]` – Patient search query
- Response: DataTables-compatible JSON

**GET** `/jadwal/default/schedules`
- Query Parameters:
  - `tanggal_periksa` – Date (YYYY-MM-DD)
- Response: JSON with all schedules for the date

## Configuration

### Database Connection

Edit `common/config/main-local.php` to modify database settings:

```php
'db' => [
    'class' => 'yii\db\Connection',
    'dsn' => $_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;port=3307;dbname=sik',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
],
```

### Application Aliases

Configured in entry points (`public/index.php`, `public/admin/index.php`):

```php
Yii::setAlias('@webroot', dirname(__DIR__) . '/public');
Yii::setAlias('@web', '/');
```

## Security

⚠️ **Important:**

- **Never commit `.env`** – It contains sensitive database credentials
- Use `.env.example` as a template for new installations
- Add `.env` to `.gitignore` (already done)
- Configure proper database user permissions (avoid using root in production)
- Enable HTTPS in production
- Validate and sanitize all user inputs (Yii2 handles most XSS/CSRF automatically)

## Troubleshooting

### 1. "Connection refused" error
- Verify `DB_HOST` and `DB_PORT` in `.env`
- Ensure MySQL/MariaDB service is running
- Check firewall rules for database host access

### 2. "Class not found" or "Module not found"
- Run `composer install` to refresh dependencies
- Clear Yii runtime cache:
  ```bash
  rm -rf frontend/runtime/* backend/runtime/*
  ```

### 3. "404 Not Found" on admin or pendaftaran pages
- Verify `.htaccess` in `public/` and `public/admin/` directories
- Check Apache `mod_rewrite` is enabled

### 4. AJAX requests return 404
- Ensure route is correct: `/module/controller/action`
- Check controller method exists and is public
- Verify module is enabled in `common/config/bootstrap.php`

### 5. Modal not appearing or Bootstrap errors
- Verify `BootstrapPluginAsset` is registered in controller:
  ```php
  \yii\bootstrap5\BootstrapPluginAsset::register($this);
  ```

## Development

### Running Tests

```bash
# TBD - add phpunit configuration
php vendor/bin/phpunit
```

### Code Quality

```bash
# PHP lint
php -l /path/to/file.php

# or check all PHP files
find . -name "*.php" -exec php -l {} \;
```

## Deployment

### Production Checklist

- [ ] Set `YII_ENV=production` and `YII_DEBUG=false` in entry points
- [ ] Configure `.env` with production database credentials
- [ ] Run `composer install --no-dev` to exclude dev dependencies
- [ ] Set proper file permissions (web server write access to `runtime/` and `web/uploads/`)
- [ ] Enable HTTPS with valid SSL certificate
- [ ] Configure database backups
- [ ] Set up error logging and monitoring
- [ ] Review security headers and CORS policies

### Quick Deploy

```bash
git clone https://github.com/suryadragn/ourkhanz.git /var/www/ourkhanz
cd /var/www/ourkhanz
cp .env.example .env
# Edit .env with production settings
composer install --no-dev
chown -R www-data:www-data .
chmod -R 755 frontend/runtime backend/runtime
```

## License

This project is part of SIMRS Khanza ecosystem. See LICENSE file for details.

## Support

For issues, bugs, or feature requests, please create an issue on GitHub:  
https://github.com/suryadragn/ourkhanz/issues

## Authors

- **Developer:** Surya Dragn
- **Framework:** Yii2 Team
- **Original SIMRS:** Khanza

---

**Last Updated:** June 2026  
**Version:** 1.0.0

REQUIREMENTS
------------

> [!IMPORTANT]
> - The minimum required [PHP](https://www.php.net/) version of Yii is PHP `8.2`.

## Install via Composer

If you do not have [Composer](https://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](https://getcomposer.org/doc/00-intro.md#installation-nix).

You can then install this project template using the following commands:

```bash
composer create-project --prefer-dist yiisoft/yii2-app-advanced advanced
cd advanced
```

### Frontend

<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/frontend/home-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/frontend/home-light.png">
    <img src="docs/images/frontend/home-light.png" alt="Web Application Advanced - Frontend">
</picture>

### Backend

<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/backend/home-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/backend/home-light.png">
    <img src="docs/images/backend/home-light.png" alt="Web Application Advanced - Backend">
</picture>

DIRECTORY STRUCTURE
-------------------

```
common
    config/              contains shared configurations
    mail/                contains view files for e-mails
    models/              contains model classes used in both backend and frontend
    tests/               contains tests for common classes
console
    config/              contains console configurations
    controllers/         contains console controllers (commands)
    migrations/          contains database migrations
    models/              contains console-specific model classes
    runtime/             contains files generated during runtime
backend
    assets/              contains application assets such as JavaScript and CSS
    config/              contains backend configurations
    controllers/         contains Web controller classes
    models/              contains backend-specific model classes
    runtime/             contains files generated during runtime
    tests/               contains tests for backend application
    views/               contains view files for the Web application
    web/                 contains the entry script and Web resources
frontend
    assets/              contains application assets such as JavaScript and CSS
    config/              contains frontend configurations
    controllers/         contains Web controller classes
    models/              contains frontend-specific model classes
    runtime/             contains files generated during runtime
    tests/               contains tests for frontend application
    views/               contains view files for the Web application
    web/                 contains the entry script and Web resources
    widgets/             contains frontend widgets
vendor/                  contains dependent 3rd-party packages
environments/            contains environment-based overrides
```

Initialize the application for the `Development` environment:

```bash
php init --env=Development --overwrite=All
```

Now you should be able to access the application through the following URLs, assuming `advanced` is the directory
directly under the Web root.

```
http://localhost/advanced/frontend/web/
http://localhost/advanced/backend/web/
```

## Install with Docker

Build and start the containers:

```bash
docker compose up -d --build
```

Install dependencies inside the container:

```bash
docker compose exec frontend composer update --prefer-dist --no-interaction
```

Initialize the application for the `Development` environment:

```bash
docker compose exec frontend php /app/init --env=Development --overwrite=All
```

After running `init`, update the database connection in `common/config/main-local.php` to use the `mysql`
service hostname:

```php
'db' => [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced',
    'username' => 'yii2advanced',
    'password' => 'secret',
    'charset' => 'utf8',
],
```

You can then access the application through the following URLs:

```
http://127.0.0.1:20080  (frontend)
http://127.0.0.1:21080  (backend)
```

To run the test suite, also update `common/config/test-local.php` to use the `mysql` hostname and create the
test database:

```php
'db' => [
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced_test',
],
```

```bash
docker compose exec -T mysql mysql -uroot -pverysecret -e "CREATE DATABASE IF NOT EXISTS yii2advanced_test; GRANT ALL PRIVILEGES ON yii2advanced_test.* TO 'yii2advanced'@'%'; FLUSH PRIVILEGES;"
docker compose exec -T frontend php /app/yii_test migrate --interactive=0
docker compose exec -T frontend vendor/bin/codecept build
docker compose exec -T frontend vendor/bin/codecept run
```

**NOTES:**
- Minimum required Docker engine version `17.04` for development (see [Performance tuning for volume mounts](https://docs.docker.com/docker-for-mac/osxfs-caching/))
- The default configuration uses a host-volume in your home directory `~/.composer-docker/cache` for Composer caches

CONFIGURATION
-------------

## Database

Edit the file `common/config/main-local.php` with real data, for example:

```php
return [
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'mysql:host=localhost;dbname=yii2advanced',
            'username' => 'root',
            'password' => '1234',
            'charset' => 'utf8',
        ],
    ],
];
```

When using Docker, the MySQL service is pre-configured. Update `common/config/main-local.php` to use:

```php
'db' => [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced',
    'username' => 'yii2advanced',
    'password' => 'secret',
    'charset' => 'utf8',
],
```

Apply migrations:

```bash
php yii migrate
```

Or with Docker:

```bash
docker compose exec frontend php /app/yii migrate
```

**NOTES:**
- Yii won't create the database for you, this has to be done manually before you can access it.
  When using Docker, the MySQL service creates the database automatically.
- Check and edit the other files in the `config/` directories to customize your application as required.
- Refer to the README in the `tests` directory for information specific to application tests.

TESTING
-------

Tests are located in `frontend/tests`, `backend/tests`, and `common/tests` directories.
They are developed with [Codeception PHP Testing Framework](https://codeception.com/).

Tests can be executed by running:

```bash
vendor/bin/codecept run --env php-builtin
```

Or using the Composer script:

```bash
composer tests
```

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?style=for-the-badge&logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=for-the-badge&logo=yii)](https://www.yiiframework.com/)
[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=for-the-badge&logo=telegram)](https://t.me/yii_framework_in_english)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=for-the-badge&logo=slack)](https://yiiframework.com/go/slack)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE.md)
