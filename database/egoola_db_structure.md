# Egoola Database — Structure Documentation

**Source:** `egooxlkz_egoola.sql` (phpMyAdmin dump, MariaDB 11.4.12, generated 2026-08-15)
**Database:** `egooxlkz_egoola`
**Framework signature:** Laravel (`migrations`, `password_resets`, `failed_jobs`, `jobs`, `notifications` tables) — the app is **Egoola**, a Bangladesh-focused multi-vendor marketplace combining physical-goods commerce (5 shop formats), freelance/gig services, job postings, chat, wallet/commission payments, and geo-location reference data.
**Table count:** 71 tables.

---

## 1. Module Map

| Module | Tables |
|---|---|
| Auth & User Profile | `users`, `user_infos`, `verifies`, `verify_phone`, `otps`, `otp_logs`, `forget_otps`, `password_resets`, `contacts`, `skills`, `interests`, `edcations`, `experiences` |
| Seller/Company Info | `companies`, `company_files`, `certificats`, `documents` |
| Geography (reference data) | `countries`, `states`, `cities`, `unions`, `upazilas` |
| Product Categories | `grand_categories`, `parent_categories`, `child_categories` |
| Service Categories | `service_grands`, `service_parents`, `service_children` |
| Shop / Listing formats | `products`, `product_images`, `used_malls`, `village_products`, `retail_shops`, `wholesales`, `brand_walls` |
| Services (freelance/gig) | `services`, `service_images`, `service_orders`, `bid_for_services`, `hireds`, `hire_pays` |
| Cart / Order / Checkout | `carts`, `sales`, `orders`, `order_pays`, `billings`, `shippings`, `delivery_costs`, `simple_reqs` |
| Payments & Wallet | `payments`, `payment_requests`, `balances`, `refund_histories`, `transactions`, `commissions`, `commission_payments`, `seller_payments` |
| Reviews / Favourites | `reviews`, `favourits` |
| Messaging & Notification | `chats`, `messages`, `notifications` |
| CMS / Site config | `banners`, `sidebar_banners`, `email_contents`, `others`, `measurements` |
| Analytics / System | `visitors`, `jobs`, `failed_jobs`, `migrations` |

---

## 2. Table Definitions

Notation: `PK` = Primary Key, `FK→table` = explicit foreign key constraint found in the dump, `idx` = indexed but no FK constraint.

### Auth & User Profile

**users** — core account table
- id `bigint(20) unsigned` **PK**
- name `varchar(255)`
- email `varchar(255)` **UNIQUE**
- provider_id `varchar(255)` null — OAuth id
- provider `varchar(255)` null — OAuth provider
- email_verified_at `timestamp` null
- password `varchar(255)` null
- remember_token `varchar(100)` null
- role `tinyint(1)` default 1 — 1=buyer, others likely seller/admin tiers (role 5 seen for admin)
- created_at / updated_at `timestamp`

**user_infos** — extended profile (1:1 with users)
- id **PK**, user_id `bigint` FK→users (cascade)
- country_id, state_id, city_id, thana_id `bigint` null — geo reference (see §4 notes)
- address `varchar(255)` null, description `text` null, telnumber `varchar(255)` null
- path/image `varchar(255)` null — profile photo
- nid_path, nid_front, nid_back `varchar(255)` null — National ID verification docs
- reason `text` null — rejection/verification reason
- section `tinyint(1)` default 0 — verification stage
- review `tinyint(4)` default 0 — approval status
- ballance `bigint(20)` default 0 — wallet balance (sic, typo in source)
- bonous `bigint(20)` default 0 (sic)
- created_at / updated_at

**verifies** — email verification tokens
- id **PK**, email, secret, code `varchar(255)`, created_at/updated_at

**verify_phone** — phone verification numbers
- id **PK**, number `varchar(255)`, created_at/updated_at

**otps** — phone OTP login/verification
- id **PK**, number `varchar(255)`, status `tinyint(1)` default 0, attempt `tinyint(4)` default 1, created_at/updated_at

**otp_logs** — historical OTP codes issued
- id **PK**, number_id `bigint` FK→otps (cascade), otp_code `varchar(255)`, status `tinyint(1)` default 0, created_at/updated_at

**forget_otps** — password-reset OTP flow
- id **PK**, number, otp `varchar(255)`, attempt `tinyint(4)` default 1, expire `tinyint(1)` default 0, token `varchar(255)` null, created_at/updated_at

**password_resets** — Laravel standard
- email `varchar(255)` (idx), token `varchar(255)`, created_at

**contacts** — public contact channels per user
- id **PK**, user_id `bigint` FK→users (cascade)
- email, whatsapp, phone, facebook, wechat, skype `varchar(255)` null
- created_at/updated_at

**skills** — user skill tags (freelancers)
- id **PK**, user_id `bigint` FK→users (cascade)
- skill `longtext` null — JSON array of skill IDs, e.g. `[612,613,617]`
- created_at/updated_at

**interests** — free-text interests
- id **PK**, user_id `bigint` FK→users (cascade), interest `longtext` null, created_at/updated_at

**edcations** (sic) — education history
- id **PK**, user_id `bigint` FK→users (cascade)
- degree, institute_name, start_year, end_year `longtext` null — each a JSON array (parallel arrays, one entry per degree)
- created_at/updated_at

**experiences** — work history
- id **PK**, user_id `bigint` FK→users (cascade)
- designation, company_name, start_year, end_year `longtext` null — parallel JSON arrays
- created_at/updated_at

### Seller / Company Info

**companies** — business profile (1:1 with users, business accounts)
- id **PK**, user_Id `bigint` FK→users (cascade) *(note inconsistent casing `user_Id`)*
- info `text`, btype, country, mainproduct, employes, owner, ravenue, establish `varchar(255)`
- certificat `varchar(255)` null, created_at/updated_at

**company_files** — uploaded docs per company
- id **PK**, company_id `bigint` FK→companies (cascade)
- path, name `varchar(255)`, what `tinyint(1)` default 1, created_at/updated_at

**certificats** (sic) — certification images
- id **PK**, company_id `bigint` FK→companies (cascade)
- path, image `varchar(255)`, created_at/updated_at

**documents** — misc doc uploads, polymorphic-ish by `what`
- id **PK**, brand_wall_id `bigint` null (no FK constraint, idx only conceptually)
- path, file `varchar(255)`, what `tinyint(1)` default 1, created_at/updated_at

### Geography (reference data)

**countries** — global country list (from "countries-states-cities" seed package)
- id `mediumint(8) unsigned` **PK**
- name `varchar(100)`, iso3 `char(3)`, iso2 `char(2)`, phonecode, capital, currency, native, emoji, emojiU `varchar`
- created_at, updated_at (default current_timestamp), flag `tinyint(1)` default 1, wikiDataId `varchar(255)` null

**states** — global states/divisions
- id `mediumint(8) unsigned` **PK**
- name `varchar(255)`, country_id `mediumint(8) unsigned` **FK→countries** (`country_region_final`)
- country_code `char(2)`, fips_code, iso2 `varchar(255)` null
- created_at null, updated_at (default current_timestamp), flag, wikiDataId

**cities** — global cities
- id `mediumint(8) unsigned` **PK**
- name `varchar(255)`, state_id **FK→states**, state_code `varchar(255)`
- country_id **FK→countries**, country_code `char(2)`
- latitude `decimal(10,8)`, longitude `decimal(11,8)`
- created_at (default '2014-01-01'), updated_on (default current_timestamp), flag, wikiDataId

**upazilas** — Bangladesh sub-districts (legacy/secondary table)
- id **PK**, name `varchar(255)`, city_id `int(10) unsigned` (idx, no FK; conceptually → cities.id, but only ~560 rows exist)
- created_at/updated_at

**unions** — Bangladesh union-level admin data (actively used as "thana")
- id **PK**, name `varchar(255)`, cities_id `bigint(20) unsigned` (idx `unions_upazila_id_foreign`, no enforced FK; conceptually → cities.id)
- created_at/updated_at
- *(~5246 rows — this is the table actual `thana_id` columns across the app resolve into; see §4)*

### Product Categories (3-level: grand → parent → child)

**grand_categories**
- id **PK**, name, path, image `varchar(255)`, section `tinyint(1)` default 0 (segments listings into Used Mall/Village Product/Retail/Wholesale/Brand Wall), created_at/updated_at

**parent_categories**
- id **PK**, grand_category_id `bigint` **FK→grand_categories** (cascade), name `varchar(255)`, created_at/updated_at

**child_categories**
- id **PK**, parent_category_id `bigint` **FK→parent_categories** (cascade), name `varchar(255)` (bilingual EN/Bengali), created_at/updated_at

### Service Categories (3-level: grand → parent → child)

**service_grands**
- id **PK**, name `varchar(255)`, path, image `varchar(255)`, section `tinyint(1)` default 0, created_at/updated_at

**service_parents**
- id **PK**, service_grand_id `bigint` **FK→service_grands** (cascade), name `varchar(255)` (bilingual), created_at/updated_at

**service_children**
- id **PK**, service_parent_id `bigint` **FK→service_parents** (cascade), name `varchar(255)`, created_at/updated_at

### Shop / Listing Formats

Egoola supports **5 parallel product-listing formats**, each an independent table with near-identical shape (category tree + geo + measurement):

**products** — main marketplace goods (import/wholesale style, has tiered pricing)
- id **PK**, user_id `bigint` **FK→users** (cascade)
- title, slug, import, price, oldPrice, min, qty, alrqty, use, brand, origin, type, model, color, certificate `varchar(255)` (various nullable)
- other `text` null, approv `tinyint(1)` default 1
- grand_category_id, parent_category_id, child_category_id, country_id, state_id, city_id `bigint unsigned`
- created_at/updated_at/deleted_at (soft delete)
- price_1, price_2, piece_1, piece_2, piece_3, capacity `varchar(255)` default '0' — tiered bulk pricing

**product_images** — polymorphic-style image table shared by products + 4 shop formats
- id **PK**, product_id `bigint` **FK→products** (cascade)
- path, image `varchar(255)`, what `tinyint(1)` default 1
- used_mall_id, village_product_id, retail_shop_id, wholesale_shop_id, brand_wall_id `bigint unsigned` null (no FK, used mutually exclusively depending on listing type)
- created_at/updated_at

**used_malls** — second-hand goods listings
- id **PK**, user_id **FK→users** (cascade), name, slug, brand `varchar(255)`
- grand_category_id, parent_category_id, child_category_id, country_id, city_id, thana_id `bigint unsigned`
- approve `tinyint(1)` default 1, purchase_date `timestamp`
- use_day, use_month, use_year, model, price `varchar(255)` null/required
- measurement_id `bigint` **FK→measurements** (implicit, no constraint), quantity `varchar(255)`, color, other `text` null
- created_at/updated_at/deleted_at

**village_products** — rural/organic-food listings
- id **PK**, user_id **FK→users** (cascade), name, slug `varchar(255)`, price `int(11)`
- grand_category_id, parent_category_id, child_category_id, country_id, city_id, thana_id `bigint unsigned`
- approve `tinyint(1)` default 1, measurement_id, quantity `varchar(255)`, other `text` null
- created_at/updated_at/deleted_at

**retail_shops** — retail-shop listings
- id **PK**, user_id **FK→users** (cascade), name, slug `varchar(255)`, price `int(11)`
- grand_category_id, parent_category_id, child_category_id, country_id, city_id, thana_id `bigint unsigned`
- approve `tinyint(1)` default 1, measurement_id, quantity `varchar(255)`, other `text` null
- created_at/updated_at/deleted_at

**wholesales** — wholesale-shop listings with 3-tier bulk pricing
- id **PK**, user_id **FK→users** (cascade), name, slug `varchar(255)`, brand `varchar(255)` null, price `int(11)`
- first/second/third_quantity `varchar(255)` null + first/second/third_quantity_price `int(11)` null — bulk price breaks
- grand_category_id, parent_category_id, child_category_id, country_id, city_id, thana_id `bigint unsigned`
- approve `tinyint(1)` default 1, measurement_id, quantity `varchar(255)`, minimum_quantity default '1'
- colors, sizes `varchar(255)` null, other `text` null
- created_at/updated_at/deleted_at

**brand_walls** — branded/warranty product listings
- id **PK**, user_id **FK→users** (cascade), name, slug, brand `varchar(255)`, price `int(11)`, warranty `varchar(255)`
- grand_category_id, parent_category_id, child_category_id, country_id, city_id, thana_id `bigint unsigned`
- approve `tinyint(1)` default 1, measurement_id, quantity `varchar(255)`
- colors, sizes `varchar(255)` null, other `text` null
- created_at/updated_at/deleted_at

**measurements** — unit lookup (Kg, Piece, Ton, Mon) referenced by all 5 shop formats

### Services (Freelance / Gig marketplace)

**services** — gig/project listing
- id **PK**, user_id **FK→users** (cascade)
- title, slug `varchar(255)`, price `int(11)` null, delivery `int(11)` null
- include `text` null, description `text`
- grand_id, parent_id `bigint unsigned` (→ service_grands/service_parents, implicit), child_id `bigint unsigned` null
- country_id, state_id, city_id, thana_id `bigint unsigned`
- approv `tinyint(1)` default 1, urgent `tinyint(4)` default 0
- created_at/updated_at/deleted_at
- start_time/end_time `timestamp` null — project deadline window
- priceType `tinyint(1)` default 0 (fixed vs hourly), hourPrice `int(11)` null, hour `int(11)` null
- saler_post `tinyint(4)` default 0 — flags whether posted by seller (offer) vs buyer (request)

**service_images**
- id **PK**, service_id `bigint` **FK→services** (cascade), path, image `varchar(255)`, what `tinyint(1)` default 1, created_at/updated_at

**service_orders** — direct hire/order on a service
- id **PK**, buyer_id, saler_id `bigint` **FK→users** (both, cascade), service_id `bigint` **FK→services** (cascade)
- price `bigint(20)` null, time `timestamp`, file `varchar(255)` null
- accept `tinyint(4)` default 0 (status), paymentMethod `int(11)`, created_at/updated_at

**bid_for_services** — buyer posts a job request, sellers bid
- id **PK**, buyer_id, saler_id `bigint` **FK→users** (both, cascade), service_id `bigint` **FK→services** (cascade)
- priceType `tinyint(4)` default 0, price `int(11)` null, hourPrice `int(11)` null, hour `int(11)` null
- description `varchar(255)`, start_time/end_time `timestamp`, created_at/updated_at

**hireds** — accepted bid → hire contract
- id **PK**, service_id, buyer_id, saler_id, bid_id `bigint unsigned` (conceptually → services/users/bid_for_services, no explicit FK)
- accept `tinyint(4)` default 0, price/hourPrice/hour `int(11)` null
- priceType `tinyint(4)`, paymentMethod `int(11)`, created_at/updated_at

**hire_pays** — payment record linking a hire to a payment
- id **PK**, payment_id, hire_od_id `bigint unsigned` (→ payments/hireds, no explicit FK), created_at/updated_at

### Cart / Order / Checkout (physical goods)

**carts**
- id **PK**, product_id `bigint unsigned` null, user_id, saler_id `bigint` **FK→users/products** (product_id, user_id have constraints; saler_id no constraint)
- qty, color, size `varchar(255)` null
- used_mall_id, village_product_id, retail_shop_id, wholesale_shop_id, brand_wall_id `bigint unsigned` null (mutually exclusive listing-type reference)
- created_at/updated_at

**sales** — an aggregated sale/checkout event (groups billings)
- id **PK**, user_id `bigint unsigned` (→ users, implicit)
- total `int(11)`, cost `int(11)` null, day/month/year `varchar(255)`
- delivary `tinyint(1)` default 1 (sic, delivery status), transaction `int(11)` default 0
- created_at/updated_at

**orders** — shipment/order envelope for a sale
- id **PK**, saler_id `bigint unsigned` (→ users, implicit), shipping_id `bigint unsigned` (→ shippings, implicit)
- sale_id `bigint` **FK→sales** (cascade)
- secret `varchar(255)`, delivary `tinyint(1)` default 1, transaction `tinyint(1)` default 1
- created_at/updated_at

**order_pays** — links a payment to a service_order
- id **PK**, payment_id, service_od_id `bigint unsigned` (→ payments/service_orders, implicit), created_at/updated_at

**billings** — individual line items within a sale/order
- id **PK**, user_id `bigint` **FK→users** (cascade), saler_id `bigint unsigned` (implicit)
- order_id `bigint` **FK→orders** (cascade), sale_id `bigint` **FK→sales** (cascade), product_id `bigint` **FK→products** (cascade, nullable)
- price, qty `varchar(255)`, color, size `varchar(255)` null
- delivary `tinyint(1)` default 1
- used_mall_id, village_product_id, retail_shop_id, wholesale_shop_id, brand_wall_id `bigint unsigned` null
- created_at/updated_at

**shippings** — delivery address snapshot per sale
- id **PK**, sale_id `bigint unsigned` null (→ sales, implicit)
- country, state, city, thana `bigint unsigned` (geo refs, implicit)
- name, address1 `varchar(255)`, address2 `varchar(255)` null, zip `int(11)`, mobile `varchar(255)`
- created_at/updated_at

**delivery_costs** — per-seller shipping fee/free-shipping threshold
- id **PK**, user_id `bigint` **FK→users** (cascade), cost `bigint(20)`, free `bigint(20)` null, created_at/updated_at

**simple_reqs** — B2B wholesale inquiry/RFQ form
- id **PK**, product_id `bigint` **FK→products** (cascade), user_id `bigint` **FK→users** (cascade)
- remark `text`, qty `bigint(20)`, email, name, company `varchar(255)`
- country_id `bigint unsigned` (implicit), state, city, road, phone `varchar(255)`
- created_at/updated_at

### Payments & Wallet

**payments** — generic payment/transaction attempt (gateway)
- id **PK**, amount `double(10,2)`, is_done `tinyint(4)` default 0
- paymentID, trxID `varchar(255)` null, created_at/updated_at

**payment_requests** — seller withdrawal requests
- id **PK**, user_id `bigint unsigned` (→ users, implicit), method, account_number `varchar(255)`
- amount `int(11)`, status `int(11)` default 0, txn_id, payment_time `varchar(255)` null
- created_at/updated_at

**balances** — buyer wallet ledger tied to a payment
- id **PK**, user_id `bigint unsigned` (implicit), amount `decimal(10,2)`, payment_id `bigint unsigned` (implicit)
- is_requested, is_refunded `tinyint(1)` default 0, created_at/updated_at

**refund_histories**
- id **PK**, balance_id `bigint unsigned` (→ balances, implicit), refundTrxID, amount `varchar(255)`, created_at/updated_at

**transactions** — user ledger entries
- id **PK**, user_id `bigint` **FK→users** (cascade), ballance `bigint(20)` (sic), number `varchar(255)`, tarnsaction `tinyint(1)` default 0 (sic), created_at/updated_at

**commissions** — platform commission accrued per order/hire
- id **PK**, user_id `bigint unsigned` (implicit), order_id `bigint unsigned` null, hire_id `bigint unsigned` null
- amount `double(10,2)`, is_done `tinyint(4)` default 0, created_at/updated_at

**commission_payments** — seller pays platform commission owed
- id **PK**, user_id `bigint unsigned` null (implicit), transaction_id `varchar(255)`, amount `double(10,2)`, verified `tinyint(1)` default 0, created_at/updated_at

**seller_payments** — payout record to seller
- id **PK/UNIQUE**, user_id `bigint unsigned` (implicit), amount `double(10,2)`, payment_id `bigint unsigned` (implicit)
- payment_method `varchar(255)` null, payment_status `varchar(255)` default 'unpaid', payment_date `datetime` null
- created_at/updated_at

### Reviews / Favourites

**reviews** — dual-purpose review for products or services
- id **PK**, user_id `bigint` **FK→users** (cascade), product_id `bigint` **FK→products** (cascade, nullable)
- service_id `bigint` **FK→services** (cascade, nullable), review, service_review `tinyint(1)` null
- content, service_content `text` null, seen `tinyint(1)` default 1
- saler_id `bigint unsigned` (implicit)
- used_mall_id, village_product_id, retail_shop_id, wholesale_shop_id, brand_wall_id, billing_id `bigint unsigned` null
- created_at/updated_at

**favourits** (sic) — wishlist, dual product/service + 5 shop formats
- id **PK**, user_id `bigint` **FK→users** (cascade), product_id `bigint` **FK→products** (cascade, nullable), service_id `bigint unsigned` null
- used_mall_id, village_product_id, retail_shop_id, wholesale_shop_id, brand_wall_id `bigint unsigned` null
- created_at/updated_at

### Messaging & Notifications

**chats** — conversation thread between buyer and seller
- id **PK**, buyer `bigint` **FK→users** (cascade), saler `bigint` **FK→users** (cascade), plus `bigint(20)` (context id, e.g. service/product id — untyped/no FK), created_at/updated_at

**messages** — individual chat message, tagged to the listing context it originated from
- id **PK**, chat_id `bigint` **FK→chats** (cascade), user_id `bigint unsigned` (sender, implicit)
- brand_wall_id, retail_shop_id, wholesale_shop_id, village_product_id, used_mall_id, product_id, service_id `bigint unsigned` null (context tag)
- seen `tinyint(1)` default 1, message `text` null, path, image `text` null
- created_at/updated_at

**notifications** — Laravel polymorphic notifications table
- id `char(36)` **PK** (UUID), type `varchar(255)`, notifiable_type `varchar(255)`, notifiable_id `bigint unsigned`
- data `text` (JSON payload), read_at `timestamp` null, created_at/updated_at

### CMS / Site Configuration

**banners** — homepage hero banners
- id **PK**, heading, small_heading, path, image `varchar(255)`, status `tinyint(1)` default 2, statusS `tinyint(1)` default 4, created_at/updated_at

**sidebar_banners** — category sidebar ad banners
- id **PK**, type `int(11)` default 0, what `int(11)`, category_id `int(11)`, path, image `varchar(255)`, created_at/updated_at

**email_contents** — templated email bodies (approve/checkout/order/review/verifycode/success)
- id **PK**, name `varchar(255)`, content `text`, created_at/updated_at

**others** — generic site settings key/value(s) (site title, logo, login banner)
- id **PK**, name `varchar(255)`, value1 `varchar(255)`, value2, value3 `varchar(255)` null, created_at/updated_at

### Analytics / System

**visitors** — page-view/IP log per user
- id **PK**, user_id `bigint` **FK→users** (cascade), ip `varchar(45)`, created_at/updated_at

**jobs** — Laravel queue jobs table
- id **PK**, queue `varchar(255)` (idx, prefix 250), payload `longtext`, attempts `tinyint(3) unsigned`, reserved_at `int(10) unsigned` null, available_at, created_at `int(10) unsigned`

**failed_jobs** — Laravel failed queue jobs
- id **PK**, connection `text`, queue `text`, payload `longtext`, exception `longtext`, failed_at `timestamp` default current_timestamp

**migrations** — Laravel migration history (102 migrations run)
- id `int(10) unsigned` **PK**, migration `varchar(255)`, batch `int(11)`

---

## 3. Explicit Foreign Key Constraints (as declared in dump)

```
bid_for_services.buyer_id      → users.id       (CASCADE)
bid_for_services.saler_id      → users.id       (CASCADE)
bid_for_services.service_id    → services.id    (CASCADE)
billings.order_id              → orders.id      (CASCADE)
billings.product_id            → products.id    (CASCADE)
billings.sale_id               → sales.id       (CASCADE)
billings.user_id               → users.id       (CASCADE)
carts.product_id               → products.id    (CASCADE)
carts.user_id                  → users.id       (CASCADE)
certificats.company_id         → companies.id   (CASCADE)
chats.buyer                    → users.id       (CASCADE)
chats.saler                    → users.id       (CASCADE)
child_categories.parent_category_id → parent_categories.id (CASCADE)
cities.state_id                → states.id
cities.country_id              → countries.id
companies.user_Id              → users.id       (CASCADE)
company_files.company_id       → companies.id   (CASCADE)
contacts.user_id               → users.id       (CASCADE)
delivery_costs.user_id         → users.id       (CASCADE)
edcations.user_id              → users.id       (CASCADE)
experiences.user_id            → users.id       (CASCADE)
favourits.product_id           → products.id    (CASCADE)
favourits.user_id              → users.id       (CASCADE)
interests.user_id              → users.id       (CASCADE)
messages.chat_id               → chats.id       (CASCADE)
orders.sale_id                 → sales.id       (CASCADE)
otp_logs.number_id             → otps.id        (CASCADE)
parent_categories.grand_category_id → grand_categories.id (CASCADE)
products.user_id               → users.id       (CASCADE)
product_images.product_id      → products.id    (CASCADE)
reviews.product_id             → products.id    (CASCADE)
reviews.service_id             → services.id    (CASCADE)
reviews.user_id                → users.id       (CASCADE)
services.user_id               → users.id       (CASCADE)
service_children.service_parent_id → service_parents.id (CASCADE)
service_images.service_id      → services.id    (CASCADE)
service_orders.buyer_id        → users.id       (CASCADE)
service_orders.saler_id        → users.id       (CASCADE)
service_orders.service_id      → services.id    (CASCADE)
service_parents.service_grand_id → service_grands.id (CASCADE)
shippings.sale_id              → sales.id       (CASCADE)
simple_reqs.product_id         → products.id    (CASCADE)
simple_reqs.user_id            → users.id       (CASCADE)
skills.user_id                 → users.id       (CASCADE)
states.country_id              → countries.id
transactions.user_id           → users.id       (CASCADE)
user_infos.user_id             → users.id       (CASCADE)
visitors.user_id               → users.id       (CASCADE)
```

## 4. Implicit Relations (naming-convention only — no DB-level FK constraint)

These `_id` columns are clearly relational by name/usage but the dump has **no enforced constraint** (common when a table uses `MyISAM` engine, which cannot hold FKs, or when the migration simply omitted `foreign()`):

- `*.saler_id` → `users.id` on billings, carts, hireds, order relationships etc.
- `*.grand_category_id / parent_category_id / child_category_id` → `grand_categories/parent_categories/child_categories` on brand_walls, products, retail_shops, used_malls, village_products, wholesales
- `*.grand_id / parent_id / child_id` → `service_grands/service_parents/service_children` on `services`
- `*.country_id / state_id / city_id` → `countries/states/cities` across `products`, `services`, `user_infos`, `shippings`, `simple_reqs`, `brand_walls`, etc.
- `*.measurement_id` → `measurements.id` on used_malls, village_products, retail_shops, wholesales, brand_walls
- `*.used_mall_id / village_product_id / retail_shop_id / wholesale_shop_id / brand_wall_id` → the respective shop table, used as a **mutually-exclusive polymorphic reference** across `carts`, `billings`, `favourits`, `messages`, `product_images`, `reviews`
- `orders.saler_id`, `orders.shipping_id` → `users.id`, `shippings.id`
- `order_pays.payment_id/service_od_id` → `payments.id`, `service_orders.id`
- `hire_pays.payment_id/hire_od_id` → `payments.id`, `hireds.id`
- `hireds.service_id/buyer_id/saler_id/bid_id` → `services.id`, `users.id` (×2), `bid_for_services.id`
- `balances.user_id/payment_id`, `refund_histories.balance_id`, `commissions.order_id/hire_id`, `seller_payments.user_id/payment_id`, `payment_requests.user_id`, `commission_payments.user_id` → users/payments/orders/hireds/balances

### ⚠️ Notable data-model observation: the "thana_id" columns

Columns named `thana_id` (Bangla for police-station/sub-district, used across `services`, `products`-family tables, `shippings`, `user_infos`) hold values in the **4,600–5,246 range**, which matches the `unions` table's id space (`AUTO_INCREMENT=5246`), **not** the `upazilas` table (`AUTO_INCREMENT=560`). In other words, despite the column name suggesting "upazila/thana", the application actually stores a `unions.id` there. `upazilas` appears to be a legacy/unused duplicate of the same Bangladesh sub-district data. Worth confirming with the current codebase (model relationships) before relying on this for reporting.

## 5. Engine / Charset Notes

- Most tables: `InnoDB`, `utf8mb4_unicode_ci` (supports FKs, emoji/Bengali text).
- A subset use `MyISAM` (no FK support, why many relations above are constraint-less): `balances`, `banners`(actually InnoDB, verify), `brand_walls`, `documents`, `jobs`, `measurements`, `notifications`, `payment_requests`, `refund_histories`, `retail_shops`, `seller_payments`, `sidebar_banners`, `used_malls`, `village_products`, `wholesales`.
- A couple of tables use `latin1_swedish_ci` (legacy default, pre-dates unicode migration): `balances`, `refund_histories`, `seller_payments`.
- `products`, `brand_walls`, `used_malls`, `village_products`, `retail_shops`, `wholesales` all support **soft deletes** (`deleted_at`).
