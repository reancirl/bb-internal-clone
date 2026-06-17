# BuffaloBuilt Internal — Modules, CRUD & Permissions

Each module = one bounded slice of functionality (controllers + Inertia pages + routes + permissions). Maps to the modules described in [01-overview.md](01-overview.md) and the tables in [02-data-model.md](02-data-model.md).

---

## 1. Permissions matrix

`A` = admin only · `C` = crew may also do (within their scope) · `–` = no access.

| Module | List/View | Create | Edit | Delete | Notes |
|---|:---:|:---:|:---:|:---:|---|
| Users / Employees | A | A | A | A | Crew can view their own profile only |
| Time Cards | A · C(own) | A · C(own) | A · C(own open) | A | Crew can only see / edit their own; admin sees all |
| Projects | A · C(assigned) | A | A | A (soft) | Crew sees projects they have jobs on |
| Project Dimensions | A · C(read) | A | A | – | Dimensions are office data |
| Project Material Decisions | A · C(read) | A | A | A | Customer-facing decisions |
| Project Material Orders | A · C(status only) | A | A · C(`ordered_at`/`on_site_at`) | A | Crew can mark items ordered/on-site from the field |
| Project Budget | A | A | A | A | Office-only |
| Project Jobs | A · C(assigned) | A | A · C(start/finish/complete) | A | Crew can change status of their own jobs |
| Material Catalog | A | A | A | A (archive) | |
| Decision Catalog | A | A | A | A (archive) | |
| Rate Catalog | A | A | A | A (archive) | |
| Budget Catalog | A | A | A | A (archive) | |
| Trade Partners | A · C(read) | A | A | A (archive) | Crew may want to call a sub — read OK |
| Reports / CSV | A | – | – | – | |

All crew-accessible endpoints are gated by [`auth`](../bootstrap/app.php). All admin-only endpoints are additionally gated by [`admin`](../app/Http/Middleware/EnsureUserIsAdmin.php).

---

## 2. Module Details

Each section below follows the same shape:

> **Purpose** — one sentence.
> **Routes** — HTTP verb, path, controller@method, middleware.
> **Pages** — Inertia page paths.
> **Notes** — special validation / business rules.

### 2.1 Auth & Users

> Login/register/reset are inherited from the starter kit. Employees module is the admin's user-management UI on top.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /login` | `Auth\AuthenticatedSessionController@create` | `auth/login` | guest |
| `POST /login` | `Auth\AuthenticatedSessionController@store` | — | guest |
| `POST /logout` | `Auth\AuthenticatedSessionController@destroy` | — | auth |
| `GET /register`, `POST /register` | `Auth\RegisteredUserController` | `auth/register` | guest |
| `GET /forgot-password`, `POST …` | `Auth\PasswordResetLinkController` | `auth/forgot-password` | guest |
| `GET /reset-password/{token}`, `POST /reset-password` | `Auth\NewPasswordController` | `auth/reset-password` | guest |
| `GET /verify-email`, `GET /verify-email/{id}/{hash}`, `POST /email/verification-notification` | `Auth\Email*Controller` | `auth/verify-email` | auth |
| `GET /settings/profile`, `PATCH …`, `DELETE …` | `Settings\ProfileController` | `settings/profile` | auth |
| `GET /settings/password`, `PUT …` | `Settings\PasswordController` | `settings/password` | auth |
| `GET /settings/appearance` | (closure) | `settings/appearance` | auth |
| `GET /admin/employees` | `Admin\EmployeeController@index` | `admin/employees/index` | auth + admin |
| `GET /admin/employees/create` | `…@create` | `admin/employees/create` | auth + admin |
| `POST /admin/employees` | `…@store` | — | auth + admin |
| `GET /admin/employees/{user}` | `…@show` | `admin/employees/show` | auth + admin |
| `PUT /admin/employees/{user}` | `…@update` | — | auth + admin |
| `DELETE /admin/employees/{user}` | `…@destroy` | — | auth + admin |

**Notes**
- Registration may be disabled in production via `config/fortify.php`-style flag, since employees should be created by an admin (TBD M2).
- Admin can change a user's `role` from the employee edit page.
- Cannot delete yourself.

### 2.2 Time Cards (Clock In / Clock Out)

> Crew taps "Clock In" in the header. App opens a `time_cards` row with `clock_out_at = null`. Tapping "Clock Out" sets the timestamp.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /time-card` | `TimeCardController@index` | `time-card/index` | auth |
| `POST /time-card/clock-in` | `…@clockIn` | — | auth |
| `POST /time-card/clock-out` | `…@clockOut` | — | auth |
| `GET /admin/time-card` | `Admin\TimeCardController@index` | `admin/time-card/index` | auth + admin |
| `PUT /admin/time-card/{card}` | `Admin\TimeCardController@update` | — | auth + admin |
| `DELETE /admin/time-card/{card}` | `Admin\TimeCardController@destroy` | — | auth + admin |

**Notes**
- Open-card rule: a crew may only have one open `time_cards` row at a time. `clockIn` rejects with 422 if an open card exists.
- Optional `project_job_id` on clock-in lets crew tag their hours to a job.
- A header widget shows elapsed time, ticking every second on mobile and desktop — matches the lawnops [clock-in-out-button.tsx](../resources/js/components/lawnops/clock-in-out-button.tsx) reference (rename to `buffalobuilt/`).

### 2.3 Projects

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /projects` | `ProjectController@index` | `projects/index` | auth (crew sees only their assigned) |
| `GET /projects/{project}` | `ProjectController@show` | `projects/show` | auth |
| `GET /admin/projects/create` | `…@create` | `projects/create` | auth + admin |
| `POST /admin/projects` | `…@store` | — | auth + admin |
| `GET /admin/projects/{project}/edit` | `…@edit` | `projects/edit` | auth + admin |
| `PUT /admin/projects/{project}` | `…@update` | — | auth + admin |
| `DELETE /admin/projects/{project}` | `…@destroy` | — | auth + admin (soft) |
| `POST /admin/projects/{project}/restore` | `…@restore` | — | auth + admin |

**Notes**
- Index filters: `status`, `city`, `search` (customer name / project name).
- Show page is the project hub — tabs for Dimensions, Decisions, Orders, Budget, Jobs.

### 2.4 Project Dimensions

> 1:1 with project. UI is a single form with all dimension fields grouped.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /admin/projects/{project}/dimensions` | `Project\DimensionController@edit` | `projects/dimensions` | auth + admin |
| `PUT /admin/projects/{project}/dimensions` | `…@update` | — | auth + admin |

`upsert` on save — row is created if missing, updated otherwise.

### 2.5 Project Material Decisions

> Walk the customer through their choice list. UI groups by decision category.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /admin/projects/{project}/decisions` | `Project\DecisionController@index` | `projects/decisions/index` | auth + admin |
| `POST /admin/projects/{project}/decisions` | `…@store` | — | auth + admin |
| `PUT /admin/projects/{project}/decisions/{decision}` | `…@update` | — | auth + admin |
| `POST /admin/projects/{project}/decisions/{decision}/confirm` | `…@confirm` | — | auth + admin |
| `DELETE /admin/projects/{project}/decisions/{decision}` | `…@destroy` | — | auth + admin |

**Notes**
- "Generate from catalog" action creates a row per `decision_item` so the office can fill them in linearly.
- `confirm` stamps `confirmed_at` + `confirmed_by_user_id`.

### 2.6 Project Material Orders

> Per-project line items. Generated from the Material catalog, then edited / supplied / ordered.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /projects/{project}/orders` | `Project\OrderController@index` | `projects/orders/index` | auth (crew read-only) |
| `POST /admin/projects/{project}/orders/generate` | `…@generate` | — | auth + admin |
| `POST /admin/projects/{project}/orders` | `…@store` | — | auth + admin |
| `PUT /admin/projects/{project}/orders/{order}` | `…@update` | — | auth + admin |
| `POST /projects/{project}/orders/{order}/mark-ordered` | `…@markOrdered` | — | auth (admin or assigned crew) |
| `POST /projects/{project}/orders/{order}/mark-on-site` | `…@markOnSite` | — | auth (admin or assigned crew) |
| `DELETE /admin/projects/{project}/orders/{order}` | `…@destroy` | — | auth + admin |

**Notes**
- `generate` creates one order row per active `material` (skip if a row already exists). Idempotent.
- `mark-ordered` / `mark-on-site` are toggle-able — second call clears the timestamp.
- Index UI groups by `material.material_category.sort_order`.

### 2.7 Project Budget

> Bid-vs-actual grid per project.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /admin/projects/{project}/budget` | `Project\BudgetController@index` | `projects/budget/index` | auth + admin |
| `POST /admin/projects/{project}/budget/generate` | `…@generate` | — | auth + admin |
| `PUT /admin/projects/{project}/budget/{line}` | `…@update` | — | auth + admin |
| `GET /admin/projects/{project}/budget/export.csv` | `…@export` | — | auth + admin |

`generate` populates a row per `budget_line_definition` so the office can fill bid/actual values.

### 2.8 Project Jobs

> Dated work assignments. Crew dashboard, "today's jobs" widget pulls from here.

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /jobs` | `JobController@index` | `jobs/index` | auth (crew sees their own) |
| `GET /jobs/{job}` | `JobController@show` | `jobs/show` | auth (must be assigned or admin) |
| `POST /admin/projects/{project}/jobs` | `Admin\JobController@store` | — | auth + admin |
| `PUT /admin/projects/{project}/jobs/{job}` | `…@update` | — | auth + admin |
| `POST /jobs/{job}/start` | `JobController@start` | — | auth (assigned or admin) |
| `POST /jobs/{job}/finish` | `JobController@finish` | — | auth (assigned or admin) |
| `POST /jobs/{job}/complete` | `JobController@complete` | — | auth (assigned or admin) |
| `POST /jobs/{job}/skip` | `JobController@skip` | — | auth + admin |
| `POST /jobs/{job}/cancel` | `JobController@cancel` | — | auth + admin |
| `DELETE /admin/projects/{project}/jobs/{job}` | `Admin\JobController@destroy` | — | auth + admin |
| `GET /calendar` | `CalendarController@index` | `calendar/index` | auth |

State transitions: `scheduled → in_progress → completed`. Side states: `skipped`, `cancelled`. See [SYSTEM_TEMPLATE.md §4.4](SYSTEM_TEMPLATE.md).

### 2.9 Material Catalog

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /admin/materials` | `Admin\MaterialController@index` | `admin/materials/index` | auth + admin |
| `GET /admin/materials/create` | `…@create` | `admin/materials/create` | auth + admin |
| `POST /admin/materials` | `…@store` | — | auth + admin |
| `GET /admin/materials/{material}/edit` | `…@edit` | `admin/materials/edit` | auth + admin |
| `PUT /admin/materials/{material}` | `…@update` | — | auth + admin |
| `DELETE /admin/materials/{material}` | `…@destroy` | — | auth + admin (sets `is_active=false`) |
| `GET /admin/material-categories` (resource) | `Admin\MaterialCategoryController` | `admin/material-categories/*` | auth + admin |

Soft-archive only — `is_active=false`. Don't hard-delete because `project_material_orders` reference materials.

### 2.10 Decision Catalog

Same shape as Material Catalog. Routes under `/admin/decision-categories` and `/admin/decision-items`.

### 2.11 Rate Catalog

Same shape. Routes under `/admin/rate-categories` and `/admin/rate-items`. Sorted by `code`.

### 2.12 Budget Catalog

Same shape. Routes under `/admin/budget-sections` and `/admin/budget-line-definitions`.

### 2.13 Trade Partners

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /trade-partners` | `TradePartnerController@index` | `trade-partners/index` | auth (filter by trade + location) |
| `GET /trade-partners/{partner}` | `…@show` | `trade-partners/show` | auth |
| `GET /admin/trade-partners/create` | `Admin\TradePartnerController@create` | `admin/trade-partners/create` | auth + admin |
| `POST /admin/trade-partners` | `…@store` | — | auth + admin |
| `GET /admin/trade-partners/{partner}/edit` | `…@edit` | `admin/trade-partners/edit` | auth + admin |
| `PUT /admin/trade-partners/{partner}` | `…@update` | — | auth + admin |
| `DELETE /admin/trade-partners/{partner}` | `…@destroy` | — | auth + admin (archive) |

**Notes**
- Form posts trades as `string[]` — controller syncs `trade_partner_trades` pivot.
- Index page is a filterable table — search, filter by `location`, filter by `trade`.

### 2.14 Reports

| Route | Controller | Page | Middleware |
|---|---|---|---|
| `GET /admin/reports` | `ReportController@index` | `admin/reports/index` | auth + admin |
| `GET /admin/reports/budget-variance` | `…@budgetVariance` | `admin/reports/budget-variance` | auth + admin |
| `GET /admin/reports/budget-variance/export.csv` | `…@budgetVarianceExport` | — | auth + admin |
| `GET /admin/reports/time-summary` | `…@timeSummary` | `admin/reports/time-summary` | auth + admin |
| `GET /admin/reports/time-summary/export.csv` | `…@timeSummaryExport` | — | auth + admin |
| `GET /admin/reports/material-status` | `…@materialStatus` | `admin/reports/material-status` | auth + admin |

CSV exports stream via `StreamedResponse` (no full-result in memory).

---

## 3. UI / Page Map

Per [SYSTEM_TEMPLATE.md §6](SYSTEM_TEMPLATE.md), Inertia page folder mirrors `Inertia::render('folder/name')`. Folder layout under [resources/js/pages/](../resources/js/pages/):

```
pages/
├── auth/                       (shipped by starter)
├── settings/                   (shipped by starter)
├── dashboard.tsx               (shipped by starter — crew/admin landing)
├── welcome.tsx                 (shipped by starter)
├── calendar/
│   └── index.tsx
├── projects/
│   ├── index.tsx
│   ├── show.tsx                 (tabs: dimensions / decisions / orders / budget / jobs)
│   ├── create.tsx
│   ├── edit.tsx
│   ├── dimensions.tsx
│   ├── decisions/index.tsx
│   ├── orders/index.tsx
│   ├── budget/index.tsx
│   └── jobs/{index,show}.tsx
├── jobs/
│   ├── index.tsx                (crew's "my jobs")
│   └── show.tsx
├── trade-partners/
│   ├── index.tsx
│   └── show.tsx
├── time-card/
│   └── index.tsx
└── admin/
    ├── dashboard.tsx            (already exists)
    ├── employees/{index,create,show,edit}.tsx
    ├── materials/{index,create,edit}.tsx
    ├── material-categories/{index,create,edit}.tsx
    ├── decision-categories/{index,create,edit}.tsx
    ├── decision-items/{index,create,edit}.tsx
    ├── rate-categories/{index,create,edit}.tsx
    ├── rate-items/{index,create,edit}.tsx
    ├── budget-sections/{index,create,edit}.tsx
    ├── budget-line-definitions/{index,create,edit}.tsx
    ├── trade-partners/{create,edit}.tsx
    ├── time-card/index.tsx
    └── reports/{index,budget-variance,time-summary,material-status}.tsx
```

Domain components (cards, status pills, qty inputs) live in [resources/js/components/buffalobuilt/](../resources/js/components/buffalobuilt/).

---

## 4. Form Request Classes

One [FormRequest](https://laravel.com/docs/12.x/validation#form-request-validation) per write action — never validate inside controllers ([SYSTEM_TEMPLATE.md §9](SYSTEM_TEMPLATE.md)). Naming: `{Module}{Action}Request`.

Examples:
- `StoreProjectRequest`, `UpdateProjectRequest`
- `StoreProjectMaterialOrderRequest`, `UpdateProjectMaterialOrderRequest`, `MarkOrderedRequest`
- `StoreTradePartnerRequest` (with nested `trades[]` validation)
- `UpdateBudgetLineRequest`
