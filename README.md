# beste.schule – Nextcloud App

Nextcloud integration for [beste.schule](https://beste.schule) that syncs student grades and the school journal into Nextcloud.

## Features

- **Grades view (Noten)** — view all grade entries per student with subject, date, teacher, collection type, and weighted average
- **Final grades (Endnoten)** — grouped by semester (Halbjahr)
- **Calendar sync** — school journal lessons and notes are pushed as events into any Nextcloud calendar
- **Background sync** — a timed background job refreshes all accounts at their configured interval
- **Admin panel** — admins can add/remove/sync beste.schule accounts for any Nextcloud user
- **Personal settings** — users can connect their own account without admin involvement
- **OCS API** — all data is also accessible via the standard Nextcloud OCS REST API

## Installation

Copy the `beste_schule` directory into your Nextcloud `apps/` folder, then enable it:

```bash
cp -r beste_schule /var/www/html/nextcloud/apps/
php occ app:enable beste_schule
php occ migrations:migrate beste_schule
```

## OCS API endpoints

Base path: `/apps/beste_schule/api/v1` (Relative to Nextcloud root)

| Method | Path | Description |
|--------|------|-------------|
| GET    | `/accounts`              | List own accounts |
| POST   | `/accounts`              | Add own account |
| PUT    | `/accounts/{id}`         | Update own account |
| DELETE | `/accounts/{id}`         | Remove own account |
| POST   | `/accounts/{id}/sync`    | Trigger manual sync |
| POST   | `/validate`              | Validate a token |
| GET    | `/grades`                | Get cached grades |
| GET    | `/finalgrades`           | Get cached final grades |
| GET    | `/admin/accounts`        | (admin) List all accounts |
| POST   | `/admin/accounts`        | (admin) Add account for any user |
| DELETE | `/admin/accounts/{id}`   | (admin) Delete any account |
| POST   | `/admin/accounts/{id}/sync` | (admin) Sync any account |

All responses follow the standard OCS envelope:
```json
{
  "ocs": {
    "meta": { "status": "ok", "statuscode": 200 },
    "data": { ... }
  }
}
```

## Token setup

1. Log in to [beste.schule](https://beste.schule)
2. Top-right menu → **Benutzerkonto** → **API** tab → **Personal Access Token**
3. Paste the token in the Nextcloud admin panel or personal settings

## Architecture

```
beste_schule/
├── appinfo/
│   ├── info.xml          # App manifest, background job, settings registration
│   └── routes.php        # Page routes + OCS API routes
├── lib/
│   ├── AppInfo/Application.php
│   ├── BackgroundJob/SyncJob.php     # TimedJob: runs every hour, syncs due accounts
│   ├── Controller/
│   │   ├── ApiController.php         # OCS endpoints (own + admin)
│   │   ├── GradesController.php      # HTML grades page
│   │   └── AdminController.php       # HTML admin page
│   ├── Db/                           # Entities + QBMapper classes
│   │   ├── Account{,Mapper}.php
│   │   ├── Grade{,Mapper}.php
│   │   └── FinalGrade{,Mapper}.php
│   ├── Exception/
│   │   ├── AuthException.php         # 401/403 from beste.schule
│   │   └── BesteSchuleException.php  # Other API errors
│   ├── Migration/
│   │   └── Version000000Date20260608000000.php   # Creates 3 DB tables
│   ├── Service/
│   │   ├── BesteSchuleService.php    # HTTP client for the REST API
│   │   ├── AccountService.php        # CRUD + token encryption
│   │   └── SyncService.php           # Orchestrates grades + calendar sync
│   └── Settings/
│       ├── AdminSettings.php         # Settings → Administration
│       └── PersonalSettings.php      # Settings → Personal
├── templates/
│   ├── admin.php
│   ├── grades.php
│   └── personal.php
├── js/
│   ├── admin.js
│   ├── grades.js
│   └── personal.js
└── css/style.css
```
