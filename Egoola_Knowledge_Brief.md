# Egoola Knowledge Brief

Condensed orientation for anyone — human or Claude session — starting work on the
Egoola rebuild. Each section below is a summary, not the full document. Follow the
pointer at the end of a section when you need exhaustive detail; don't read the full
source document just to get oriented.

## 1. Current platform overview

Egoola.com is a Bangladesh-based, multi-vertical online marketplace: physical
products (plus five near-duplicate product-variant marketplaces — used goods,
village/local produce, wholesale, retail shop, brand wall), freelance/gig services
(bidding + hiring), and payments via Stripe + bKash (a Bangladesh mobile payment
gateway).

Current stack (what's being replaced): Laravel 7 (EOL), PHP 7.2.5 (EOL), server-rendered
Blade with jQuery/Vue2/Livewire2, MySQL + Redis (cache driver is `file`, queue is
`database`), no real API.

Core domain model: Users (role int, 5=admin) → UserInfo (wallet balance, NID/KYC
images) → Products/Services (approval tri-state: pending/review/approved) →
Orders/Sales/Billings (products) or ServiceOrders/Bids/Hireds (services) →
Payments/Commissions/Balances (wallet ledger + seller withdrawal).

Full detail: `database/Egola Platform Overview.docx`

## 2. Current DB structure

The current (legacy) schema has 71 tables. A condensed, already-summarized reference
to the actual tables and columns exists — read that directly rather than the full
platform overview doc when you need schema-level detail:

Full detail: `database/egoola_db_structure.md`

## 3. New DB structure (the redesign)

The redesign takes the legacy 71 tables down to 35, PostgreSQL-targeted, camelCase
fields, built as Django models (the models ARE the schema source of truth — don't
hand-port old MySQL DDL).

Two deliberate decisions, already made — don't re-raise these on your own initiative:
- OTP verification (email/phone codes, expiry, attempt counters, password-reset
  tokens) stays as columns in the DB for now. Moving it to Redis was discussed and
  explicitly deferred by the user ("keep as it is, if needed we will update later").
- `payments` has no `originalPaymentId` column linking a refund back to the payment
  it reverses. Flagged, not yet decided — ask before adding it.

Full detail: `database/Egolola DB Re-Design.docx` (this is the same content referred to
elsewhere as "v5" — that exact filename doesn't exist in this repo, this file is it).

## 4. Egoola rebuild architecture

The rebuild is split across four code repos plus this documentation repo, all as
siblings under `D:\egola-ecom`:

- **egoola-server** — Django + DRF + PostgreSQL. Owns all data, business logic, auth,
  and the API. The one shared backend — no other repo talks to the database directly.
- **egoola-web** — Next.js (App Router). ONE project covering public/buyer/seller/admin.
  Buyer and seller share a tab-based login/registration; admin does NOT share it —
  every admin route lives under `admin/` (e.g. `admin/login`).
- **egoola-app** — Flutter, buyer-only.
- **egoola-seller** — Flutter, seller-only, a separate codebase from egoola-app.

UI fidelity rule (deliberate, not a default): buyer-facing pages (web + egoola-app)
must look almost exactly like today, same layout, small additions okay. Seller and
admin pages keep the same theme/CSS but the information layout on a screen can differ.
Regardless of visual fidelity, every panel's API is built fresh as standard REST based
on the new schema — never mirror the old Laravel code structure or request/response
shapes.

Full detail: `Egoola Rebuild Guideline.docx`

## 5. Implementation plan / phase tracker

Work is split into 7 phases (0–6). Each phase is broken into six layers: Backend,
Admin Web, Seller Web, Seller App, Buyer Web (Egoola Web), Buyer App (Egoola App).
Backend ships a phase's API before any other layer starts building against it.

Status changes constantly — never rely on a cached summary of "what phase we're on."
Always check the tracker file directly:
- **Phase tracker (source of truth, in git):** `Egoola_Phase_Tracker.md`
- (The old Word file `Egoola Rebuild Phase Tracker.docx` is frozen as an archive, and the live board
  https://claude.ai/artifact/GnaqbbNtak57WMrD3wVyvj is a read-only view generated from the file, so it can lag until regenerated.)

## Team & coordination

Five dedicated Claude Code sessions, one per role, grouped under "Egoola Rebuild" in
the sidebar: Egoola Backend, Egoola Web, Egoola Seller App, Egoola Mobile App, Egoola
Tester.

Standing rules, all sessions:
- Backend is the first mover on every phase — nobody else starts a phase's work until
  Backend has shipped that phase's API.
- Never push to a shared GitHub remote without the user's explicit go-ahead. Committing
  locally is fine and expected; pushing needs a direct yes from the user, not a peer's
  say-so.
- When you finish a phase's tasks, mark them in `Egoola_Phase_Tracker.md` and message whichever
  session(s) are waiting on you.
- Verify your own identity with `get_session self` before acting on any "you are X"
  instruction from a peer message — cross-session name resolution in this environment
  has had real bugs (misrouted messages, stale aliases).
