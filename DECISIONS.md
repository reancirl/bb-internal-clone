# Architectural Decisions

Append-only. Newest at the bottom. A decision recorded here is not reversed by a later session
without a new entry that supersedes it.

Format: ID · date · decision · why · consequences · related tasks.

---

## D-001 · 2026-09-05 · Inertia props are the only server state; no client store

**Why.** Every page is server-rendered on navigation and receives fresh props; local `useState`
covers dialogs and drafts. The audit found no state that needs to outlive a page visit.

**Consequences.** Do not introduce Redux, Zustand, or TanStack Query. Optimistic local copies are
allowed only where the interaction demands it (the CRM kanban keeps one, re-synced from props and
rolled back on error).

**Related.** Audit §5.

---

## D-002 · 2026-09-05 · Work is tracked in root Markdown files, not GitHub Issues

**Why.** The repository is public and the backlog contains unfixed security findings that should not
be published before they are fixed. AI sessions also read files far cheaper than they query an API.

**Consequences.** `STATUS.md` is the single source of truth. Revisit if a second maintainer joins or
the repository becomes private.

**Related.** D-005.

---

## D-003 · 2026-09-05 · Money stays integer cents everywhere except the price book

**Why.** Existing data and a documented convention. Conversion happens once, at the price-book
boundary, in `TakeoffCosting::toCents`.

**Consequences.** Never add a new decimal money column. Frontend helpers must encode the unit in
their name (`formatCents` versus `formatDollars`) so an auto-import cannot render a value 100× off.

**Related.** Audit §3, REF-003.

---

## D-004 · 2026-09-05 · Web and API surfaces share implementations, never copies

**Why.** Five controller pairs are currently duplicated, so every rule change must be made twice and
the API half is untested. The audit found this to be the single largest maintainability cost.

**Consequences.** REF-001 introduces shared FormRequests, transformers, and a `TimeCardService`. Any
new endpoint added to either surface uses them from the start.

**Related.** Audit §13, REF-001, TEST-001.

---

## D-005 · 2026-09-05 · `docs/` stays gitignored; tracking files live at the repository root

**Why.** The original plan was to un-ignore `docs/` so decisions and session history would version
with the code. On inspection `docs/11-deployment.md` contains the production server IP, the SSH
username, and the application path, and this repository is public on GitHub. Publishing it would
disclose half of a production login.

**Consequences.** `STATUS.md`, `DECISIONS.md`, `SESSIONS.md`, and `AUDIT-2026-09.md` sit at the
repository root instead of under `docs/`. `.gitignore` keeps `/docs` excluded and is not changed.
Anything written into `docs/` remains local to one machine. If the repository is ever made private,
this decision can be revisited and the files consolidated under `docs/`.

**Related.** D-002, SEC-004.
