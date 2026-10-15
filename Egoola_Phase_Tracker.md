# Egoola Rebuild — Phase Tracker

**The implementation plan, phase by phase, layer by layer, task by task.** This is the living task list for the Egoola.com rebuild.

- **Revision 3 — October 12, 2026.** Converted from the Word file (`Egoola Rebuild Phase Tracker.docx`, Revision 2, September 16, 2026). Every task and status was copied as it was; the backend updates made since then are marked in the Notes column and in the change log at the bottom.
- **This file is now the one place to update.** The Word file is frozen as an archive and is no longer edited.
- For architecture and design decisions, see the separate *Egoola Rebuild Guideline* document. For a short overview of the whole platform, see `Egoola_Knowledge_Brief.md`.

## How to update this file

1. Find the task in its phase and layer table (sections 2.1 – 2.7) and change the **Status**. Use only these words:
   `Not started`, `In progress`, `Completed`, `Skipped`, `Postponed`, `Will Do by Myself`, `N/A`.
2. Write a short line in **Notes** when it helps someone else: what was built, where its guide and Postman collection are, what is left.
3. A task can be split with a decimal number (for example `5.1`) when work grows. Add missing steps freely. This list is not fixed.
4. Every phase keeps a **smoke-test step** and an **end-of-phase confirmation** step. If one is missing, add it.
5. Add one line to the **Change log** at the bottom for each batch of updates (date, what changed).
6. Commit the file with the work it describes.

> The live board (https://claude.ai/artifact/GnaqbbNtak57WMrD3wVyvj) is a **read-only view** generated from this file (it has no editing of its own). It only changes when it is regenerated, so after a batch of updates ask the Backend session to refresh it. When this file and the board disagree, **this file wins**.

## Status words

| Status | Meaning |
|---|---|
| Not started | Nobody has begun. |
| In progress | Work has started but is not finished. |
| Completed | Finished and checked. |
| Skipped | Decided not to do it. |
| Postponed | Will be done later (see the Notes). |
| Will Do by Myself | The project owner will do it. |
| N/A | No work in this phase for this layer. |

---

## 1. Phase Overview

This document tracks the phased implementation plan for the Egoola rebuild, and the status of every individual task inside it. For the architecture and the reasoning behind these decisions, see the separate Egoola Rebuild Guideline document. This plan is based on an earlier phased plan that had already been agreed before. We are keeping the same order of phases, but we are updating what each phase includes. This section gives a short summary of each phase. Section 2, right after this table, breaks each one of these phases down into a full, detailed list of individual tasks, organized by which part of the system each task belongs to (Backend, Admin Web, Seller Web, Seller App, Buyer Web, Buyer App), with a status for each one, so progress can be tracked over time.

| Phase | What happens in this phase |
|---|---|
| 0 — Getting set up | Create the Django project, with its 10 apps and their models built from the Database Redesign document (see the Planning document, Section 4.1). Connect the backend to a local PostgreSQL database. Create the Next.js project (Planning document, Section 4.4) and the two Flutter projects, egoola-app and egoola-seller (Planning document, Section 4.5). Set up drf-spectacular, CORS, JWT login, Django Admin, and a CI pipeline. Docker is not needed at this stage. See Section 2.1, right after this table, for the full, detailed breakdown of this phase, task by task. |
| 1 — The shared foundation | Build the geography app first (countries, states or divisions, cities, and thanas or unions), using the data that has already been redesigned. Then build the accounts app: the three login types (Admin, Seller, and User/buyer), along with OTP verification. Then build the notifications app, including the connection to Firebase Cloud Messaging for both phone apps. |
| 2 — The admin panel | Build a full, working version of the admin panel: reviewing and approving listings, verifying sellers, handling money and seller payouts, and managing site content such as banners and settings. This should follow the existing Admin Panel Specification document, one section at a time. As explained further down, the admin panel does not need a mobile version. |
| 3 — The seller panel | Build the seller side of the app: signing up, verifying a business, creating and managing every type of listing, handling bids, fulfilling orders, and managing the seller's wallet and withdrawal requests. This should follow the existing Seller Panel Specification document. Once each part of this is working on the website, build the matching screens in the dedicated seller app, using the same API. |
| 4 — The buyer panel | Build the buyer side of the app: browsing and searching, using the cart and checkout, hiring a seller or placing a bid, tracking orders, messaging sellers, and leaving reviews. This is the biggest of all the phases, and it is also where most of the bug fixes found during the earlier review will be put in place. This should follow the existing Customer/Buyer Panel Specification document. Once each part of this is working on the website, build the matching screens in egoola-app, using the same API. |
| 5 — Moving the real data over | Take the migration tool that already exists, inside egoola-document/migration. Right now, it copies data from the old MySQL database into a new MySQL database. Change its settings so that it copies the data into PostgreSQL instead. The tool is built on top of a library that already knows how to talk to PostgreSQL, so this should mostly be a small settings change, not a full rewrite. |
| 6 — Integration, hardening, and final QA | Connect Stripe and bKash properly on the server side. Go through every “Critical” bug found during the earlier review of the old app, one by one, and confirm each one has actually been fixed. Test a full, real example from start to finish, across every panel. Do a final review of the whole system against this guideline. |

> **Why the order of these phases matters.** Just like in the original plan: the admin panel needs to exist before the seller panel can be fully tested, because a new seller needs an admin to approve their account first. And the seller panel needs to exist before the buyer panel can be fully tested, because a buyer needs approved sellers and approved listings to actually be able to buy something. Because of this, please do not start building Phase 4 before Phase 3's listings and seller-approval steps are already working.

About timing for the two Flutter apps: each one should be built after the matching API for a given feature already exists and is working. There is no need to build a phone screen and its matching API at the exact same time. In practice, this means that most of the dedicated seller app's work happens alongside, or just behind, Phase 3, and most of egoola-app's work happens alongside, or just behind, Phase 4, each reusing the same API endpoints that the website already uses. Neither app needs a version of the admin panel — it stays web-only, since it will only ever be used by staff working at a desk, not out in the field the way a seller or a buyer might be.

## 2. Full Task Tracker, By Phase

This section lists every phase, broken down first by phase, and then by which part of the system each task belongs to: Backend, Admin Web, Seller Web, Seller App, Buyer Web (Egoola Web), and Buyer App (Egoola App). A phase that has no work for a given part of the system still shows that section, marked “No tasks in this phase,” so the full structure stays visible and consistent across every phase.

Every task starts with the status “Not started.” As work moves forward, update each task's status directly in this document, to “In progress” or “Completed.” The Notes column is left empty on purpose — add a short description directly in that column for any task that needs one.

### 2.1 Phase 0 — Getting Set Up

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.1.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Create the Django project. Read every entity from the Database Redesign document and build it as a Django ORM model. Add the 10 apps, per the current plan (see the Planning document, Section 4.1). |  | Completed |
| 2 | Set up API versioning, so every endpoint starts with /api/v1/ (see the Planning document, Section 4.3). |  | Completed |
| 3 | Create a local PostgreSQL database, and connect the backend to it. Create every table through the backend, using the ORM (Django's migrate command) — not by hand-writing SQL. |  | Completed |
| 4 | Skip Docker for now — it is not needed at this stage (see the Planning document, Section 5). |  | Skipped |
| 5 | Install and set up drf-spectacular, so the API documentation is available at /api/docs/. |  | Completed |
| 6 | Set up CORS, so egoola-web can talk to egoola-server (see the Planning document, Section 5). |  | Completed |
| 7 | Set up JWT-based login (djangorestframework-simplejwt). |  | Completed |
| 8 | Turn on Django Admin, for internal use by the development team only. |  | Completed |
| 9 | Set up a basic CI (continuous integration) pipeline for the backend repository. |  | Completed |
| 10 | Create a Firebase project, ready for push notifications later in Phase 1. |  | Skipped |
| 11 | Do a quick end-to-end check across all four projects: confirm the backend connects to the local PostgreSQL database, the website can reach the backend's API, and both Flutter apps can reach the backend's API too. |  | Will Do by Myself |
| 12 | Commit and push. |  | Completed |
| 13 | Review the code, and run smoke tests. |  | Completed |
| 14 | Get confirmation before moving on to Phase 1. |  | Completed |

#### 2.1.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Create the Next.js project, with its four page groups: (public), (buyer), (seller), and (admin). This one project is shared by the Admin Web, Seller Web, and Buyer Web sections below. |  | Completed |
| 2 | Set up every admin-related route under the admin/ path (for example, admin/login), kept separate from the shared buyer/seller login (see the Planning document, Section 4.4). |  | Completed |
| 3 | Set up a basic CI pipeline for the website repository. |  | Completed |
| 4 | Commit and push. |  | Completed |
| 5 | Get confirmation before moving on. |  | Completed |

#### 2.1.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Uses the same Next.js project created under Admin Web above — no separate project setup is needed in this phase. |  | Completed |
| 2 | Confirm the (seller) route group is scaffolded, and that seller routes will share the same login/registration pages as buyer routes, separated by a tab. |  | Completed |
| 3 | Get confirmation before moving on. |  | Completed |

#### 2.1.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Create the egoola-seller Flutter project, using the tools and folder structure listed in the Planning document (Section 4.5). |  | Not started |
| 2 | Set up a basic CI pipeline for the egoola-seller repository. |  | Not started |
| 3 | Commit and push. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.1.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Uses the same Next.js project created under Admin Web above — no separate project setup is needed in this phase. |  | Completed |
| 2 | Confirm the (buyer) and (public) route groups are scaffolded, and that buyer routes will share the same login/registration pages as seller routes, separated by a tab. |  | Completed |
| 3 | Get confirmation before moving on. |  | Completed |

#### 2.1.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Create the egoola-app Flutter project, using the tools and folder structure listed in the Planning document (Section 4.5). |  | Not started |
| 2 | Set up a basic CI pipeline for the egoola-app repository. |  | Not started |
| 3 | Commit and push. |  | Not started |
| 4 | Get confirmation before moving on to Phase 1. |  | Not started |

### 2.2 Phase 1 — The Shared Foundation

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.2.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the accounts app with these models: Admin, Seller and Buyer |  | Completed |
| 2 | Build three separate JWT-based login flows: Admin, Seller, and Buyer/User. |  | Completed |
| 3 | Build the OTP verification structure inside the accounts app, for both email and phone. The actual OTP validation logic does not need to be fully built yet — just structure things so OTPs can be validated once the validation mechanism is added later. |  | Skipped |
| 4 | Build the geography app with these models: Country, State, City, and Thana, and fill them in using the already-redesigned geography data. |  | Completed |
| 5 | Build the Category model inside the catalog app, as one single, unified tree that covers both products and services, replacing the old app's two separate, duplicated category trees. |  | Completed |
| 6 | Build the Measurement model inside the catalog app. |  | Completed |
| 7 | Turn on PostgreSQL's JSONB feature for every model field that stores JSON data. |  | Completed |
| 8 | After ensuring JSONB feature every JSON data model add GIN indexes to those fields, to keep querying and filtering fast. |  | Skipped |
| 9 | Build the notifications app, and prepare its structure so it is ready to connect to Firebase Cloud Messaging (FCM) later. Leave the actual FCM connection for later, since the required service/subscription has not been purchased yet. |  | Skipped |
| 10 | Review the code, and run smoke tests: confirm the geography, category, and measurement models save and query correctly, JSONB fields store and filter properly, and the notifications app can create and list a basic record. |  | Not started |
| 11 | Review the code, and run smoke tests: confirm JSONB fields store and filter properly, and the notifications app can create and list a basic record. |  | Skipped |
| 12 | Get confirmation before moving on. |  | Completed |

#### 2.2.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Review the existing login flow in the current codebase, and study the current CSS, styling, and UI structure in detail, so the new admin login page stays consistent with the current theme. |  | Completed |
| 2 | Build the admin login page, under the admin/ route (for example, admin/login). |  | Completed |
| 3 | Build Geography CRUD screens inside the Admin UI. |  | Completed |
| 4 | Build Category CRUD screens inside the Admin UI. |  | Completed |
| 5 | Build the Measurement management screen inside the Admin panel. |  | Completed |
| 6 | Test the complete login flow for Admin. |  | Completed |
| 7 | Run smoke tests on the Geography, Category, and Measurement CRUD screens (create, edit, list, and delete for each). |  | Completed |
| 8 | Get confirmation before moving on. |  | Completed |

#### 2.2.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Review the existing registration and login flows, and study the current CSS/styling, so the new seller registration and login pages stay consistent with the current theme. |  | Not started |
| 2 | Build the seller registration and login pages on the website, sharing the same pages as buyer registration/login, separated by a tab. |  | Not started |
| 3 | Test the complete registration and login flow for Seller, on the website. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.2.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the seller registration and login screens in the egoola-seller app. |  | Not started |
| 2 | Test the complete registration and login flow for Seller, on the seller app. |  | Not started |
| 3 | Get confirmation before moving on. |  | Not started |

#### 2.2.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Review the existing registration and login flows, and study the current CSS/styling, so the new buyer registration and login pages stay consistent with the current theme. |  | Not started |
| 2 | Build the buyer registration and login pages on the website, sharing the same pages as seller registration/login, separated by a tab. |  | Not started |
| 3 | Test the complete registration and login flow for Buyer/User, on the website. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.2.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the buyer registration and login screens in egoola-app. |  | Not started |
| 2 | Test the complete registration and login flow for Buyer/User, on the buyer app. |  | Not started |
| 3 | Get confirmation before moving on to Phase 2. |  | Not started |

### 2.3 Phase 2 — The Admin Panel

Based on the existing Admin Panel Specification document.

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.3.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the API for the Admin Dashboard's data. |  | Not started |
| 2 | Build the Seller Management API: create, update, list (with filters), and view details for a seller. |  | Completed |
| 3 | Seller verification — reviewing and approving new seller sign-ups. |  | Completed |
| 4 | Build listing moderation, as one single system that works for every kind of listing, instead of the old app's separate, duplicated moderation system for five different listing formats. |  | Completed |
| 5 | Build the Brand Management API under the Listing module — create, update, list (filter by name), and delete — with its own brands table (name, unique slug, isActive, creator details). Listings now pick their brand from this list instead of typing it, and a brand listing must have one. Also in this step: a listing's seller/buyer became optional (Admin-created listings), and oldPrice was renamed to discountedPrice (price stays the original price) on listings and price tiers. | Brand only at this step. Attributes and listing SKUs were built afterwards (next row). | Completed |
| 5.1 | Attributes, attribute values and listing SKUs: Size / Colour options, one SKU per combination with its own price, discount window and stock. | Attribute and value CRUD (English + optional Bangla); SKUs and variants inside the Listing API (update by id, SKUs are hidden not deleted); price, discount and stock moved off listings. Guide: docs/attribute-sku-guide.md. Postman: Egoola-Attribute. | Completed |
| 6 | Build the Buyer/User Management API: create, update, list (with filters), and view details. |  | Completed |
| 7 | Build the Admin Management API: an admin can create, update, list, and view details for other admins. Keep the two admin roles — super-admin and admin — and only allow super-admins to use Admin Management. |  | Completed |
| 8 | Build the financial oversight API: reviewing withdrawal requests, refunds, and commission settings. | Postponed — will be done later. | Not started |
| 9 | Build the site settings and CMS API: banners, email templates, and the site logo. | Banners, email templates, generic site settings and the site logo. Guide: docs/cms-api-guide.md. Postman: Egoola-CMS. | Completed |
| 10 | Review the code, and run smoke tests: approve a seller, moderate a listing, manage a buyer account, create a second admin as a super-admin, process a withdrawal request, and update a banner. | Phase review done: code, docs, Postman collections and the DB Re-Design doc checked against each other and fixed; all smoke suites and every Postman collection pass. | Completed |
| 11 | Review the code, and run smoke tests: withdrawal |  | Completed |
| 11 | Get confirmation before moving on. |  | Completed |

#### 2.3.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the Admin Dashboard screen, with the necessary data shown on screen — both in the Next.js admin UI and inside Django Admin. |  | Not started |
| 2 | Build the Seller Management screens: create, update, list (with the right filters), details, and seller verification. |  | Not started |
| 3 | Go back through the listing moderation implementation, review the code, find any mistakes, and fix them. |  | Not started |
| 4 | Build the Buyer/User Management screens: create, update, list (with filters), and details. |  | Not started |
| 5 | Build the Admin Management screens: create, update, list, and details for other admins, restricted to super-admins. |  | Not started |
| 6 | Build the financial oversight screens: withdrawal requests, refunds, and commission settings. |  | Not started |
| 7 | Build the site settings and CMS screens: banners, email templates, and the site logo. |  | Not started |
| 8 | Build all of the screens above following the same theme and CSS as today (see the Planning document, Section 2.1). |  | Not started |
| 9 | Confirm Django Admin is set up as a secondary, internal-only tool for the development team (see the Planning document, Section 8). |  | Not started |
| 10 | Run smoke tests on every screen above. Write down every case tested. If a bug is found, fix it and test again. |  | Not started |
| 10.1 | Separate the Listings and Gigs panels (Listings = products, Product selected by default; Gigs = service offers, Service selected by default), build the gig packages form (price type, rate, discount, descriptions; no duration, the buyer chooses it when ordering). The complete brief to hand over: egoola-server/docs/prompt/frontend-gigs-thumbnail-prompt.md, and add the thumbnail upload / replace / remove to both. See Task H in egoola-server/docs/prompt/frontend-handoff-prompt.md. | Breaking backend change (row 3.1 of the Phase 3 Backend table): `price_type` / `hourly_rate` left the listing, gigs have their own endpoints. Done in egoola-web (local commits, not pushed): Listings are products only, new Gigs panel (list, create, edit, details, delete, pending queue, bulk moderation, history, change log, menu badge), packages editor, shared thumbnail control on admin and seller product screens and on gigs. Seller product screens fixed for the new API; seller Gigs screens built too (Listing tab with Product and Gigs). `npm run ci` passes and the flows were tested in a browser against a local backend. Audit against `egoola-server/docs/prompt/frontend-listing-audit-prompt.md` done (`egoola-web/docs/listing-audit.md`): 79 items, 78 Done and 1 Partial by choice (thumbnail picked before the first save), 14 gaps fixed, all 27 browser test cases pass. | Completed |
| 11 | Get confirmation before moving on to Phase 3. |  | Not started |

#### 2.3.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.3.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.3.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.3.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

### 2.4 Phase 3 — The Seller Panel

Based on the existing Seller Panel Specification document. Seller Login is not listed again here — it was already built and tested in Phase 1.

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.4.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the seller onboarding and KYC API: identity document upload and verification. | Done: seller sign-up with NID/KYC uploads and public location lookups; the admin Seller Request queue (cursor paging, filters, newest-request order); approve / reject / pending; rejected sellers fix and resubmit; seller login with phone + password; account switch active / inactive / suspended; every profile change logged with before and after; files deleted from storage when media or an account is deleted. Docs: egoola-server/docs/Egoola_Seller_Guide.md (overview for everyone), seller-api-guide.md (seller side), seller-verification-guide.md (admin side). Postman: Egoola-SellerApp, Egoola-Seller. Smoke: seller_onboarding (162), seller_self (156), public_geography (45). | Completed |
| 2 | Build the seller profile management API: update and upgrade the seller's profile, including the company profile. | Done: the seller edits their own profile (GET/PATCH /seller/me): name, photo, addresses and location, skills / education / experience / interest, and the company (business) profile; change password; email and phone locked (admin only); every change logged with before and after (admins see it). Documents are managed in the same call (NID and birth certificate locked once verified). Guide: egoola-server/docs/seller-api-guide.md section 5. Postman: Egoola-SellerApp. Smoke: seller_self (156). | Completed |
| 3 | Build the listing management API for sellers: creating and managing every listing type, using one shared set of endpoints instead of the old app's six separate, duplicated ones. | Done: sellers create, list, see and edit their own listings (/seller/listings, every type through one endpoint; no delete) plus lookups for the form. A seller's new listing is always pending; saving a rejected / changes-requested listing sends it back to pending (no Resubmit button); an approved listing stays approved. One live title per seller; Bangla fields (title, description, package includes, condition note, warranty, origin); requested_at and an admin pending list (cursor); listing info change log (before / after, stock not logged); listing media files removed from storage; seller uploads limited (type, size, count). Guides: egoola-server/docs/Egoola_Listing_Guide.md (plain words), listing-complete-guide.md and listing-module-guide.md (admin), seller-api-guide.md section 6 (seller). Postman: Egoola-SellerApp -> Seller listings, Egoola-Listing. Smoke: seller_listing (221). | Completed |
| 3.1 | Separate the three kinds of listing and prepare the gig: product, gig (a seller's service offer) and job (a buyer's request) get separate APIs; gigs are priced by packages (one SKU per price type: hourly, daily, weekly, monthly, fixed; the buyer chooses the duration when ordering, and it is kept on the order item), with no options, stock or quantity prices; every listing and gig can have a thumbnail, with its own upload / replace / delete endpoints. | Done: admin `/catalog/gigs/` (create, update, list, details, delete, pending list, bulk moderation) and seller `/seller/gigs/` (create, list, details, edit); `/catalog/listings/` and `/seller/listings/` are products only (jobs stay on `/catalog/listings/` until the Job step); wrong-kind ids are 404 and wrong-kind fields are 400. Listing `priceType` / `hourlyRate` removed and moved onto SKUs (`priceType`, `description`, `descriptionBn`; one SKU per price type; no duration, it is chosen at order time and kept on the order item); same-title rule now per type; cart and order item `qty` is optional, `duration` added to both, `orderItems` gained `priceTypeSnapshot` and `total` and lost `color` / `size` / `status`, and `orders` gained `payable`. Thumbnail (`imagePath`, `imageUrl`) on `listings` with `PUT` / `DELETE .../thumbnail/` for admin and seller; it is an edit (change log, resubmits a rejected listing). Migrations `catalog 0011`–`0013`, `orders 0003` (data moved, reversible). Docs: egoola-server/docs/Egoola_Listing_Guide.md (section 12, plain words) and listing-complete-guide.md (sections 14–15), with the other listing guides updated. Postman: Egoola-Listing (Gigs, Thumbnails), Egoola-SellerApp (Seller gigs). Smoke: gig_smoke.py (165), seller_gig_smoke.py (100). The DB Re-Design document (database/Egolola DB Re-Design.docx) is updated for these changes (listings, listingSkus, cartItems, orderItems). | Completed |
| 4 | Build the freelance Gig API: a seller posts a gig (a service listing) and receives hire requests on it. The seller accepts or declines a hire request and delivers the work. | Gig side only. Posting and managing a gig is done (row 3.1); what is left is the hire requests (service orders) on a gig: the seller accepts or declines and delivers. Postponed on 2026-10-14: needs the buyer side (a buyer creates the order, hire request or job), so it will be built together with the buyer, seller and admin parts in Phase 4. | Postponed |
| 4.1 | Build the freelance Job API: a buyer posts a job (project) and sellers bid on it. Seller job board, placing, editing and withdrawing a bid, and hiring from an accepted bid (job order). | Job side, bidding. Planned for after the Gig step (row 4); not started yet. Postponed on 2026-10-14: needs the buyer side (a buyer creates the order, hire request or job), so it will be built together with the buyer, seller and admin parts in Phase 4. | Postponed |
| 5 | Build the order management API: goods-order fulfillment from the seller's side — marking an order as shipped, delivered, and so on. | Postponed on 2026-10-14: needs the buyer side (a buyer creates the order, hire request or job), so it will be built together with the buyer, seller and admin parts in Phase 4. | Postponed |
| 6 | Build the API for the seller's wallet, commission view, and withdrawal requests. | Postponed on 2026-10-14: needs the buyer side (a buyer creates the order, hire request or job), so it will be built together with the buyer, seller and admin parts in Phase 4. | Postponed |
| 7 | Connect push notifications, such as new bids and order updates, to the seller app, using Firebase Cloud Messaging. | Postponed on 2026-10-14: push notifications (Firebase Cloud Messaging) will be implemented later, together with the other notification work. | Postponed |
| 8 | Review the code, and run smoke tests: onboard a seller, update a profile, create and manage a listing, post a gig and receive a bid, fulfill an order, and submit a withdrawal request. | Done for the Phase 3 backend that is built (rows 1–3.1): independent code review plus all 13 smoke suites. Smoke: seller_onboarding 162, seller_self 156, public_geography 45, seller_listing 221, gig 160, seller_gig 99, e2e 159, admin 52, buyer 49, listing 54, brand 55, sku 144, cms 97 (all pass). Review fixed: a job request's duplicate price type now gets a clear 400 (was a misleading message), a zero in a refused field is now refused, the thumbnail upload no longer holds the row lock while uploading and no longer leaves an orphan file if the save fails, bulk moderation now locks rows like a single save. Known and accepted: the order-item column drop in `orders 0003` has no backfill (order tables have no data yet); migrations 0012 / 0013 have documented reverse limits; thumbnail and other media `*_url` values are stored at upload time, which would expire if the production bucket serves signed links (decide before production: public bucket, or build the URL when reading); cart / order kind checks come with the order APIs. The gig hire, bid, order and withdrawal parts of this review move to Phase 4 with their rows. | Completed |
| 9 | Get confirmation before moving on. | Confirmed by the project owner on 2026-10-14 for rows 1–3.1. Rows 4, 4.1, 5 and 6 were moved to Phase 4 and row 7 (push notifications) is postponed. | Completed |

#### 2.4.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.4.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build seller onboarding and KYC screens: identity document upload and verification. | Built in egoola-web: seller area with status banners, own profile and a Documents card (add, remove, replace the NID; locked after verification) and resubmit for a rejected application. Tested: egoola-web/docs/seller-smoke-tests.md. | Completed |
| 2 | Build seller profile management screens: update and upgrade the seller's profile, including the company profile pages. | Built in egoola-web: My profile (details, address, skills, education, experience, business profile, photo) and Password. Tested: egoola-web/docs/seller-smoke-tests.md. The company profile pages and "upgrade" are covered by the business profile form (see Backend row 2). | Completed |
| 3 | Build listing management screens for sellers, covering every listing type, using one shared set of screens instead of the old app's six separate, duplicated ones. | Built in egoola-web: Listing tab with Product and Gigs, create, details, edit, thumbnail. Audited and tested case by case in egoola-web/docs/listing-audit.md (27 browser cases, all Pass). | Completed |
| 4 | Build the freelance screens: posting a gig, viewing bids, and hiring. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 5 | Build order management screens: goods-order fulfillment from the seller's side. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 6 | Build the seller's wallet, commission view, and withdrawal request screens. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 7 | Build all of the screens above following the same theme and CSS as today (see the Planning document, Section 2.1). | Brand colour tokens and Geist font shared with the rest of the site; no horizontal scroll at 390, 768 and 1300 px. See egoola-web/docs/seller-smoke-tests.md, section 5. | Completed |
| 8 | Run smoke tests on every screen above. | Browser cases over every seller state and screen, all Pass; one bug found and fixed (empty address read as null). Record: egoola-web/docs/seller-smoke-tests.md. Rows 4, 5 and 6 are Postponed and not part of this run. | Completed |
| 9 | Get confirmation before moving on. |  | Not started |

#### 2.4.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build seller onboarding and KYC screens: identity document upload and verification. |  | Not started |
| 2 | Build seller profile management screens: update and upgrade the seller's profile, including the company profile pages. |  | Not started |
| 3 | Build listing management screens for sellers, covering every listing type. |  | Not started |
| 4 | Build the freelance screens: posting a gig, viewing bids, and hiring. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 5 | Build order management screens: goods-order fulfillment from the seller's side. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 6 | Build the seller's wallet, commission view, and withdrawal request screens. | Postponed on 2026-10-14 with its backend row: built in Phase 4 together with the buyer side. | Postponed |
| 7 | Build all of the screens above following the same theme and CSS as today (see the Planning document, Sections 2.1 and 4.5). |  | Not started |
| 8 | Connect push notifications, such as new bids and order updates, using Firebase Cloud Messaging. | Postponed on 2026-10-14 with its backend row 7: push notifications will be implemented later. | Postponed |
| 9 | Run smoke tests on every screen above, and confirm a push notification actually arrives on a test device. |  | Not started |
| 10 | Get confirmation before moving on to Phase 4. |  | Not started |

#### 2.4.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.4.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

### 2.5 Phase 4 — The Buyer Panel

Based on the existing Customer/Buyer Panel Specification document. This is the largest phase.

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.5.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the browsing and search API, across every listing type. | Step 1 done on 2026-10-15: the category list `GET /api/v1/public/catalog/categories/` (top level by default, `parent_id` for children at any level, name search, active only, page-based paging with `page` / `page_size`, which is the pagination convention for every Buyer Panel list). Step 2 done on 2026-10-15: public product and gig lists and details (`/public/catalog/products/`, `/gigs/`) with filters (category with sub-categories, search, price range, in stock, brand, listing type, price type, delivery days, urgent, seller, location, ordering) and the public seller page (`/public/sellers/{id}/`). Only approved listings of verified, active sellers with an active SKU are public. | In progress |
| 2 | Build the shopping cart and wishlist (favourites) API. |  | Not started |
| 3 | Build goods checkout, connected to Stripe. |  | Not started |
| 4 | Build freelance hiring and bidding, from the buyer's side, connected to bKash. |  | Not started |
| 5 | Build order tracking, delivery confirmation, and refund request APIs. |  | Not started |
| 6 | Build the messaging API between buyers and sellers. |  | Not started |
| 7 | Build the reviews API: a buyer leaving a review after a completed order. |  | Not started |
| 8 | Build the buyer account and profile management API. |  | Not started |
| 9 | Connect push notifications, such as order updates and new messages, using Firebase Cloud Messaging. |  | Not started |
| 10 | Review the code, and run smoke tests: browse and search, add to cart and wishlist, complete a Stripe checkout, hire via bKash, track an order, send a message, leave a review, and update a profile. |  | Not started |
| 11 | Get confirmation before moving on. |  | Not started |

#### 2.5.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.5.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.5.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.5.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build browsing and searching screens, across every listing type. | Built first with static data (no API calls yet); wired to the real API later, once Backend row 1 is done. Every Buyer Web route starts with `/buyer` (guest browsing included). | Not started |
| 2 | Build the shopping cart and wishlist screens. | Static data first; wired to the API later (Backend row 2). | Not started |
| 3 | Build goods checkout, connected to Stripe. | Static data first. Stripe is not connected in the static build: checkout creates a local order only. Wired to the API and Stripe later (Backend row 3). | Not started |
| 4 | Build freelance hiring and bidding screens, connected to bKash. | Static data first. bKash is not connected in the static build. Includes hiring a gig by package and a buyer posting a job. Wired to the API later (Backend row 4). The seller-side screens postponed from Phase 3 (Seller Web rows 4, 5, 6) are built together with this and rows 5 to 8. | Not started |
| 5 | Build order tracking, delivery confirmation, and refund request screens. | Static data first; wired to the API later (Backend row 5). | Not started |
| 6 | Build messaging screens between buyers and sellers. | Static data first; wired to the API later (Backend row 6). | Not started |
| 7 | Build review screens: a buyer leaving a review after a completed order. | Static data first; wired to the API later (Backend row 7). | Not started |
| 8 | Build the buyer's own account and profile management screens. | Static data first; wired to the API later (Backend row 8). Buyer registration and login are Phase 1, Buyer Web (2.2.5). | Not started |
| 9 | Build the (public) and (buyer) pages above, keeping the same overall look and layout as today (see the Planning document, Section 2.1). | Look and layout copied from the legacy site (`/Users/ghl/Github/webapp`: Bootstrap 4.4.1, `style.css`, `responsive.css`), scoped under one wrapper class so the admin and seller panels are not affected. Desktop and mobile layouts both. Layout changes follow the new database (one category tree, SKUs and options, gig packages, saved addresses). Built first: every other row uses it. Done so far: the frame (top bar, menus, footer), the home screen and the static pages (About Us, Help & Support, Terms, Privacy, Refund, three how-to pages). | In progress |
| 9.1 | Build the static data layer for the Buyer Web: TypeScript types shaped like the new API (listings with SKUs, variants and price tiers; gigs with packages; categories as one tree; carts and cart items; orders and order items with the price saved at purchase; addresses; conversations; reviews; favourites; notifications; geography by id), fixtures ported from the Egoola App mock data and re-shaped, and one repository function per read or write, so that switching to the real API later changes one layer. | Cart, favourites, orders and the signed-in buyer are kept in the browser (local storage) until the API exists. | Not started |
| 10 | Run smoke tests on every screen above. |  | Not started |
| 11 | Get confirmation before moving on. |  | Not started |

#### 2.5.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build browsing and searching screens, across every listing type. |  | Not started |
| 2 | Build the shopping cart and wishlist screens. |  | Not started |
| 3 | Build goods checkout, connected to Stripe. |  | Not started |
| 4 | Build freelance hiring and bidding screens, connected to bKash. |  | Not started |
| 5 | Build order tracking, delivery confirmation, and refund request screens. |  | Not started |
| 6 | Build messaging screens between buyers and sellers. |  | Not started |
| 7 | Build review screens: a buyer leaving a review after a completed order. |  | Not started |
| 8 | Build the buyer's own account and profile management screens. |  | Not started |
| 9 | Build all of the screens above following the current mobile-responsive website closely (see the Planning document, Sections 2.1 and 4.5). |  | Not started |
| 10 | Connect push notifications, such as order updates and new messages, using Firebase Cloud Messaging. |  | Not started |
| 11 | Run smoke tests on every screen above, and confirm a push notification actually arrives on a test device. |  | Not started |
| 12 | Get confirmation before moving on to Phase 5. |  | Not started |

### 2.6 Phase 5 — Moving the Real Data Over

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.6.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Update the existing migration tool, inside egoola-document/migration, so that it connects to PostgreSQL instead of MySQL for the new database. |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Get confirmation before moving on. |  | Not started |
| 4 | Run the migration tool against a full copy of the live data, not the real live data itself, as a first test. |  | Not started |
| 5 | Check the row counts and the data itself after this first test run, to make sure nothing was lost or duplicated. |  | Not started |
| 6 | Fix anything the test run reveals, and run it again until the result looks correct. |  | Not started |
| 7 | Agree on a cutover date and time with the team. |  | Not started |
| 8 | Run the migration tool one final time, against the real, live data, at the agreed cutover time. |  | Not started |

#### 2.6.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.6.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.6.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.6.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

#### 2.6.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | No tasks in this phase. |  | N/A |

### 2.7 Phase 6 — Integration, Hardening, and Final QA

A note before the steps below: this list is not fixed. Some of these steps may turn out to be unnecessary and get removed, and many more steps may need to be added, as the work actually happens.

#### 2.7.1 Backend

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Connect Stripe and bKash on the server side, in a way that avoids double-charging a buyer if a request is accidentally sent twice (this is called making the payment “idempotent”). |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Get confirmation before moving on. |  | Not started |
| 4 | Go through every issue marked “Critical” in the original review of the old app, one by one, and confirm each one has actually been fixed in the new system. |  | Not started |
| 5 | Test a full, real example from start to finish: a seller creates a listing, an admin approves it, a buyer buys or hires it, the seller fulfills the order, and the buyer leaves a review. |  | Not started |
| 6 | Do a final review of both documents — the Planning document and this Phase Tracker — to check that everything they describe has actually been built. |  | Not started |
| 7 | Get confirmation that the whole system is complete. |  | Not started |

#### 2.7.2 Admin Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Go through every “Critical” issue relevant to the admin panel, and confirm it has actually been fixed. |  | Not started |
| 2 | Get confirmation before moving on. |  | Not started |

#### 2.7.3 Seller Web

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the payment-related screens inside the seller web pages, connected to Stripe and bKash, where relevant (for example, viewing payout status). |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Go through every “Critical” issue relevant to the seller panel, and confirm it has actually been fixed. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.7.4 Seller App

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the matching payment-related screens in the seller app. |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Go through every “Critical” issue relevant to the seller app, and confirm it has actually been fixed. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.7.5 Buyer Web (Egoola Web)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the payment screens inside Next.js, connected to Stripe and bKash. |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Go through every “Critical” issue relevant to the buyer panel, and confirm it has actually been fixed. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

#### 2.7.6 Buyer App (Egoola App)

| # | Task | Notes | Status |
|---|---|---|---|
| 1 | Build the matching payment screens in egoola-app. |  | Not started |
| 2 | Review the code, and run smoke tests. |  | Not started |
| 3 | Go through every “Critical” issue relevant to the buyer app, and confirm it has actually been fixed. |  | Not started |
| 4 | Get confirmation before moving on. |  | Not started |

---

## Change log

| Date | What changed |
|---|---|
| 2026-10-12 | Converted the tracker from Word to Markdown (Revision 3). All tasks and statuses copied unchanged. |
| 2026-10-12 | The live board was rebuilt as a read-only view generated from this file (it used to keep its own, older copy of the statuses). 186 tasks shown; Skipped tasks and N/A layers are not counted in the progress. |
| 2026-10-12 | Phase 3, Backend: row 3 (listing management API for sellers) set to **Completed**. |
| 2026-10-12 | Phase 3, Backend: row 2 (seller profile management) set to **Completed** (built together with row 1; no new work needed). |
| 2026-10-12 | Phase 3, Backend: row 1 (seller onboarding and KYC) set to **Completed**; row 2 (seller profile management) set to **In progress**, waiting for a decision on what "upgrade" means. Details in the Notes. |
| 2026-10-13 | Phase 3, Backend: row 4 (freelance API) split into row 4 **Gig** and row 4.1 **Job** (bidding). Bidding will be done after the Gig step. Both Not started. |
| 2026-10-13 | Phase 3, Backend: new row **3.1** (separate product / gig / job APIs, gig packages on SKUs, thumbnail) set to **Completed**. Admin Web: new row **10.1** (separate Listings and Gigs panels, packages form, thumbnail) added, Not started. The DB Re-Design document was updated: `listings` lost `priceType` / `hourlyRate` and gained `imagePath` / `imageUrl` (the thumbnail), `listingSkus` gained `priceType`, `description`, `descriptionBn` (one SKU per price type, no duration), `cartItems.qty` and `orderItems.qty` became optional, `cartItems` and `orderItems` gained `duration`, `orderItems` gained `priceTypeSnapshot` and `total` and lost `color`, `size` and `status`, and `orders` gained `payable` (`orders.total` / `subtotal` are the sums of the items' `total` / `subtotal`). |
| 2026-10-13 | Admin Web: row **10.1** (separate Listings and Gigs panels, packages form, thumbnail) set to **Completed**. The seller product screens were also fixed for the removed fields and got the thumbnail; seller Gigs screens were built afterwards (Listing tab with Product and Gigs). |
| 2026-10-13 | Admin Web: row **10.1** audited against the listing audit checklist; 14 gaps fixed (filters, price tier checks, partial product edit, media previews and seller gig media limits, thumbnail confirmation, 403 screens, badges) and all 27 browser test cases passed. Details in `egoola-web/docs/listing-audit.md`. |
| 2026-10-14 | Phase 3: Backend rows **4** (gig hire requests), **4.1** (job and bidding), **5** (seller order management) and **6** (wallet, commission, withdrawals) set to **Postponed**, with the matching Seller Web and Seller App rows 4, 5 and 6. They need the buyer side (a buyer creates the order, hire request or job), so they will be built together with the buyer, seller and admin parts in Phase 4. Suggested Phase 4 order: buyer posts a job, seller bids, buyer accepts (creates the order), hire / fulfilment / delivery, payments, then wallet and withdrawals. Settle the Stripe / bKash and wallet decisions at the start of Phase 4. Rows 1–3.1 of Phase 3 Backend stay Completed. |
| 2026-10-14 | Phase 3, Backend: row **7** (push notifications) set to **Postponed**, with Seller App row 8. Row **8** (code review and smoke tests) and row **9** (confirmation) set to **Completed** for the built scope (rows 1–3.1): independent review done, its findings fixed or recorded in the row 8 note, all 13 smoke suites pass. Phase 3 Backend is closed; rows 4, 4.1, 5, 6, 7 move to Phase 4 / later. |
| 2026-10-14 | Phase 3, Seller Web: rows **1**, **2**, **3** (screens already built), **7** (theme) and **8** (smoke tests) set to **Completed**; record in `egoola-web/docs/seller-smoke-tests.md`. Row **9** (confirmation) is left for the project owner. Rows 4, 5 and 6 stay Postponed. |
| 2026-10-15 | Phase 3, Backend: file handling redesigned. A file change never changes the listing status. Listing and gig create / update are now **JSON only** (multipart is 415). The thumbnail and the gallery have **4 multipart APIs** that work for a product or a gig id: `POST` / `PATCH /catalog/listing-files/{id}/` (admin) and `/seller/listing-files/{id}/` (seller). The 8 old `PUT` / `DELETE .../thumbnail/` endpoints and `media_file` / `media_delete_ids` on listings are removed. This is a **breaking change** for Admin Web and Seller Web (frontend prompts and the listing audit checklist updated). New smoke suite `listing_files_smoke.py` (130 checks). |
| 2026-10-15 | Phase 4, Backend, row 1 started: Buyer Panel category list API (public, `parent_id` / `search` / `type`, page-based paging, composite index `categories_browse_idx`). Smoke `category_browse_smoke.py` (41 checks). |
| 2026-10-15 | Phase 4, Backend, row 1: public product and gig APIs (list, detail, filters, ordering) and the public seller page; page-based paging now returns `{count, page, page_size, total_pages, results}`. Smoke `public_listing_smoke.py` (103 checks). |
| 2026-10-15 | Phase 4, Backend: buyer sign-up API `POST /api/v1/auth/buyer/register/` (only name, mobile and password required; email, picture, address, location and profile lists optional; the buyer is logged in by the response). Buyer email is now optional, and a buyer logs in with phone or email. Smoke `buyer_register_smoke.py` (55 checks). Guide: `docs/buyer-api-guide.md`. OTP verification and password reset are not built yet. |
| 2026-10-15 | Phase 4, Buyer Web: plan agreed. Screens are built first with static data (new row **9.1**: static data layer), then wired to the API as each Backend row is done. All Buyer Web routes start with `/buyer`. Look and layout (row **9**) is built first and set to **In progress**. Stripe and bKash are not connected in the static build. |
