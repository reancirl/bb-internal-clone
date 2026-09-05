# Session Log

Append-only. One entry per AI coding session, newest at the bottom. `STATUS.md` holds the current
state; this file holds the history of how it got there.

Each entry records: investigated, changed, tested, unfinished, problems, decisions, new task IDs,
and the recommended next task.

---

## 2026-09-05 · Session 0 · Full technical audit

**Investigated.** Complete read of the codebase at commit `f6dafa0`: all routes, 35 controllers, 30
models, 28 migrations, the `app/Support` classes, notifications, the console command, every page and
component under `resources/js`, the two PDF templates, seeders, factories, the test suite, and CI
configuration.

**Changed.** No project files. The audit is recorded in `AUDIT-2026-09.md` and published as an
artifact at https://claude.ai/code/artifact/dc7e72b3-9665-4d3f-a6ad-3e9ab356f5ab

**Tested.** Nothing run; this was a read-only audit.

**Unfinished.** All 44 tasks are 🔴 Pending. No code work started.

**Problems encountered.** The planned `.gitignore` change (un-ignoring `docs/`) was abandoned after
finding production SSH details in `docs/11-deployment.md` on a public repository. Tracking files were
placed at the repository root instead. See D-005.

**Decisions.** D-001 through D-005.

**New task IDs.** SEC-001..004, BUG-001..010, PERF-001..006, DB-001, TEST-001..005, REF-001..013,
ARCH-001..005, DX-001..002, UX-001. Forty-four tasks seeded into `STATUS.md`.

**Recommended next task.** SEC-001 — remove public registration. Independent, small, closes the
largest exposure.

---

## 2026-09-05 · Session 1 · Tracking system setup

**Investigated.** Existing repository conventions: commit style (`type: summary (roadmap #n)`),
branch naming, the GitHub remote (public), `ai-core/` prompt files, and the contents of `docs/`.

**Changed.** Added `CLAUDE.md`, `STATUS.md`, `DECISIONS.md`, `SESSIONS.md`, and `AUDIT-2026-09.md` at
the repository root on branch `chore/tracking-system`. `.gitignore` deliberately left unchanged.

**Tested.** No code changed, so no suite run. Files are documentation only and are not read by the
application, the build, or the deploy script.

**Unfinished.** The backlog itself: 44 tasks, all 🔴 Pending.

**Problems encountered.** `docs/11-deployment.md` appeared between the audit and this session and
contains the production server IP, SSH user, and app path. Since the repository is public, `docs/`
stays ignored and the tracking files moved to the root. Recorded as D-005 and reflected in SEC-004.

**Decisions.** D-005.

**New task IDs.** None.

**Recommended next task.** SEC-001.

---

## 2026-09-05 · Session 2 · SEC-001 remove public registration

**Investigated.** Every reference to registration across routes, controllers, pages, and tests. Two
assumptions in the task row turned out to be wrong: the login page already directs users to contact
an administrator (no signup link to remove), and the only other caller of `route('register')` was
the unrouted `pages/welcome.tsx`.

**Changed.** Branch `fix/SEC-001-remove-public-registration`, commit `13c225a`. Dropped the GET and
POST register routes with a comment explaining why; deleted `RegisteredUserController`,
`pages/auth/register.tsx`, and `pages/welcome.tsx`; rewrote `RegistrationTest`.

**Tested.** `./vendor/bin/phpunit` — 261 pass, 1452 assertions (was 260; removed 2 registration
tests, added 3). `vendor/bin/pint --test` fails and `npx tsc --noEmit` reports 3 errors, both
verified as pre-existing at baseline `f6dafa0` (which fails Pint and had 5 TS errors); deleting the
two pages removed 2 of them. Tracked by DX-002.

**Unfinished.** Nothing on this task.

**Problems encountered.** None.

**Decisions.** None new; the change follows the existing admin-creates-accounts model.

**New task IDs.** None. DX-001 was amended to note `welcome.tsx` is already gone.

**Recommended next task.** SEC-002 — throttle `POST /api/login` and `/api/leads`, set
`sanctum.expiration`, revoke tokens on employee deactivation.

---

## 2026-09-05 · Session 3 · SEC-002 API throttling and token expiry

**Investigated.** Confirmed all three gaps in code before starting: neither public API route had a
throttle, `sanctum.expiration` was `null`, and `EmployeeController@destroy` soft-deleted the user
without touching their tokens. Also noted that another session had merged UX-002 (login redesign)
into `main`; unrelated to this work, no conflict.

**Changed.** Branch `fix/SEC-002-api-throttle-token-expiry`, commit `52a29bd`. Added the `api-login`
(5/min, email + IP) and `api-leads` (20/min, per token) limiters in `AppServiceProvider`; applied
both in `routes/api.php`; set `sanctum.expiration` to 30 days via `SANCTUM_EXPIRATION_MINUTES`;
revoked tokens in `EmployeeController@destroy`. New `tests/Feature/Api/AuthThrottleTest.php`, the
first test file under `tests/Feature/Api`.

**Tested.** `./vendor/bin/phpunit` — 268 pass, 1506 assertions (261 before, plus 7 new).
`php artisan route:list -v` confirms both throttle middlewares are attached.

**Unfinished.** Nothing on this task.

**Problems encountered.** Two, both instructive. My first expiry test was vacuous: it read the token
back from the database, where `plainTextToken` is null, so it sent an empty bearer and would have
passed even with expiry disabled. After fixing it to use the login response token, it failed — and
the investigation showed Sanctum's guard is resolved once per test process and cached, so an
authenticated call made before `travel()` keeps the token valid afterwards. That is a test-harness
artifact, not a production flaw, since each real request boots a fresh container. The expiry test now
makes a single authenticated request, with a separate test for the inside-the-window path.

**Decisions.** None new. The 30-day window is a default, not a decision; SEC-005 asks the business to
confirm it.

**New task IDs.** SEC-005 (production env decision for `SANCTUM_EXPIRATION_MINUTES`; existing mobile
tokens stop working on deploy). PERF-006 amended to include scheduling `sanctum:prune-expired`.

**Bookkeeping fix.** The UX-002 session reused two IDs that were already taken: its asset task was a
second `PERF-001` and its lead-form bug a second `BUG-006`. Renumbered to `PERF-007` and `BUG-011`,
since "continue with PERF-001" would otherwise be ambiguous. The Progress counts were also stale —
recounted from the table: 51 tasks, 46 pending, 1 fixed, 2 verified, 2 deferred, and by priority
High 9 / Medium 19 / Low 21. Before adding a row, grep the table for the next free number.

**Recommended next task.** BUG-001 — change order numbering race, missing status guards, and the
missing transaction. Independent, and unblocks ARCH-001, ARCH-002, and TEST-003.

---

## 2026-09-05 · Session 4 · BUG-001 change order integrity (and UX-002 verification)

**Investigated.** Read `ChangeOrder`, `ChangeOrderController`, and the existing seven tests, and
confirmed all three defects. Compared against `ProjectTask::nextNumber`, which is the pattern the
codebase already uses for safe numbering.

**Changed.** Branch `fix/BUG-001-change-order-integrity`, commit `19100d2`. Added
`ChangeOrder::nextNumber()` (lock plus caller retry) and `isPending()`; guarded `decide()` and
`revert()` on the status they expect; wrapped both in `DB::transaction`; switched `update()` to the
new helper. Seven new test cases.

Also moved UX-002 to 🔵 Verified after the maintainer did the manual visual pass on `/login`.

**Tested.** `./vendor/bin/phpunit` — 275 pass, 1537 assertions (268 before).

**Unfinished.** Nothing on this task.

**Problems encountered.** Three of my own test attempts were wrong before they were right, which is
worth recording. (1) I asserted that deleting CO-2 makes the next number 3; it is 2, because
numbering is `max + 1` over surviving rows. Rather than change the code I documented the behavior in
a test, since change order numbers are internal and only pending ones can be deleted. (2) The
rollback test first tried a string-length overflow to force a failure — SQLite ignores column
lengths. (3) It then tried deleting the parent project, which cascades the change order away. The
working version drops `project_budget_lines` mid-approval.

**Decisions.** None new. The deleted-number behavior is documented on the task rather than raised to
a decision, because it restates the existing distinction between internal and customer-facing
numbers (D-003 territory).

**New task IDs.** None. TEST-003 was satisfied by this work and marked verified alongside BUG-001.

**Recommended next task.** BUG-003 — case-sensitive `like` means directory and price-book search
silently misses rows on PostgreSQL while SQLite tests pass. Pairs naturally with TEST-002, which is
the reason the bug is invisible in CI today.
