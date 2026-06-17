# Field Service Operations System — Template Documentation

A reusable template/blueprint based on a working Laravel + React (Inertia) lawn-care operations system. The architecture is generic enough to adapt to **any recurring field-service business** — cleaning, pest control, HVAC maintenance, pool service, landscaping, snow removal, etc.

---

## 1. What This System Does

A web app for small/medium field-service companies to manage:

- A roster of **clients** (customers + the recurring services they receive)
- A catalog of **services** the company offers (with default price + duration)
- **Recurring schedules** that auto-generate dated **jobs** (once / weekly / biweekly / monthly)
- A **calendar** and **job board** for crews to see their day
- **Clock in / clock out** time tracking for employees
- **Service-summary reports** with CSV export for billing/payroll context

Two user roles: **admin** (office staff — full access) and **crew** (field workers — see jobs assigned to them, clock in/out, mark jobs complete).

---

## 2. Tech Stack

### Backend
| Package | Version | Purpose |
|---|---|---|
| PHP | 8.3+ (8.4 supported) | Runtime |
| Laravel Framework | 13.x | Core framework |
| Laravel Fortify | 1.x | Authentication backend (login, register, password reset, email verify, 2FA) |
| Inertia.js for Laravel | 3.x | Server-driven SPA bridge (no separate API) |
| Laravel Wayfinder | 0.1.x | Generates typed TS functions for Laravel routes/controllers |
| Laravel Tinker | 3.x | REPL |
| Laravel Boost | 2.x (dev) | MCP server for AI-assisted dev |
| Laravel Pint | 1.x (dev) | PHP code formatter |
| Laravel Pail | 1.x (dev) | Real-time log tailing |
| Laravel Sail | 1.x (dev) | Docker dev environment |
| PHPUnit | 12.x (dev) | Testing |

### Frontend
| Package | Version | Purpose |
|---|---|---|
| React | 19.x | UI |
| @inertiajs/react | 3.x | Inertia client |
| @inertiajs/vite | 3.x | Vite plugin + SSR |
| TypeScript | 5.7+ | Types |
| Vite | 8.x | Bundler |
| Tailwind CSS | 4.x | Styling |
| @tailwindcss/vite | 4.x | Tailwind Vite plugin |
| Radix UI primitives | 1.x / 2.x | Headless component foundation (dialog, dropdown, select, etc.) |
| lucide-react | 0.475+ | Icon set |
| sonner | 2.x | Toast notifications |
| class-variance-authority, clsx, tailwind-merge | latest | Class composition utilities |
| input-otp | 1.x | 2FA OTP input |
| @laravel/vite-plugin-wayfinder | 0.1.x | Auto-regenerates TS bindings on save |

### Tooling
- ESLint 9 + typescript-eslint 8
- Prettier 3 + prettier-plugin-tailwindcss
- React Compiler (babel plugin) enabled
- Concurrently (run server + queue + logs + vite together with `composer run dev`)

### Database
- SQLite by default (zero-config local dev)
- Any Laravel-supported DB (MySQL, PostgreSQL, MariaDB) for production — just swap `.env`

---

## 3. Architecture Overview

```
Browser (React 19 + Inertia)
   │  ▲
   │  │  Inertia visits (JSON page props, not REST)
   ▼  │
Laravel Controllers (Inertia::render('page-name', $props))
   │
   ▼
Eloquent Models → SQL DB
```

- **No separate API layer.** Inertia ships server-rendered prop payloads to React pages — controllers return `Inertia::render('clients/index', [...])` instead of JSON.
- **Wayfinder** generates `@/actions/...` and `@/routes/...` TypeScript files so the frontend calls Laravel endpoints with full type safety and no hardcoded URLs.
- **Fortify** owns all auth routes (`/login`, `/register`, `/forgot-password`, `/two-factor-challenge`, …). Customizations live in `app/Actions/Fortify/` and `app/Providers/FortifyServiceProvider.php`.
- **Middleware** `EnsureUserIsAdmin` (alias `admin`) gates admin-only routes.

---

## 4. Modules / Domain Model

### 4.1 Clients (`clients` table)
The customers who receive services.

Fields: `customer_name`, `company_name`, `address`, `city`, `state`, `zip`, `phone`, `email`, `notes`, `is_active`.

Relationships:
- `hasMany RecurringSchedule`
- `hasMany ServiceJob`
- `belongsToMany Service` (pivot: `billing_type`, `contract_price_cents`, `notes`, `is_active`)

The pivot table lets you say "Client X gets Service Y at a custom contract price."

### 4.2 Services (`services` table)
The catalog of work the company offers.

Fields: `name`, `category`, `default_duration_minutes`, `default_price_cents`, `is_active`.

Use cases (template-wide):
- Lawn care: "Mowing", "Hedge trimming", "Fertilization"
- Cleaning: "Standard clean", "Deep clean", "Move-out clean"
- Pool service: "Weekly chemical check", "Filter clean"
- HVAC: "AC tune-up", "Filter swap"

### 4.3 Recurring Schedules (`recurring_schedules` table)
A template that says "do these services for this client, every X, starting Y, ending Z."

Fields: `client_id`, `title`, `start_date`, `end_date`, `start_time`, `recurrence_type`, `day_of_week`, `is_active`, `notes`.

`recurrence_type` ∈ `{once, weekly, biweekly, monthly}`. Many-to-many with `services`.

### 4.4 Service Jobs (`service_jobs` table)
A single concrete instance of work to do on a specific date — generated from a recurring schedule, or created ad-hoc.

Fields: `recurring_schedule_id`, `client_id`, `scheduled_date`, `scheduled_time`, `status`, `notes`, `completed_at`, `started_at`, `ended_at`, `started_by_user_id`, `ended_by_user_id`, `actual_price_cents`.

Status state machine: `scheduled → in_progress → completed` (with side-paths to `skipped`, `rescheduled`, `cancelled`).

`RecurringJobGenerator` service ([app/Services/RecurringJobGenerator.php](app/Services/RecurringJobGenerator.php)) materializes jobs from schedules. It is **idempotent** — re-running it never duplicates jobs for the same `(schedule_id, date, time)` triple.

### 4.5 Time Cards (`time_cards` table)
Employee clock-in/clock-out for hours tracking.

Fields: `user_id`, `clock_in_at`, `clock_out_at`, `notes`.

Surfaced via a header widget ([resources/js/components/lawnops/clock-in-out-button.tsx](resources/js/components/lawnops/clock-in-out-button.tsx)) that ticks elapsed time every second on mobile and desktop.

### 4.6 Users (`users` table)
With `role` column: `admin` or `crew`. 2FA fields via Fortify migration.

### 4.7 Reports
Service-summary report ([app/Http/Controllers/ReportController.php](app/Http/Controllers/ReportController.php)) with date-range + client filters, plus a streamed CSV export. Computes effective price per job: `actual_price_cents ?? client contract price ?? service default price`.

### 4.8 Calendar
Read-only month/week view of upcoming jobs (`CalendarController`).

### 4.9 Dashboard
KPI tiles for: jobs today, jobs this week, completed this week, skipped/rescheduled this week, active client count + today's job list.

### 4.10 Settings
Profile, password, 2FA setup with QR + recovery codes, appearance (light/dark/system).

---

## 5. Route Map

### Shared (admin + crew)
| Method | Path | Purpose |
|---|---|---|
| GET | `/dashboard` | KPI dashboard |
| GET | `/calendar` | Job calendar |
| GET | `/jobs` | Job list |
| GET | `/jobs/{job}` | Job detail |
| PUT | `/jobs/{job}` | Update job |
| POST | `/jobs/{job}/start` | Mark in-progress + timestamp |
| POST | `/jobs/{job}/finish` | Set ended_at |
| POST | `/jobs/{job}/complete` | Mark completed |
| POST | `/jobs/{job}/skip` | Mark skipped |
| POST | `/jobs/{job}/cancel` | Mark cancelled |
| GET | `/time-card` | Time card index |
| POST | `/time-card/clock-in` | Open card |
| POST | `/time-card/clock-out` | Close card |

### Admin-only
| Path | Purpose |
|---|---|
| `clients/*` (resource + service attach/detach) | Manage clients & their services |
| `services/*` (resource) | Manage service catalog |
| `schedules/*` (resource + regenerate) | Manage recurring schedules |
| `employees/*` (resource) | Manage employees/users |
| `reports/service-summary` | Filtered report view |
| `reports/service-summary/export` | CSV download |
| `DELETE /jobs/{job}` | Hard delete job |

All auth routes (`/login`, `/register`, etc.) are registered automatically by Fortify.

---

## 6. Directory Layout

```
app/
├── Actions/Fortify/         # CreateNewUser, ResetUserPassword
├── Concerns/                # Password & profile validation traits
├── Http/
│   ├── Controllers/         # Thin controllers, one per module
│   │   └── Settings/        # Profile, security
│   ├── Middleware/          # EnsureUserIsAdmin, HandleInertiaRequests, HandleAppearance
│   └── Requests/            # Form Request classes (validation)
├── Models/                  # Client, Service, RecurringSchedule, ServiceJob, TimeCard, User
├── Providers/               # AppServiceProvider, FortifyServiceProvider
└── Services/                # RecurringJobGenerator (domain logic)

database/
├── factories/               # Model factories for tests/seeders
├── migrations/              # Schema
└── seeders/                 # Demo seeders dropped from default DatabaseSeeder

resources/
├── css/                     # Tailwind entry
└── js/
    ├── actions/             # Wayfinder-generated controller bindings (auto)
    ├── routes/              # Wayfinder-generated route bindings (auto)
    ├── components/
    │   ├── ui/              # Radix-based primitives (button, dialog, input, …)
    │   └── lawnops/         # Domain components — rename per project
    ├── layouts/             # App shell, auth, settings layouts
    ├── pages/               # Inertia pages — folder matches Inertia::render() name
    │   ├── auth/
    │   ├── calendar/
    │   ├── clients/
    │   ├── employees/
    │   ├── jobs/
    │   ├── reports/
    │   ├── schedules/
    │   ├── services/
    │   ├── settings/
    │   └── time-card/
    ├── hooks/
    ├── lib/
    └── types/

routes/
├── web.php                  # Main app routes
├── settings.php             # Settings routes
└── auth.php                 # (Fortify auto-registers; this is for overrides)
```

---

## 7. Adapting to Another Company

Most renames will be **one find-and-replace** because the domain language is intentionally generic (`Client`, `Service`, `ServiceJob`, `RecurringSchedule`).

### Step-by-step
1. **Clone & rename** — `composer.json` `name`, `package.json` `private`, app name in `.env` (`APP_NAME`).
2. **Pick what to keep** — every module is independent. Drop modules you don't need by deleting:
   - the migration
   - the model
   - the controller(s)
   - the FormRequest(s)
   - the route block in `routes/web.php`
   - the page folder in `resources/js/pages/`
   - the nav entry in `resources/js/components/app-sidebar.tsx`
3. **Rename domain components folder** — `resources/js/components/lawnops/` → `resources/js/components/<your-domain>/`.
4. **Adjust services & billing** — change `category` enum on `Service`, swap "lawn" wording in seed data, adapt CSV columns in `ReportController`.
5. **Add company-specific fields** — write a new migration, add to `#[Fillable]` on the model, update the Inertia page form.
6. **Configure auth** — toggle features in `config/fortify.php` (registration on/off, 2FA enforced, email verification required).
7. **Rebrand** — `resources/js/components/app-logo*.tsx`, favicon at `public/favicon.ico`, Tailwind colors in `resources/css/app.css`.

### Common shape variations
| Business | Map `Client` to | Map `Service` to | Map `ServiceJob` to |
|---|---|---|---|
| Cleaning company | Property / household | Cleaning package | Visit |
| HVAC | Site | Maintenance plan item | Service call |
| Pool service | Pool | Treatment | Visit |
| Pest control | Account | Treatment type | Treatment visit |
| Snow removal | Property | Plowing/salting | Push |

---

## 8. Getting Started (Local Dev)

```bash
# One-time
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Run everything (server + queue + logs + vite)
composer run dev

# Or piecewise
php artisan serve
npm run dev
php artisan queue:listen
php artisan pail
```

Useful commands:
- `php artisan test --compact` — full test suite
- `vendor/bin/pint --dirty --format agent` — format changed PHP
- `npm run lint` / `npm run format` / `npm run types:check`
- `php artisan route:list --except-vendor` — see app routes

---

## 9. Notable Patterns Worth Keeping

- **Money in cents** — every price field is `*_cents` (integer). Avoids float rounding. Format on render.
- **Idempotent generators** — `RecurringJobGenerator` uses a unique index `(recurring_schedule_id, scheduled_date, scheduled_time)` so regeneration is safe.
- **Status as string constants on model** — `ServiceJob::STATUS_SCHEDULED` etc., with a `STATUSES` array for validation.
- **Pivot tables carry meaningful data** — `client_service` has `billing_type` + `contract_price_cents`, so contract pricing is a property of the relationship, not the service.
- **Form Request classes per write action** — never validate inside controllers.
- **PHP 8 attributes for model config** — `#[Fillable([...])]` and `#[Hidden([...])]` instead of property arrays.
- **PHP 8.4 property promotion** in service constructors.
- **Wayfinder-typed frontend calls** — no hardcoded URLs in TSX.

---

## 10. Auth Features Provided

Out of the box via Fortify:
- Email + password login
- Registration (toggleable)
- Password reset via email
- Email verification
- Two-factor authentication (TOTP) with QR setup and recovery codes
- Password confirmation gate for sensitive routes
- Login rate limiting
- Profile update + password change + account deletion

---

## 11. Testing

PHPUnit 12 feature + unit tests under `tests/`. Run with:

```bash
php artisan test --compact
php artisan test --compact --filter=ClientControllerTest
```

Factories exist for every model (`database/factories/`). Use `--phpunit` flag when creating new tests (no Pest in this project).

---

## 12. License & Origin

Base skeleton: `laravel/react-starter-kit` (MIT). Domain layer is custom.
