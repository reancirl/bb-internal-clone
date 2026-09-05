# Project Status — BuffaloBuilt Internal

**Last updated:** 2026-09-05 (Session 4, BUG-001) · **Baseline commit:** `f6dafa0`

This file is the single source of truth for project state. Read it in full at the start of every
session. Verify its claims against the code before acting on them.

---

## What this system is

An internal job-management platform for a residential general contractor in Sheridan, Wyoming. It
replaces a set of spreadsheets with one system covering the whole job lifecycle:

Website lead → sales pipeline → converted project → takeoff estimate (dimensions × formulas ×
price book) → customer proposal PDF → contract plus change orders → budget (bid vs actual) fed by
awarded sub bids and purchase orders → scheduled jobs with crew and dependencies → field records
(daily logs, photos, tasks and punch list, customer selections) → time cards and reports.

Two roles: **admin** (office: estimating, sales, purchasing, budgets, scheduling, reports) and
**crew** (field: clock in/out, own jobs, daily logs, tasks, photos). A companion Expo mobile app
uses a token API for the crew subset. The marketing website posts leads through one scoped endpoint.

Full description: `AUDIT-2026-09.md` §1.

---

## Current architecture

- **Backend.** Laravel 12.61 monolith. Layers: routes → controllers (inline validation, hand-built
  array serialization) → Eloquent models (relationships, casts, status constants, small domain
  helpers) → `app/Support` for domain logic (`FormulaEvaluator`, `TakeoffCosting`, `ScheduleShifter`,
  `Notify`, `TakeoffTemplate`, two photo storage classes). No service layer, no policies, two
  FormRequests.
- **Frontend.** Inertia 2 renders React 19 + TypeScript pages. `resources/js/pages/` mirrors routes;
  `components/ui/` is shadcn; `components/buffalobuilt/` holds five shared components
  (`Pagination`, `useConfirm`, `FlashToaster`, `ClockInOutButton`, `ScheduleTimeline`);
  `components/leads/` is the only feature currently split into components; `types/` covers about
  half the features; `lib/` has `money.ts`, `formula.ts`, `utils.ts`.
- **State.** Inertia props are the server state; local `useState`/`useForm` for UI. No store (D-001).
  Shared props: `auth.user`, `flash` with a per-response id, a lazy `openTimeCard`.
- **Auth.** Session auth for web, `EnsureUserIsAdmin` middleware for admin routes, ownership checks
  on `DailyLog`, `ProjectTask`, and job crew membership. Sanctum bearer tokens for the API with two
  abilities: `mobile` and `leads:create`.
- **Database.** PostgreSQL in production and local; SQLite in memory for tests. Money in integer
  cents except the price book, which stores decimal dollars (D-003). 30 tables.
- **Infrastructure.** Queue, cache, and session on the database driver. Notifications are queued.
  An hourly scheduled command sends clock-out reminders. Pushing to `main` auto-deploys.

Details: `AUDIT-2026-09.md` §2 and §8.

---

## Current status

**Both Critical security tasks are closed** (SEC-001, SEC-002), and **BUG-001 has removed the last
known data-corruption path**: change order numbering, decision guards, and the approval transaction.
The backlog is now High and below, with no task blocking more than one other.

## Progress

| Total | 🔴 Pending | 🟡 In Progress | 🟢 Fixed | 🔵 Verified | ⚪ Deferred | ❌ Won't Fix |
|---|---|---|---|---|---|---|
| 51 | 44 | 0 | 0 | 5 | 2 | 0 |

By priority (not yet 🔵 Verified): **Critical 0** · **High 8** · **Medium 18** · **Low 20**

## Current task

None.

## Next recommended tasks

1. **BUG-003** — a real bug in production only: search silently misses rows on PostgreSQL. Pairs
   naturally with TEST-002, which is what would have caught it.
2. **BUG-002 · BUG-004 · BUG-005** — independent, each roughly an hour.
3. **TEST-002** then **PERF-001** — put Postgres in CI before rewriting the report query in SQL.
4. **TEST-001** then **REF-001** — the API must be tested before it is refactored.
5. **ARCH-001 · ARCH-002** — now unblocked by BUG-001; fold the third copy of the numbering and
   status-machine patterns into traits while the shape is fresh.

---

## Task backlog

Status key: 🔴 Pending · 🟡 In Progress · 🟢 Fixed · 🔵 Verified · ⚪ Deferred · ❌ Won't Fix
`Ref` points at a section of `AUDIT-2026-09.md`. Priority is independent of ID number.

| ID | Priority | Area | Problem | Recommended fix | Depends on | Status | Ref |
|---|---|---|---|---|---|---|---|
| SEC-001 | Critical | Auth | `/register` is public; anyone can create a crew account with read access to pricing, projects, and partner data | Remove register routes, controller, and page (or gate behind an invite); update `RegistrationTest` | — | 🔵 Verified 2026-09-05 · `13c225a` · `./vendor/bin/phpunit` 261 pass | §11.1 |
| SEC-002 | Critical | API | `POST /api/login` and `POST /api/leads` unthrottled; Sanctum tokens never expire; tokens survive user deactivation | Add throttle middleware; set `sanctum.expiration`; delete tokens in `EmployeeController@destroy` | — | 🔵 Verified 2026-09-05 · `52a29bd` · `./vendor/bin/phpunit` 268 pass | §11.2–3 |
| SEC-003 | Medium | Settings | `ProfileController@destroy` force-deletes the account, even the last admin; time cards cascade away | Soft delete plus last-admin guard, or remove self-delete entirely | — | 🔴 Pending | §11.4 |
| SEC-004 | Low | Deploy | `SESSION_SECURE_COOKIE` unset in `.env.example`; `APP_DEBUG=true` in the example | Production env checklist; verify both on the server | — | 🔴 Pending | §11.5 |
| BUG-001 | High | Change orders | `max + 1` numbering races to a 500; `decide`/`revert` lack status guards so an approved CO can be re-decided; `decide` writes two rows with no transaction | `nextNumber` with `lockForUpdate` + `retry(3)`; guard on pending; wrap in `DB::transaction` | — | 🔵 Verified 2026-09-05 · `19100d2` · `./vendor/bin/phpunit` 275 pass | §3, §7 |
| BUG-002 | High | Projects | `destroy` blocks only on non-draft proposals; sent POs and awarded bid requests cascade away, freeing their document numbers for reuse | Extend the guard to non-draft POs and non-draft bid requests | — | 🔴 Pending | §3, §8 |
| BUG-003 | High | Search | `like` is case-sensitive on PostgreSQL, so directory and price-book search misses rows in production; SQLite tests hide it | `Builder::macro('whereLike')` using `LOWER()`; apply in all four controllers | — | 🔴 Pending | §8 |
| BUG-004 | High | Reports · Time · Calendar | Unvalidated `from`/`to`/`month` reach `Carbon::parse` and throw a 500 | Add `date` and regex validation in `ReportController`, `TimeCardController` (web + API), `JobController@calendar` | — | 🔴 Pending | §7 |
| BUG-005 | High | Errors | `withExceptions` is empty, so 403/404/419/500 render Laravel's HTML page inside Inertia's modal | Standard Inertia exception handler plus `pages/errors/error.tsx`; 419 redirects back with a flash | — | 🔴 Pending | §12 |
| BUG-006 | High | Inline saves | Budget money and notes, contract field, selection fields, quote rows, checklist and archive toggles call `router.put/patch` with no `onError`, so failures are silent | Shared `saveField` helper with an error toast; later absorbed by the `MoneyInput` in REF-004 | — | 🔴 Pending | §5 |
| BUG-007 | Medium | Tasks | `syncChecklist` deletes and recreates rows outside the transaction; item ids churn on every save so a stale page 404s | Upsert by id or position inside the task transaction | — | 🔴 Pending | §3 |
| BUG-008 | Low | UI | `lead-card` marks a follow-up due today as overdue; a literal `0` renders when `estimated_value_cents` is 0; keyless fragment in the PO table | One `isOverdueDate` helper; `!== null` check; `<Fragment key>` | — | 🔴 Pending | §3 |
| BUG-009 | Low | Bids | `storeResponse` checks then creates; a concurrent duplicate invite hits the unique index and 500s instead of flashing | Catch `UniqueConstraintViolationException` and flash the friendly message | — | 🔴 Pending | §3 |
| BUG-010 | Low | Purchase orders | `itemRows` uses `abort(422)`, which the form cannot render as a field error | `ValidationException::withMessages(['items' => ...])` | — | 🔴 Pending | §3 |
| PERF-001 | High | Reports | `timeSummaryRows` loads every card in range into PHP and aggregates in a Collection; `range=all` reads the whole table | SQL `GROUP BY user_id` with the duration computed in SQL | TEST-002 recommended first | 🔴 Pending | §7, §9 |
| PERF-002 | Medium | CRM | `pipeline` and `analytics` ship every lead with every column; analytics math runs in the browser | Bound by status and age with a "show archived" toggle; move aggregates server-side | — | 🔴 Pending | §6 |
| PERF-003 | Medium | Calendar | `jobOptions` returns every non-canceled job in the system for the predecessor picker | Scope options to the selected project via a partial reload or an endpoint | — | 🔴 Pending | §6 |
| PERF-004 | Medium | Directories | Search input fires a server request on every keystroke on three pages | Debounced `SearchInput` component (300 ms) | — | 🔴 Pending | §9 |
| PERF-005 | Low | Projects | `show` ships the entire price book as props on every project view | Load the option list lazily when the line dialog opens | — | 🔴 Pending | §6 |
| PERF-006 | Low | Operations | Queue and scheduler failures are invisible; emails depend on both. *2026-09-05: SEC-002 also needs `sanctum:prune-expired` scheduled, or expired token rows accumulate forever.* | Failed-job alerting, a scheduler health check, and `Schedule::command('sanctum:prune-expired --hours=24')->daily()` | — | 🔴 Pending | §12 |
| DB-001 | Medium | Schema | Roughly 22 foreign-key columns are unindexed; PostgreSQL does not auto-index them | One migration adding the indexes listed in the audit | — | 🔴 Pending | §8 |
| TEST-001 | High | API | Zero tests for `/api` routes except leads; the mobile app depends on hand-copied controllers | Feature tests for login, user, logout, time card, price book, directories, admin | — | 🔴 Pending | §15 |
| TEST-002 | High | CI | Tests run on SQLite while production is PostgreSQL, hiding locking, unique-with-null, and LIKE differences | Add a Postgres service to `tests.yml` and run the suite on both | — | 🔴 Pending | §15 |
| TEST-003 | Medium | Change orders | No test that a non-pending CO rejects a second decision, or that numbering survives concurrency | Add tests alongside BUG-001 | BUG-001 | 🔵 Verified 2026-09-05 · `19100d2` · 7 cases in `ChangeOrderTest` | §15 |
| TEST-004 | Medium | Frontend | No JS tests; `lib/formula.ts` must stay in lockstep with the PHP evaluator | Vitest with shared golden cases for the formula parser and money helpers | REF-003 | 🔴 Pending | §15 |
| TEST-005 | Low | E2E | No smoke test of the estimate → proposal → PDF path | Playwright smoke suite | BUG-005 | 🔴 Pending | §15 |
| REF-001 | Medium | Web + API | Trade partner, vendor, price book, time card, and rate controllers are duplicated between web and API | Shared FormRequests, transformers, and a `TimeCardService` used by both | TEST-001 | 🔴 Pending | §13 |
| REF-002 | Medium | Photos | `DailyLogPhotoStorage` and `TaskPhotoStorage` are near-identical; two copies of `stream()` | One `PhotoProcessor` and a shared streamer; consider queuing the web rendition | — | 🔴 Pending | §13 |
| REF-003 | Medium | Frontend lib | `formatMoney`, `centsToInput`, `inputToCents`, `formatDate`, `FieldError`, and `Paginated` are re-declared per page; two `formatMoney` functions take different units | Consolidate into `lib/money.ts` and a new `lib/dates.ts`; use `InputError`; delete the local copies | — | 🔴 Pending | §13 |
| REF-004 | Medium | Shared UI | `PageHeader`, `DataTable`, `StatCard`, `StatusBadge`, `MoneyInput`, `FilterPills`, `PhotoLightbox`, `PhotoUploadButton`, and a create/edit dialog hook are re-implemented 3 to 25 times | Build them in `components/buffalobuilt/`; adopt page by page | REF-003 | 🔴 Pending | §4 |
| REF-005 | Medium | `projects/show.tsx` | 670 lines holding the project form, dimensions, takeoff table, line dialog with formula builder, and estimate card | Split into `components/projects/*` | REF-004 | 🔴 Pending | §3 |
| REF-006 | Medium | `projects/budget.tsx` | 728 lines with five inner components and four local helpers | Split into `components/budget/*`; adopt shared helpers | REF-004 | 🔴 Pending | §3 |
| REF-007 | Medium | `projects/selections.tsx` | 731 lines with seven inner components; overdue computed client-side | Split into `components/selections/*`; send `overdue` from the server | REF-004 | 🔴 Pending | §3 |
| REF-008 | Medium | `daily-logs/index.tsx` | 647 lines; bespoke pagination using `dangerouslySetInnerHTML`; redeclares `Paginated` | Use the shared `Pagination` (add `per_page` support server-side); extract the form modal and lightbox | REF-004 | 🔴 Pending | §3 |
| REF-009 | Low | `projects/tasks.tsx` | 623 lines, page plus dialogs plus rows | Split into `components/tasks/*` | REF-004 | 🔴 Pending | §3 |
| REF-010 | Low | `calendar/index.tsx` | 565 lines with two dialogs and the month grid | Split into `components/calendar/*` | REF-004 | 🔴 Pending | §3 |
| REF-011 | Low | Four admin pages | `admin/bids/show.tsx` (534), `purchase-orders.tsx` (519), `admin/leads/index.tsx` (502), `decision-catalog.tsx` (478) | Split as each is next touched | REF-004 | 🔴 Pending | §3 |
| REF-012 | Low | Conventions | Five `window.confirm` calls; 42 hard-coded URLs against 81 `route()` calls | Replace with `useConfirm` and `route()` | — | 🔴 Pending | §10 |
| REF-013 | Medium | Projects | `store` creates the project and 13 takeoff lines with no transaction; template seeding is duplicated in three places | `Project::seedStandardTakeoff()` inside `DB::transaction`, shared by store, convert, and the seeder | — | 🔴 Pending | §3 |
| ARCH-001 | Low | Models | Status machines and yearly numbering are copy-pasted across `ProjectProposal`, `PurchaseOrder`, and `BidRequest` | `HasStatusTransitions` and `HasYearlySequence` traits; PHP backed enums for statuses | BUG-001 | 🔴 Pending | §13 |
| ARCH-002 | Low | Budget | The "write a committed cost line" flow is duplicated in CO approval and bid award | A `BudgetLineWriter` service used by both | BUG-001 | 🔴 Pending | §13 |
| ARCH-003 | Low | Projects | Deleting a project hard-cascades POs, bids, COs, logs, photos, tasks, selections, budget, and jobs | Soft delete or an archived status for projects | BUG-002 | 🔴 Pending | §8 |
| ARCH-004 | Low | Time cards | No project link, so labor actuals cannot flow into the budget the way sub bids and POs do | Optional `project_id` on time cards — needs a product decision first | — | ⚪ Deferred | §8 |
| ARCH-005 | Low | API | No `/api/v1` prefix; payload changes would break shipped mobile clients | Version the API before wide mobile rollout | REF-001 | ⚪ Deferred | §18 |
| DX-001 | Low | Hygiene | Dead starter files (`app-header`, `nav-main`, `nav-footer`, `coming-soon`, `auth-card-layout`, `appearance-dropdown`, four unused `ui/*`, `tests/Pest.php`, `ExampleTest`); three unused Radix packages; build tooling under `dependencies`; a BOM in `admin/proposals/show.tsx`; the unused `quote` shared prop. *2026-09-05: `welcome.tsx` already removed by SEC-001, which deleted the last `route('register')` caller.* | Delete, prune, move to `devDependencies`, strip the BOM | — | 🔴 Pending | §10, §14 |
| DX-002 | Low | CI | `lint.yml` runs `prettier --write` and `pint` (both rewrite) and never fails, so drift is never caught; 13 files use 2-space indentation against a 4-space config | Switch to `prettier --check` and `pint --test`; one-time format commit | — | 🔴 Pending | §3 |
| UX-001 | Medium | Dashboard | The post-login landing page is still the starter placeholder pattern | A real dashboard (today's jobs, open time card, overdue follow-ups, budget alerts) or a role-based redirect | — | 🔴 Pending | §3 |
| SEC-005 | Low | Deploy | `SANCTUM_EXPIRATION_MINUTES` is unset in production, so tokens use the 30-day default from SEC-002. Existing tokens issued before that change are older than the window and stop working on deploy — the mobile app must log in again. | Confirm the default suits the business, set the env var if not, and warn mobile users before the deploy | SEC-002 | 🔴 Pending | — |
| UX-002 | Low | Auth | Login page and the shared auth layout were the starter pattern; `logo.png` is a 17716×9644 / 0.96 MB master rendered directly, so the page stalls on a 171 MP decode; Tailwind 4 leaves every `button` at `cursor: default` | Redesign login and the auth layout; generate sized logo derivatives; restore pointer cursors app-wide; `display=swap` on the webfont | — | 🔵 Verified 2026-09-05 · `397fbd0` · `./vendor/bin/phpunit` 261 pass · `npm run build` · manual visual pass on `/login` by the maintainer | — |
| PERF-007 | Medium | Assets | `public/img/logo.png` is still the 0.96 MB / 171 MP master in the repo; only the auth pages use the new derivatives | Point the remaining `AppLogoIcon` callers at the derivatives and drop or archive the master | UX-002 | 🔴 Pending | — |
| BUG-011 | Low | Leads | `convert.tsx:228` reads `errors.contract_price_cents`, which is not a key of `ConvertForm`; the field's validation error never renders | Use `contract_price`, the actual form key | — | 🔴 Pending | — |

---

## Task details

Only tasks needing more than their table row get a block here. Add one when you start a task.
Blocks for tasks that have been 🔵 Verified for more than two sessions collapse to a single line
pointing at the commit, so this section stays readable.

### SEC-001 — Remove public registration · 🔵 Verified

Done in `13c225a`. Removed the two register routes, `RegisteredUserController`, and
`pages/auth/register.tsx`; also deleted the unrouted `pages/welcome.tsx`, the only other caller of
`route('register')`. `RegistrationTest` now asserts both routes 404, no user is created, and the
admin creation path still works. The login page already said "Contact your administrator", so no UI
change was needed. Suite: 261 pass, 1452 assertions.

### UX-002 — Login page redesign · 🟢 Fixed

**Files.** `resources/js/pages/auth/login.tsx`, `resources/js/layouts/auth/auth-split-layout.tsx`,
`resources/js/components/app-logo-icon.tsx`, `resources/css/app.css`, `resources/views/app.blade.php`,
`resources/js/pages/auth/reset-password.tsx`, and five new files under `public/img/`.

**Perceived load time.** `public/img/logo.png` is 17716×9644 (171 MP, 0.96 MB) and was rendered
directly into a slot a few dozen pixels tall, twice per page — about 680 MB of decode before first
paint. Pillow refuses to open it by default as a decompression bomb. Added derivatives at 360/720/1080
plus a navy recolor for light backgrounds (`logo-dark-*`); `AppLogoIcon` now serves them through
`srcSet`/`sizes` with explicit `width`/`height` so the layout does not shift. 1,004,603 → 26,041 bytes
at the size the header actually uses. The webfont also had no `display=swap`, so text stayed invisible
until a third-party round trip finished.

**Cursors.** Tailwind 4 resets `button` to `cursor: default`, and no `ui/` primitive set
`cursor-pointer`, so nothing in the app showed a pointer. One rule in the base layer now covers
buttons, `[role=button|checkbox|radio|switch|tab]`, `label[for]`, `summary` and `select`, excluding
disabled controls. This is app-wide, not login-only.

**Types.** `useForm` requires `Record<string, FormDataConvertible>`; a TS `interface` has no index
signature and fails that constraint. `LoginForm` and `ResetPasswordForm` were the only two form types
declared as `interface` — every other page already uses `type` — so both were converted. That clears
two of the three long-standing `tsc` errors; the third is BUG-006.

**Design.** Desktop keeps the split: the mark centred and large (322→480 px by breakpoint) on the navy
panel over a masked grid and a radial key light. Below `lg` the panel is dropped entirely and the navy
mark sits on the page background — no band, no card, no texture. Form fields are 44 px with a 3 px
focus ring, `aria-invalid` drives the error border, and inputs are 16 px on mobile so iOS Safari does
not zoom the page on focus.

**Verification.** `./vendor/bin/phpunit` — 261 pass, 1452 assertions. `npm run build` succeeds and the
bundle contains the new copy and the cursor rule. `eslint` and `prettier --check` clean on the changed
files. `tsc` reports only BUG-006. `vendor/bin/pint --test` fails on 9 files, all of them pre-existing
on `main` and unrelated to this branch (DX-002).

**Still open.** No browser automation exists in this repo, so the visual result was verified from the
built bundle and computed layout values, not by eye. A manual pass in light and dark at phone, tablet
and desktop widths is needed before this moves to 🔵 Verified.

### SEC-002 — Throttle and expire API credentials · 🔵 Verified

Done in `52a29bd`. Limiters are defined in `AppServiceProvider::configureRateLimiters` and applied in
`routes/api.php`: `api-login` is 5/minute keyed on email + IP (matching the web budget, and keyed so
one attacker cannot lock an employee out from another address); `api-leads` is 20/minute keyed on the
calling token, because that token ships with the public marketing site and each submission emails
every opted-in admin. `sanctum.expiration` is 30 days, overridable with `SANCTUM_EXPIRATION_MINUTES`.
`EmployeeController@destroy` now deletes the employee's tokens before soft-deleting them.

**Two things worth knowing.**

1. *A test caught a real hole in itself.* The first version of the expiry test read the token back
   from the database, where `plainTextToken` is null, so it sent an empty bearer and passed for the
   wrong reason. Rewritten to use the token from the login response.
2. *Sanctum's guard is resolved once per test process and cached in the container*, so an
   authenticated request made before `travel()` keeps the token alive afterwards. The expiry test
   therefore makes exactly one authenticated request. Each real HTTP request boots a fresh container,
   so this does not apply in production. A separate test covers the inside-the-window happy path.

**Follow-ups created.** SEC-005 (production env decision, and existing tokens invalidate on deploy)
and a note on PERF-006 to schedule `sanctum:prune-expired`.

### BUG-001 — Change order integrity · 🔵 Verified

Done in `19100d2`. `ChangeOrder::nextNumber()` locks the project's rows and the caller retries three
times; `decide()` and `revert()` both refuse unless the order is in the state they expect; approval
and revert each run in one transaction. `isPending()` replaced the inline status comparison in
`update()`.

**Deliberate non-fix.** Numbering is `max + 1` over surviving rows, so deleting the highest change
order hands its number to the next one. That is fine here and is now covered by a test that says so:
change order numbers are internal, and only pending orders can be deleted. Proposal and PO numbers
are customer- and supplier-facing, which is why those are guarded against reissue instead.

**Testing note.** The rollback test drops `project_budget_lines` mid-approval to force the second
write to throw. Two earlier attempts were discarded: a string-length overflow (SQLite ignores column
lengths) and a cascade delete of the parent project (which takes the change order with it).


---

## Known issues and risks

Open conditions that are not tasks, or are deliberate. Do not "fix" these without a decision entry.

- Any authenticated user can toggle any takeoff line's ordered/on-site flags, view any project's
  photos and cost estimates, and create tasks on any project. This is by design for a small crew.
- The price book stores decimal dollars while everything downstream stores integer cents.
  Conversion is centralized in `TakeoffCosting::toCents` (D-003).
- Three pairs of migrations share a timestamp prefix; ordering currently relies on filename sort.
- Photo processing is synchronous, decoding up to 10 files of 15 MB per request through GD. Large
  batches may exceed PHP memory limits. REF-002 may move this to a queue.
- `docs/` is gitignored and exists on one machine only. It contains the deployment runbook with
  production SSH details. Do not move it into a tracked path while the repository is public (D-005).
- SSR is configured in `vite.config.js` and `ssr.jsx` but never deployed; two files access `window`
  at module scope and would crash under it.

---

## Session protocol

**Starting.** Read this file. Verify the 🟡 In Progress task against the actual code. If nothing is in
progress, take the first task in "Next recommended tasks" whose dependencies are all 🟢 or 🔵.
Announce the task ID and its acceptance criteria before writing code.

**During.** Set the task row to 🟡 with a dated note when you start. Work on a branch named
`type/ID-slug`. If you discover a new problem, create a new task ID rather than widening the current
one. If the code contradicts this document, fix the document and note it in `SESSIONS.md`.

**Ending.** Run the test suite and lint. Update the task row (status, dated note, commit hash, the
test command you ran), "Current task", "Next recommended tasks", and recount the Progress table.
Append a session entry to `SESSIONS.md`. Append any architectural decision to `DECISIONS.md`. Commit
this file together with the code, or as `chore(status): session <date> [<IDs>]` if no code changed.
