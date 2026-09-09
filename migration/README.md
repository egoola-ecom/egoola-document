# Egoola DB Migration

Standalone tool — moves data from the legacy Egoola schema into the redesigned
32-table schema. Not part of the `webapp` Laravel app: it has its own
`composer.json` and runs with plain `php migrate.php`, not `artisan`.

For the full architecture, module-by-module mapping reference, flagged
assumptions, and a post-migration checklist, see
[Egoola_Database_Migration_Guide.docx](Egoola_Database_Migration_Guide.docx)
in this folder. This file only covers what's different now that the tool
lives on its own.

## Setup

```bash
composer install
cp .env.example .env
# edit .env: OLD_DB_* (existing live database, read-only) and
# NEW_DB_* (an empty database for the redesigned schema)
```

## Run

```bash
php migrate.php --fresh
```

`--fresh` creates all 32 tables in the new database from
`database/new_schema/new_schema.sql` before migrating. Options:

| Option | Default | Purpose |
|---|---|---|
| `--fresh` | off | Drop and recreate the new schema first |
| `--only=<step>` | all steps | Run only the named step(s), repeatable |
| `--chunk=<n>` | 500 | Rows read per batch from the old database |

Step keys for `--only`: `geography`, `identity`, `sellerinfos`, `categories`,
`measurements`, `listings`, `listingmedia`, `bids`, `carts`, `orders`,
`legacypayments`, `quotes`, `messaging`, `reviews`, `notifications`, `cms` —
run in that order, since later steps depend on ID maps built by earlier ones.

## Layout

```
migration/
  composer.json
  migrate.php                            entry point
  src/Migrator.php                       all migration logic
  database/new_schema/new_schema.sql     DDL for the new schema
  Egoola_Database_Migration_Guide.docx   full written guide
```
