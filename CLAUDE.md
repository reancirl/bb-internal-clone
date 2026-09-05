# BuffaloBuilt Internal — instructions for AI coding sessions

Internal operations platform for a residential general contractor. Laravel 12 monolith, Inertia 2 +
React 19 + TypeScript, Tailwind 4, Sanctum bearer API for the Expo mobile app. PostgreSQL in
production and local, SQLite in tests. Two roles: `admin` and `crew`.

## Start every session

1. Read `STATUS.md` in full. It is the single source of truth for what is done and what is left.
2. Verify the 🟡 In Progress task against the actual code before continuing it. Never trust the
   document over the code.
3. Follow the Session protocol at the bottom of `STATUS.md`. Update `STATUS.md` in the same commit
   as the code it describes.

## Commands

- Tests: `./vendor/bin/phpunit` (SQLite in memory). Run before marking anything Fixed.
- PHP style: `vendor/bin/pint --test` to check, `vendor/bin/pint` to fix.
- JS: `npm run lint`, `npm run format:check` to check, `npm run format` to fix.
- Dev server: `composer run dev`. Seed data: `php artisan db:seed` (idempotent).

## Conventions (do not regress these)

- **Money.** The price book stores decimal dollars; everything downstream stores integer cents.
  Convert once at the boundary via `TakeoffCosting::toCents`. Never add a new decimal money column.
- **Forms.** Frontend forms use Inertia `useForm`. Confirmations use `useConfirm`, never
  `window.confirm`. URLs use `route()`, not hard-coded strings.
- **Shared code.** Money and date helpers live in `resources/js/lib/`. Shared UI lives in
  `resources/js/components/buffalobuilt/`. Feature UI is split into
  `resources/js/components/<feature>/`; a page file holds the page component and its props type.
- **Document numbers.** Per-year and per-project sequences use `lockForUpdate` plus `retry(3)`; see
  `ProjectProposal::nextNumber`. Never `max + 1`.
- **Transactions.** Any action that writes more than one row runs inside `DB::transaction`.
- **Input.** Validate every query parameter. Never pass raw request input to `Carbon::parse`.
- **Web and API.** The two surfaces share FormRequests and transformers. Do not copy a controller.
- **Formatting.** Prettier with 4 spaces and single quotes (`.prettierrc`); Pint defaults for PHP.

## Rules for STATUS.md

- 🟢 **Fixed** means the code is complete and the full test suite passes. 🔵 **Verified** means a test
  covering that specific behavior exists and ran; UI-only work also needs a manual check. Write the
  command you ran in the task row.
- Never delete a task row. Never reuse an ID. Search the table before adding one so you do not create
  a duplicate.
- If the scope of a task changes, edit that task and add a dated note. Do not clone it.
- If the code contradicts `STATUS.md`, investigate, fix the document, and record it in `SESSIONS.md`.
- A new problem found mid-task becomes a new ID, not an expansion of the current one.

## Git

- Branch per task: `type/ID-slug`, for example `fix/BUG-003-case-insensitive-search`.
- Commit message: `type(scope): summary [ID]`, for example
  `fix(search): case-insensitive whereLike macro [BUG-003]`.
- `STATUS.md` changes ride in the same commit as the code.
- Do not push task work directly to `main`; merge the branch.
- Pushing to `main` triggers the production deploy (see `docs/11-deployment.md`, local only).

## Repository notes

- `docs/` is gitignored and local only. It holds deployment credentials and internal figures, and
  this repository is public. Never move its contents into a tracked path.
- Tracking files live at the repository root: `STATUS.md`, `DECISIONS.md`, `SESSIONS.md`,
  `AUDIT-2026-09.md`.
