# SchoolCare — Tech Stack Documentation

> **Smart School Clinic Management System** for Immaculate Conception College of Balayan, Inc.  
> Built and maintained by the SchoolCare dev team.

---

## Table of Contents
1. [Language & Runtime](#1-language--runtime)
2. [Backend Framework](#2-backend-framework)
3. [Frontend Stack](#3-frontend-stack)
4. [Authentication & Authorization](#4-authentication--authorization)
5. [Database & ORM](#5-database--orm)
6. [Third-Party Packages](#6-third-party-packages)
7. [External API Integrations](#7-external-api-integrations)
8. [Architecture Patterns](#8-architecture-patterns)
9. [Developer Tooling](#9-developer-tooling)
10. [Project Module Overview](#10-project-module-overview)

---

## 1. Language & Runtime

| Item        | Detail                         |
|-------------|--------------------------------|
| Language    | PHP **8.2+**                   |
| Runtime env | Local (Windows / Linux / Mac) via `php artisan serve` or Laravel Sail (Docker) |

---

## 2. Backend Framework

### Laravel 12
The entire backend is built on **Laravel 12** — the MVC framework that drives routing, middleware, Eloquent ORM, Blade views, queues, events, and more.

Key Laravel sub-systems used in this project:

| Sub-system         | How it's used |
|--------------------|---------------|
| **Routing**        | `routes/web.php` + `routes/auth.php` for all web routes |
| **Middleware**     | `CheckActiveUser` — custom guard that blocks inactive accounts |
| **Blade**          | Server-side templating for all views |
| **Eloquent ORM**   | All database models (`Patient`, `Appointment`, `Medicine`, etc.) |
| **Migrations**     | Full schema version control under `database/migrations/` |
| **Seeders**        | Role/permission seeder, medicine category seeder, settings seeder, admin user seeder |
| **Service Provider** | `AppServiceProvider` wires up repository bindings and boots observers |
| **Policies**       | `PatientPolicy`, `AppointmentPolicy`, `ConsultationPolicy`, `MedicinePolicy` — gate per-action authorization |
| **Form Requests**  | `StorePatientRequest`, `StoreAppointmentRequest`, etc. — validation lives here, not in controllers |
| **Observers**      | `PatientObserver`, `AppointmentObserver`, `ConsultationObserver`, `MedicineObserver` — react to model lifecycle events (used for audit logging) |
| **Carbon**         | Date/time formatting throughout the app (shipped with Laravel) |

---

## 3. Frontend Stack

### Vite 6 (Build Tool)
Asset bundling. Runs in dev mode with HMR via `npm run dev`; production build via `npm run build`. Wired into Laravel via the `laravel-vite-plugin`.

### Bootstrap 5.3 + Bootstrap Icons 1.11
The entire UI is built with **Bootstrap 5** for layout, components, and utilities. **Bootstrap Icons** provides the icon set (no Font Awesome).

### Sass 1.77
Custom styles are written in **SCSS** and compiled through Vite + Sass. Keeps styles modular and supports variables/nesting.

### Axios 1.8
Used for AJAX/HTTP requests from the browser (ships with Bootstrap's JS bundle tooling; available globally via Laravel's default setup).

### Blade Templating
All HTML is server-rendered via **Laravel Blade**. No JavaScript SPA framework (no Vue, React, or Alpine). The app is a traditional multi-page application (MPA).

---

## 4. Authentication & Authorization

### Laravel Breeze 2.4 (Authentication Scaffolding)
Provides the login, register, password reset, email verification, and profile pages out of the box. Views are located in `resources/views/auth/` and `resources/views/profile/`.

### Spatie Laravel Permission 6.25 (RBAC)
Role-Based Access Control library. Every user is assigned one or more **roles**, and roles have **permissions**. 

- Roles and permissions are seeded via `database/seeders/RolePermissionSeeder.php`
- Role management UI: `resources/views/admin/roles/`
- User role assignment UI: `resources/views/admin/users/`
- Guards: uses Spatie's `@can` / `@role` Blade directives and `$this->authorize()` in controllers

---

## 5. Database & ORM

### Database Engine
- Default (local): **SQLite** (`database/database.sqlite`) — auto-created on first install
- Can be swapped to **MySQL / PostgreSQL** by changing `DB_CONNECTION` in `.env`

### Eloquent ORM (Laravel built-in)
All data access goes through Eloquent models. Key models:

| Model                  | Table                      | Notes |
|------------------------|----------------------------|-------|
| `User`                 | `users`                    | Authenticated staff accounts |
| `Patient`              | `patients`                 | Core entity; supports multiple categories |
| `Appointment`          | `appointments`             | Linked to patient + time slot |
| `AppointmentTimeSlot`  | `appointment_time_slots`   | Seeded available slots |
| `Consultation`         | `consultations`            | Medical visit records |
| `MedicineCategory`     | `medicine_categories`      | Grouping for medicines |
| `Medicine`             | `medicines`                | Catalog with stock + expiry |
| `InventoryTransaction` | `inventory_transactions`   | Every stock movement (in / out / dispensed) |
| `DispensingRecord`     | `dispensing_records`       | Links dispensed medicines to consultations |
| `SmsLog`               | `sms_logs`                 | Log of every SMS sent |
| `AuditLog`             | `audit_logs`               | Before/after change log for all major models |
| `AiConversation`       | `ai_conversations`         | Chat history with Cobi AI |
| `Setting`              | `settings`                 | Key-value app configuration (clinic name, SMS templates, AI model, etc.) |
| `PatientLog`           | `patient_logs`             | Additional activity notes per patient |

---

## 6. Third-Party Packages

### barryvdh/laravel-dompdf `^3.1`
Generates PDF reports (daily, monthly, annual, medicine usage, inventory, appointments). Blade views under `resources/views/reports/pdf/` are rendered to PDF and streamed to the browser.

**How to use:**
```php
use Barryvdh\DomPDF\Facade\Pdf;

$pdf = Pdf::loadView('reports.pdf.daily', compact('data'));
return $pdf->download('report.pdf');
// or ->stream('report.pdf') to open in browser
```

### maatwebsite/excel `^1.1`
Handles CSV/Excel export for reports.

**How to use:**
```php
use Maatwebsite\Excel\Facades\Excel;

Excel::create('filename', function ($excel) use ($data) {
    $excel->sheet('Sheet 1', function ($sheet) use ($data) {
        $sheet->fromArray($data);
    });
})->download('xlsx');
```

### guzzlehttp/guzzle `^7.9`
HTTP client used internally by:
- `AiAssistantService` — calls the Groq API
- `SmsService` — calls the Semaphore API

---

## 7. External API Integrations

### Groq API (AI Assistant — "Cobi")
- **Service file:** `app/Services/AiAssistantService.php`
- **Endpoint:** `https://api.groq.com/openai/v1/chat/completions`
- **Default model:** `llama-3.3-70b-versatile` (configurable via Settings)
- **Env variable:** `GROQ_API_KEY` in `.env`
- Sends last 5 conversations as context for continuity
- System prompt configures Cobi's personality and knowledge of the SchoolCare system

To change the AI model: go to **Admin → Settings → AI Model** in the app, or set `ai_model` in the `settings` table.

### Semaphore API (SMS Notifications)
- **Service file:** `app/Services/SmsService.php`
- **Endpoint:** `https://api.semaphore.co/api/v4/messages`
- **Config file:** `config/semaphore.php`
- **Env variables:** `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME`
- Used for appointment approval and cancellation SMS notifications
- SMS templates are customizable via Settings (`sms_template_approval`, `sms_template_cancellation`)
- All SMS attempts are logged in `sms_logs` table regardless of success/failure

---

## 8. Architecture Patterns

This project follows **standard Laravel conventions** plus a few additional patterns for maintainability:

### Service Layer
Business logic lives in `app/Services/`, not in controllers. Controllers are kept thin — they call services, then redirect/return views.

| Service                  | Responsibility |
|--------------------------|----------------|
| `AppointmentService`     | Appointment CRUD + status transitions |
| `ConsultationService`    | Consultation CRUD |
| `DispensingService`      | Dispense medicines + inventory deduction |
| `InventoryService`       | Stock-in / stock-out ledger management |
| `PatientService`         | Patient CRUD business logic |
| `ReportService`          | Data aggregation for reports |
| `SmsService`             | SMS sending + logging |
| `AiAssistantService`     | Groq API chat + conversation history |
| `AuditLogService`        | Write audit log entries |

### Repository Pattern (Patient only)
`PatientRepositoryInterface` + `PatientRepository` — the interface is bound in `AppServiceProvider`. Allows swapping data source without touching controllers or services.

### Observer Pattern (Model Observers)
Observers hook into Eloquent model events (`created`, `updated`, `deleted`) to trigger side effects automatically:
- `PatientObserver` → audit log on patient changes
- `AppointmentObserver` → audit log on appointment changes
- `ConsultationObserver` → audit log on consultation changes
- `MedicineObserver` → audit log on medicine changes

All registered in `AppServiceProvider::boot()`.

### Policy-Based Authorization
Each major resource has a Policy class (`PatientPolicy`, `AppointmentPolicy`, etc.). Controllers call `$this->authorize('action', $model)`. Blade views use `@can('action', $model)`.

### Form Request Validation
All incoming request data is validated in dedicated **Form Request** classes (`app/Http/Requests/`), not inline in controllers. This keeps controllers clean and validation reusable.

---

## 9. Developer Tooling

### Laravel Breeze (Auth scaffolding)
Run `php artisan breeze:install` to regenerate if needed.

### Laravel Pint (Code Style)
PSR-12 code formatter for PHP.
```bash
./vendor/bin/pint
```

### Laravel Pail (Log Viewer)
Real-time log tailing in the terminal.
```bash
php artisan pail
```

### Laravel Sail (Docker — optional)
Docker environment for local dev. Uses the `sail` script.
```bash
./vendor/bin/sail up
```

### PHPUnit 11 (Testing)
Test files in `tests/`. Run with:
```bash
php artisan test
# or
./vendor/bin/phpunit
```

### Faker + Mockery (Test Helpers)
- **Faker** — generates fake data for factories
- **Mockery** — mocking library for unit tests

### Collision (Better Error Display)
Pretty error output in the terminal when running `php artisan test`.

### Concurrently (Dev Server)
The `composer dev` script runs the Laravel server, queue listener, and Vite dev server together:
```bash
composer dev
# Equivalent to running all three:
# php artisan serve
# php artisan queue:listen --tries=1
# npm run dev
```

---

## 10. Project Module Overview

| Module             | Controller                        | Views Folder                |
|--------------------|-----------------------------------|-----------------------------|
| Dashboard          | `DashboardController`             | `dashboard/`                |
| Patients           | `PatientController`               | `patients/`                 |
| Patient Logs       | `PatientLogController`            | `patient-logs/`             |
| Appointments       | `AppointmentController`           | `appointments/`             |
| Consultations      | `ConsultationController`          | `consultations/`            |
| Medicines          | `MedicineController`              | `medicines/`                |
| Medicine Categories| `MedicineCategoryController`      | `medicine-categories/`      |
| Inventory          | `InventoryController`             | `inventory/`                |
| Dispensing         | `DispensingController`            | `dispensing/`               |
| Reports            | `ReportController`                | `reports/` + `reports/pdf/` |
| SMS Notifications  | `SmsController`                   | `sms/`                      |
| AI Assistant       | `AiAssistantController`           | `ai-assistant/`             |
| Users (Admin)      | `Admin\UserController`            | `admin/users/`              |
| Roles (Admin)      | `Admin\RoleController`            | `admin/roles/`              |
| Audit Logs (Admin) | `Admin\AuditLogController`        | `admin/audit-logs/`         |
| Settings (Admin)   | `Admin\SettingsController`        | `admin/settings/`           |

---

## Quick Start (Local Setup)

```bash
# 1. Install PHP dependencies
composer install

# 2. Install JS dependencies
npm install

# 3. Copy environment file and set your values
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Run migrations + seeders
php artisan migrate --seed

# 6. Start dev servers (Laravel + Vite + Queue)
composer dev
```

**Required `.env` keys to fill in:**
```env
DB_CONNECTION=sqlite          # or mysql / pgsql
GROQ_API_KEY=                 # Get from console.groq.com
SEMAPHORE_API_KEY=            # Get from semaphore.co
SEMAPHORE_SENDER_NAME=ICCBI
```

---

*Last updated: June 2026*
