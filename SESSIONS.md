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
