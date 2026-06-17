# BuffaloBuilt Internal — Implementation Plan

Phased rollout from the stack we have today (M0) to a feature-complete v1 (M8). Each milestone ships a working app to staging — no half-finished modules.

> **Note (2026-06-17):** Delivery diverged from the original milestone order below. The roadmap (M0–M8) is preserved as the *intended* plan; the **As-built status** table is the source of truth for what actually exists. See the [As-built reconciliation](#as-built-reconciliation-2026-06-17) and updated [Decision log](#decision-log-open-for-future-updates).

## Roadmap (original intent)

| M | Name | Goal |
|---|---|---|
| M0 | Foundation | Stack, auth, admin gate, role column |
| M1 | Projects & Trade Partners | Office can create projects and look up subs |
| M2 | Catalog Import | Master data lives in the DB |
| M3 | Material Catalog UI | Office can edit the material/decision/rate catalogs |
| M4 | Customer Decisions | Walk the customer through the decision list inside the app |
| M5 | Material Orders | Per-project line items, ordered/on-site tracking |
| M6 | Budget Tracking | Bid vs Actual variance grid; first CSV export |
| M7 | Time + Jobs | Crew clock in/out, dated job assignments, calendar |
| M8 | Reports & Polish | Variance / time / material-status reports; UI polish; deploy guide |

## As-built status (source of truth)

| # | Delivered | Status | Maps to roadmap |
|---|---|---|---|
| B0 | Foundation — stack, hand-rolled auth, admin gate, role column, time cards | ✅ Done | M0 |
| B1 | Trade Partners + Vendors directory (filter, pagination) | ✅ Done | M1 (partial) |
| B2 | Price Book + Labor/Equipment rate tables + admin CRUD | ✅ Done | overlaps M2/M3 |
| B3 | Projects + Takeoff Calculator (dimensions → formula-computed quantities, ordered/on-site, supplier links) | ✅ Merged | M1 (projects) + M5 (order tracking) + descoped formula engine |
| B4 | Mobile JSON API (Sanctum token auth) for the Expo/React Native app | 🚧 In progress | new — not in original roadmap |

Each milestone should ship to staging behind admin's review before the next starts.

---

## As-built reconciliation (2026-06-17)

Actual delivery pivoted away from *Catalog Import (M2)* and *Customer Decisions (M4)* toward directories, a price book, a takeoff calculator, and a mobile API. Key divergences from the original roadmap:

| Roadmap assumption | As-built reality | Impact |
|---|---|---|
| M1 ships `projects` + separate `project_dimensions` table | Dimensions are 15 fixed columns on `projects` (no child table) | Simpler for a fixed dimension set; revisit if dimensions become user-defined |
| Material formulas stored as **text, not auto-compute** (descoped) | `FormulaEvaluator` (PHP) + `formula.ts` (TS) auto-compute quantities live | Scope increase — reverses a logged decision; see Decision log 2026-06-17 |
| Soft-delete on `projects` | `projects` hard-deletes (no `softDeletes`) | Deviation from cross-cutting commitment — accepted as debt |
| FormRequest class per write action | `ProjectController` / `TakeoffLineController` use inline `$request->validate()` | Deviation from cross-cutting commitment — accepted as debt |
| Catalog Import (M2) seeds master data from xlsx | Not built; price book/rates entered via CRUD instead | M2 still open if xlsx bulk import is needed |

**Outstanding debt to schedule:** add `softDeletes` to `projects`; extract FormRequest classes; add a shared fixture so `FormulaEvaluator.php` and `formula.ts` cannot drift.

---

## M0 — Foundation (✅ done)

**Shipped:** Laravel 12 + Inertia v2 + React 19 + TS + Tailwind 4 + Vite 6, SQLite, hand-rolled auth, role column on users, `EnsureUserIsAdmin` middleware, `/admin/dashboard` shell, sidebar role-aware nav, seed users, AdminAccessTest passing.

**Key files**
- [app/Models/User.php](../app/Models/User.php) — role constants + `isAdmin()`
- [app/Http/Middleware/EnsureUserIsAdmin.php](../app/Http/Middleware/EnsureUserIsAdmin.php)
- [bootstrap/app.php](../bootstrap/app.php) — `admin` alias
- [routes/web.php](../routes/web.php) — `/admin/dashboard`
- [resources/js/pages/admin/dashboard.tsx](../resources/js/pages/admin/dashboard.tsx)
- [tests/Feature/Admin/AdminAccessTest.php](../tests/Feature/Admin/AdminAccessTest.php)

---

## M1 — Projects & Trade Partners

**Goal:** office can create a project (with dimensions) and look up a subcontractor.

### Deliverables
1. `projects` + `project_dimensions` tables ([02-data-model.md §3.3–3.4](02-data-model.md))
2. `trade_partners` + `trade_partner_trades` tables ([02-data-model.md §3.16–3.17](02-data-model.md))
3. Project CRUD + dimensions form ([03-modules.md §2.3–2.4](03-modules.md))
4. Trade Partner CRUD + index with filter by trade + location ([03-modules.md §2.13](03-modules.md))
5. Sidebar: add "Projects" and "Trade Partners" links
6. Dashboard widgets: count of projects by status; recent activity

### Tests required
- `ProjectControllerTest` — index filtering, store/update/destroy, soft-delete + restore
- `TradePartnerControllerTest` — store with `trades[]`, sync pivot, filter index
- `AdminAccessTest` — confirm crew can list but not create/edit projects

### Done criteria
- An admin can create a project, fill in dimensions, save, edit, and soft-delete.
- An admin can add a trade partner with multiple trades; the index filters work.
- All M0 tests still pass.

---

## B3 — Projects + Takeoff Calculator (✅ merged)

**Goal:** office creates a project, enters building dimensions, and the app computes material quantities per line from formulas.

**Shipped (merged from `dev-grant`, 2026-06-17):**
- `projects` table — identity + 15 dimension columns; `takeoff_lines` table — per-project material lines (`formula`, `waste_pct`, `ordered`, `on_site`, `price_item_id`, `supplier_id`).
- [`FormulaEvaluator.php`](../app/Support/FormulaEvaluator.php) — safe recursive-descent parser (no `eval`); supports `+ - * /`, parentheses, whitelisted dimension variables; throws on bad input.
- [`formula.ts`](../resources/js/lib/formula.ts) — JS mirror of the grammar for live client-side quantity preview.
- [`TakeoffTemplate.php`](../app/Support/TakeoffTemplate.php) — new projects auto-seed ~13 standard takeoff lines.
- Role model: everyone views; **crew can toggle `ordered`/`on_site`**; admins manage projects and lines.
- Tests: [`ProjectTest.php`](../tests/Feature/ProjectTest.php) (10) + [`FormulaEvaluatorTest.php`](../tests/Unit/FormulaEvaluatorTest.php).

**Done criteria — met:**
- Admin creates a project; it starts with a standard takeoff; entering dimensions computes quantities with waste.
- Bad formulas surface a per-line error without breaking the page.
- Crew can mark items ordered/on-site but cannot add lines.

**Known deviations:** no soft-delete on `projects`; inline validation instead of FormRequests; dual PHP/TS formula implementations (see [As-built reconciliation](#as-built-reconciliation-2026-06-17)).

---

## B4 — Mobile JSON API (🚧 in progress)

**Goal:** expose the app's data to an Expo / React Native client via token auth.

**In progress:**
- Laravel Sanctum personal-access-token auth (`config/sanctum.php`, `personal_access_tokens` migration).
- [`routes/api.php`](../routes/api.php): `POST /api/login`, `/api/logout`, `/api/user`; self-service time-card clock in/out; read-everyone trade-partners / vendors / price-book; admin-gated writes + rates + dashboard.
- [`app/Http/Controllers/Api/`](../app/Http/Controllers/Api/) — JSON controllers mirroring the web controllers.

**Done criteria:**
- A client logs in once, stores the bearer token, and authenticates every request with it.
- Role gates (`auth:sanctum` + `admin`) match the web side.
- API controllers have feature tests (TODO).

---

## M2 — Catalog Import

**Goal:** master data from the xlsx lives in the DB.

### Deliverables
1. Migrations for all catalog tables: `rate_categories`, `rate_items`, `material_categories`, `materials`, `decision_categories`, `decision_items`, `budget_sections`, `budget_line_definitions`.
2. Eloquent models with `is_active` scope.
3. Composer dep: `composer require phpoffice/phpspreadsheet`.
4. Artisan command [`bb:import-master-list`](../app/Console/Commands/ImportMasterListCommand.php) per [04-xlsx-mapping.md §7](04-xlsx-mapping.md).
5. Idempotent + reversible (`--rollback`, `--force` flags).
6. Test against the live xlsx fixture in `tests/Feature/ImportMasterListTest.php`.

### Done criteria
- `php artisan bb:import-master-list` populates ~6 catalog tables, hundreds of rows.
- Re-running does not create duplicates.
- One project (James Staebler) and its budget lines exist after import.
- Trade partner suppliers are linked to materials where names match.

---

## M3 — Catalog UI

**Goal:** the office never touches the xlsx after M2 — they edit catalogs in the app.

### Deliverables
- Admin CRUD pages for every catalog ([03-modules.md §2.9–2.12](03-modules.md)):
  - Materials + Material Categories
  - Decision Items + Decision Categories
  - Rate Items + Rate Categories
  - Budget Line Definitions + Budget Sections
- Sort, search, archive (toggle `is_active`).
- Consistent table component in [resources/js/components/buffalobuilt/](../resources/js/components/buffalobuilt/).

### Done criteria
- An admin can add a new material category, then add materials under it.
- Archiving a material hides it from order-creation flow without breaking historical orders.
- All catalog pages have tests.

---

## M4 — Customer Decisions

**Goal:** Walk the customer through the Decision List inside the app.

### Deliverables
1. `project_material_decisions` table ([02-data-model.md §3.10](02-data-model.md)).
2. `Project\DecisionController` ([03-modules.md §2.5](03-modules.md)).
3. UI: a tab on the project show page, decisions grouped by category, inline edit, "Confirm" stamps `confirmed_at`.
4. "Generate from catalog" button: creates a row per `decision_item` so the office can fill them in linearly.
5. PDF / print stylesheet for the decision list (handed to the customer).

### Done criteria
- A new project starts empty; clicking Generate creates ~80 rows.
- Office can fill in decisions, mark confirmed, see total of `price_cents`.
- Print view fits on letter paper.

---

## M5 — Material Orders

**Goal:** per-project orders with supplier tracking.

### Deliverables
1. `project_material_orders` table ([02-data-model.md §3.7](02-data-model.md)).
2. `Project\OrderController` with `generate` / `markOrdered` / `markOnSite` actions ([03-modules.md §2.6](03-modules.md)).
3. UI grouped by material category, with phone-friendly mark-ordered / mark-on-site swipes.
4. Supplier picker: dropdown of active `trade_partners` filtered by trade, with free-text fallback.
5. Bulk-edit: assign supplier or mark-ordered for a category at once.

### Done criteria
- Office hits Generate on a project; the order list appears grouped by category.
- Field crew on a phone can mark items ordered / on-site.
- Allowance price + actual price are captured for variance reporting.

---

## M6 — Budget Tracking

**Goal:** bid vs actual variance per budget line per project.

### Deliverables
1. `project_budget_lines` table ([02-data-model.md §3.15](02-data-model.md)).
2. `Project\BudgetController` ([03-modules.md §2.7](03-modules.md)).
3. UI: grid grouped by section, with bid/actual columns for Sub/Material/Labor, derived Difference column.
4. CSV export streamed via `Symfony\Component\HttpFoundation\StreamedResponse`.
5. Auto-feed: where `default_rate_item_id` is set, pre-fill the bid columns from rate catalog (override-able).

### Done criteria
- Office can fill in the grid for James Staebler's project; numbers match what's in the xlsx today.
- CSV export downloads and opens correctly in Excel.
- Variance summary shows totals at the bottom.

---

## M7 — Time + Jobs

**Goal:** crew can clock in/out and see today's assigned jobs.

### Deliverables
1. `time_cards` + `project_jobs` + `project_job_user` tables ([02-data-model.md §3.2, 3.18, 3.19](02-data-model.md)).
2. Clock-in/out header widget (port from lawnops [clock-in-out-button.tsx](../resources/js/components/lawnops/clock-in-out-button.tsx) to `buffalobuilt/`).
3. `JobController` for crew, `Admin\JobController` for admin ([03-modules.md §2.8](03-modules.md)).
4. `/calendar` page (month/week, color-coded by status).
5. `/jobs` page — "My jobs today" for crew.
6. Optional: link a time card to a `project_job_id` on clock-in to time-track by job.

### Done criteria
- Crew user logs in, sees "Clock In" button in header, taps it; timer ticks.
- Crew can see their own assigned jobs for today and update status.
- Admin can assign multiple crew to one job.
- Calendar renders all upcoming scheduled jobs.

---

## M8 — Reports & Polish

**Goal:** v1 sign-off — reports, polish, deploy doc.

### Deliverables
- Reports module ([03-modules.md §2.14](03-modules.md)):
  - **Budget variance** report (across projects) — total bid vs actual, % over
  - **Time summary** report — hours per crew per project per date range
  - **Material status** report — what's ordered / on-site / outstanding by project and category
  - CSV exports for each
- Dashboard polish — KPI tiles for: active projects, jobs today, jobs this week, outstanding material orders
- 2FA — opt-in Fortify install if the office decides they want it (decision point — currently descoped to keep the starter's hand-rolled auth)
- Deploy guide: `docs/06-deploy.md` (Postgres setup, queue worker, scheduled `php artisan schedule:run`, file storage, backup policy)

### Done criteria
- All 3 reports view + CSV export pass tests.
- Lighthouse mobile score > 90 on the crew-facing pages.
- Deploy guide can be followed end-to-end without office help.

---

## Cross-cutting commitments

These apply to every milestone:

| Commitment | Mechanism |
|---|---|
| Money in cents | Migrations use `bigInteger`, ending in `_cents`. Format on render with a `cents-to-dollars` helper. |
| FormRequests for every write | One class per controller-action. Validates + authorizes. |
| Tests must pass before merge | `php artisan test --compact` + `npm run build` + `npx tsc --noEmit` (Inertia-related TS warnings from the starter are acceptable, see [M8 polish notes](#m8-reports--polish)). |
| `pint --dirty` before commit | PHP formatter (`vendor/bin/pint --dirty --format agent`) |
| `npm run format` before commit | Prettier for JS/TS |
| Wayfinder-style typed routes | Out of scope — starter kit uses Ziggy. If route-typing becomes painful at M5+, evaluate adding Wayfinder. |
| Soft-delete on `projects` and `users` only | Hard-delete everywhere else; archive via `is_active` for catalogs. |

---

## Risks & mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| xlsx import miss-classifies items (a Rate Item gets seeded as a Material, etc.) | Medium | Medium | Dry-run mode: `bb:import-master-list --dry-run` prints what it would create. Office reviews before commit. |
| Office continues editing the xlsx after go-live ("just to be safe") | Medium | High | Lock the xlsx after M3 ships. Move it to `docs/archive/`. Communication, not technical. |
| Auto-compute formulas (descoped from v1) becomes a v1 must-have late | Low | Medium | Keep `takeoff_formula` as a string field; the parser can be added in v1.x without schema changes. |
| Crew adoption — clock-in friction on phones | Medium | Medium | Big-button clock-in widget; "tap once" UX; remind via SMS daily for first 2 weeks (out of scope but worth flagging). |
| 2FA needed by auditor / insurance | Low | Medium | Hand-rolled auth can swap to Fortify in M8 with controllers replaced. ~1–2 days. |

---

## Decision log (open for future updates)

| Date | Decision | Why |
|---|---|---|
| 2026-05-27 | Use react-starter-kit (Laravel 12 + hand-rolled auth) over Laravel 13 + Fortify | Faster path; 2FA can be added later if needed |
| 2026-05-27 | Admin portal at `/admin/*` prefix | Clean separation from crew routes |
| 2026-05-27 | Admin + Crew only for v1 (no PM, no customer portal) | Match lawnops template scope |
| 2026-05-27 | Material formulas stored as text (not auto-compute) for v1 | Spreadsheet works this way today; no functionality lost |
| 2026-06-17 | **Reversed:** takeoff formulas auto-compute via `FormulaEvaluator` (PHP) + `formula.ts` (TS) | The estimating value is in computing quantities from dimensions; safe parser (no `eval`) makes it low-risk |
| 2026-06-17 | `projects` uses fixed dimension columns, not a `project_dimensions` child table | Dimension set is fixed (15 fields); a child table adds joins with no flexibility gain today |
| 2026-06-17 | Accept as debt: no soft-delete on `projects`, inline validation instead of FormRequests | Merged from `dev-grant` as-is to unblock; scheduled in As-built reconciliation outstanding-debt list |
| 2026-06-17 | Add a mobile JSON API (Sanctum tokens) for an Expo/React Native client | Field crew need a native app; token auth is the standard Laravel path and reuses existing role gates |
