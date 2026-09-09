<?php

namespace Egoola\Migration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Str;

/**
 * Copies data from the legacy Egoola schema (connection: "old") into the
 * redesigned 32-table schema (connection: "new"). Both connections are wired
 * up by migrate.php before this class is constructed — see that file for the
 * .env variables (OLD_DB_* / NEW_DB_*) that configure them.
 *
 *   php migrate.php --fresh
 *   php migrate.php --only=geography --only=identity
 *
 * Every mapping decision below follows the module notes documented in the
 * DB-redesign proposal (scratchpad/egoola_review/schema_data.js). Where the
 * legacy schema is genuinely ambiguous (status codes with no comment, two
 * parallel withdrawal tables, free-text country names, etc.) the assumption
 * made is called out in a comment right above the code that makes it — spot
 * check those against a real data sample before trusting this in production.
 *
 * Design choices that apply everywhere:
 *  - Every migrated row is tagged creator_type/updater_type = 'system',
 *    creator_name/updater_name = 'Legacy Data Migration'. The legacy schema
 *    never recorded a per-row actor, so inventing one would be fiction;
 *    original created_at/updated_at timestamps ARE preserved.
 *  - OTP codes, password-reset tokens and "remember me" tokens are never
 *    migrated (verify_phone, otps, otp_logs, forget_otps, password_resets,
 *    verifies) — carrying live secrets/tokens into a new system is both a
 *    security smell and pointless, since they are all short-lived by design.
 *  - IDs are NOT preserved 1:1 (six legacy tables collapse into one
 *    `listings` table, three into one `categories` table, etc.), so every
 *    relationship is re-pointed through an in-memory old-id -> new-id map
 *    built up as each table is migrated, in dependency order.
 */
class Migrator
{
    /** @var string */
    protected $old = 'old';

    /** @var string */
    protected $new = 'new';

    /** @var int */
    protected $chunkSize = 500;

    /** @var string absolute path to database/new_schema/new_schema.sql */
    protected $schemaPath;

    /**
     * old-id -> new-id maps, namespaced by concept.
     * e.g. $map['seller'][14] = 201, $map['listing']['used_malls'][7] = 3005
     */
    protected $map = [
        'country' => [], 'state' => [], 'city' => [], 'thana' => [],
        'admin' => [], 'seller' => [], 'user' => [],
        'category_product' => [], 'category_service' => [],
        'measurement' => [],
        'listing' => [
            'products' => [], 'used_malls' => [], 'village_products' => [],
            'retail_shops' => [], 'wholesales' => [], 'brand_walls' => [],
            'services' => [],
        ],
        'bid' => [],
        'order' => [
            'orders' => [], 'service_orders' => [], 'hireds' => [],
        ],
        'cart' => [], // buyer_id -> new cart id
    ];

    public function __construct($schemaPath)
    {
        $this->schemaPath = $schemaPath;
    }

    /**
     * @param array $options ['fresh' => bool, 'only' => string[], 'chunk' => int]
     */
    public function run(array $options)
    {
        $this->chunkSize = (int) (isset($options['chunk']) ? $options['chunk'] : 500);

        if (!empty($options['fresh'])) {
            $this->applySchema();
        }

        $steps = [
            'geography'     => 'migrateGeography',
            'identity'      => 'migrateIdentity',
            'sellerinfos'   => 'migrateSellerInfos',
            'categories'    => 'migrateCategories',
            'measurements'  => 'migrateMeasurements',
            'listings'      => 'migrateListings',
            'listingmedia'  => 'migrateListingMedia',
            'bids'          => 'migrateBids',
            'carts'         => 'migrateCarts',
            'orders'        => 'migrateOrders',
            'legacypayments'=> 'migrateStandalonePayments',
            'quotes'        => 'migrateQuoteRequests',
            'messaging'     => 'migrateMessaging',
            'reviews'       => 'migrateReviewsAndFavourites',
            'notifications' => 'migrateNotifications',
            'cms'           => 'migrateCms',
        ];

        $only = isset($options['only']) ? $options['only'] : [];

        foreach ($steps as $key => $method) {
            if (!empty($only) && !in_array($key, $only, true)) {
                continue;
            }
            echo "==> {$key}\n";
            $this->{$method}();
        }

        echo "Migration finished.\n";
    }

    // -------------------------------------------------------------------
    // Schema bootstrap
    // -------------------------------------------------------------------

    protected function applySchema()
    {
        echo "Dropping and recreating every table in the new database...\n";
        $sql = file_get_contents($this->schemaPath);

        // Naive but safe statement split: our DDL never uses ";" inside a
        // string/value, only as a statement terminator.
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));

        Capsule::connection($this->new)->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $statement) {
            // Strip full-line SQL comments so a statement is never skipped just
            // because a section-header comment happened to land in the same
            // chunk as the real DROP/CREATE TABLE line after the naive split.
            $statement = trim(preg_replace('/^--.*$/m', '', $statement));
            if ($statement === '') {
                continue;
            }
            Capsule::connection($this->new)->statement($statement);
        }
        Capsule::connection($this->new)->statement('SET FOREIGN_KEY_CHECKS=1');
        echo "Schema applied.\n";
    }

    // -------------------------------------------------------------------
    // Small helpers
    // -------------------------------------------------------------------

    protected function oldHasColumn($table, $column)
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            $cache[$table] = Capsule::connection($this->old)->getSchemaBuilder()->getColumnListing($table);
        }
        return in_array($column, $cache[$table], true);
    }

    protected function val($row, $key, $default = null)
    {
        return (isset($row->{$key}) && $row->{$key} !== '') ? $row->{$key} : $default;
    }

    protected function systemAudit($createdAt = null, $updatedAt = null)
    {
        $now = \Carbon\Carbon::now();
        return [
            'created_by' => null,
            'creator_type' => 'system',
            'creator_name' => 'Legacy Data Migration',
            'updated_by' => null,
            'updater_type' => 'system',
            'updater_name' => 'Legacy Data Migration',
            'created_at' => $createdAt ?: $now,
            'updated_at' => $updatedAt ?: $createdAt ?: $now,
        ];
    }

    protected function uniqueSlug(&$used, $base, $fallbackId)
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'item-' . $fallbackId;
        }
        $candidate = $slug;
        $i = 2;
        while (isset($used[$candidate])) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        $used[$candidate] = true;
        return $candidate;
    }

    protected function chunk($table, $callback, $connection = null)
    {
        Capsule::connection($connection ?: $this->old)
            ->table($table)
            ->orderBy('id')
            ->chunk($this->chunkSize, $callback);
    }

    /**
     * Legacy tables (products, used_malls, ..., favourits, carts, billings,
     * messages, reviews) all carry the same "which listing is this row
     * about" pattern: one of product_id / service_id / used_mall_id /
     * village_product_id / retail_shop_id / wholesale_shop_id /
     * brand_wall_id is set, the rest are null. Resolve it to a new
     * listings.id via the per-source-table map built during migrateListings().
     */
    protected function resolveListingId($row)
    {
        $pairs = [
            'product_id' => 'products',
            'service_id' => 'services',
            'used_mall_id' => 'used_malls',
            'village_product_id' => 'village_products',
            'retail_shop_id' => 'retail_shops',
            'wholesale_shop_id' => 'wholesale_shops', // note: column name says wholesale_shop_id but source table is `wholesales`
            'brand_wall_id' => 'brand_walls',
        ];
        foreach ($pairs as $column => $sourceTable) {
            if (isset($row->{$column}) && $row->{$column} !== null) {
                $source = $sourceTable === 'wholesale_shops' ? 'wholesales' : $sourceTable;
                if (isset($this->map['listing'][$source][$row->{$column}])) {
                    return $this->map['listing'][$source][$row->{$column}];
                }
            }
        }
        return null;
    }

    // -------------------------------------------------------------------
    // 1. Geography
    //
    // countries/states/cities are NOT defined by any migration in this repo
    // (no create_countries/states/cities_table.php exists) — they were
    // seeded directly into the DB, most likely from the common open
    // "countries-states-cities" dataset (Country::findOrFail(19)->phonecode
    // is used in RegisterController, confirming a `phonecode` column).
    // Column reads below are therefore defensive: every value is pulled via
    // oldHasColumn()/val() so this step degrades gracefully instead of
    // fatal-erroring if the live columns differ slightly from the guess.
    // `unions` is the real, live thana-level table (every thana_id column
    // in the app points at it); `upazilas` is a confirmed-dormant duplicate
    // per the redesign audit and is intentionally NOT migrated.
    // -------------------------------------------------------------------

    protected function migrateGeography()
    {
        $usedSlugs = [];

        $this->chunk('countries', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $name = $this->val($row, 'name', 'Unknown');
                $phonecode = $this->val($row, 'phonecode');
                $id = Capsule::connection($this->new)->table('countries')->insertGetId(array_merge([
                    'name' => $name,
                    'slug' => $this->uniqueSlug($usedSlugs, $name, $row->id),
                    'country_code' => $phonecode !== null ? ('+' . ltrim($phonecode, '+')) : 'N/A',
                    'flag_path' => $this->val($row, 'flag') ?: $this->val($row, 'emoji'),
                    'flag_url' => null,
                ], $this->systemAudit($this->val($row, 'created_at'), $this->val($row, 'updated_at'))));
                $this->map['country'][$row->id] = $id;
            }
        });

        $this->chunk('states', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $countryId = isset($this->map['country'][$row->country_id]) ? $this->map['country'][$row->country_id] : null;
                if ($countryId === null) {
                    return; // orphaned state with no known country — skip rather than violate the FK
                }
                $name = $this->val($row, 'name', 'Unknown');
                $id = Capsule::connection($this->new)->table('states')->insertGetId(array_merge([
                    'country_id' => $countryId,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($usedSlugs, $name, $row->id),
                    'state_code' => $this->val($row, 'state_code', strtoupper(substr($name, 0, 3))),
                    'flag_path' => null,
                    'flag_url' => null,
                ], $this->systemAudit($this->val($row, 'created_at'), $this->val($row, 'updated_at'))));
                $this->map['state'][$row->id] = $id;
            }
        });

        $this->chunk('cities', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $stateId = isset($this->map['state'][$row->state_id]) ? $this->map['state'][$row->state_id] : null;
                if ($stateId === null) {
                    return;
                }
                $countryId = $this->oldHasColumn('cities', 'country_id') && isset($this->map['country'][$row->country_id])
                    ? $this->map['country'][$row->country_id]
                    : $this->newCountryIdForState($stateId);
                $name = $this->val($row, 'name', 'Unknown');
                $id = Capsule::connection($this->new)->table('cities')->insertGetId(array_merge([
                    'country_id' => $countryId,
                    'state_id' => $stateId,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($usedSlugs, $name, $row->id),
                    'city_code' => $this->val($row, 'city_code', strtoupper(substr($name, 0, 3))),
                    'flag_path' => null,
                    'flag_url' => null,
                ], $this->systemAudit($this->val($row, 'created_at'), $this->val($row, 'updated_at'))));
                $this->map['city'][$row->id] = $id;
            }
        });

        // `unions` -> `thanas` (the real, live table). fillable = ['name', 'cities_id'].
        $this->chunk('unions', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $oldCityId = $this->val($row, 'cities_id');
                $cityId = $oldCityId !== null && isset($this->map['city'][$oldCityId]) ? $this->map['city'][$oldCityId] : null;
                if ($cityId === null) {
                    return;
                }
                list($stateId, $countryId) = $this->newStateCountryForCity($cityId);
                $name = $this->val($row, 'name', 'Unknown');
                $id = Capsule::connection($this->new)->table('thanas')->insertGetId(array_merge([
                    'country_id' => $countryId,
                    'state_id' => $stateId,
                    'city_id' => $cityId,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($usedSlugs, $name, $row->id),
                    'thana_code' => strtoupper(substr($name, 0, 3)),
                    'flag_path' => null,
                    'flag_url' => null,
                ], $this->systemAudit($this->val($row, 'created_at'), $this->val($row, 'updated_at'))));
                $this->map['thana'][$row->id] = $id;
            }
        });
    }

    protected function newCountryIdForState($newStateId)
    {
        $row = Capsule::connection($this->new)->table('states')->where('id', $newStateId)->first();
        return $row ? $row->country_id : null;
    }

    protected function newStateCountryForCity($newCityId)
    {
        $row = Capsule::connection($this->new)->table('cities')->where('id', $newCityId)->first();
        return $row ? [$row->state_id, $row->country_id] : [null, null];
    }

    protected function findCountryIdByName($name)
    {
        if (!$name) {
            return null;
        }
        $row = Capsule::connection($this->new)->table('countries')->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
        return $row ? $row->id : null;
    }

    // -------------------------------------------------------------------
    // 2. Identity — split the legacy `users` (role column) into
    //    admins / sellers / users(buyers), per the confirmed rule:
    //      users.role == 5           -> admins
    //      user_infos.section == 1   -> sellers
    //      user_infos.section == 0 (or missing) -> users (buyers)
    //    (role==5 check: app/Http/Middleware/Admin.php; section check:
    //    app/Http/Middleware/CheckBuyer.php and >20 controller call sites.)
    // -------------------------------------------------------------------

    protected function migrateIdentity()
    {
        $this->chunk('users', function ($rows) {
            foreach ($rows as $row) {
                $info = Capsule::connection($this->old)->table('user_infos')->where('user_id', $row->id)->first();
                $skills = $this->jsonFromChild('skills', 'skill', $row->id);
                $interests = $this->jsonFromChild('interests', 'interest', $row->id);
                $educations = $this->educationJson($row->id);
                $experiences = $this->experienceJson($row->id);

                $countryId = $info ? (isset($this->map['country'][$info->country_id]) ? $this->map['country'][$info->country_id] : null) : null;
                $stateId = $info ? (isset($this->map['state'][$info->state_id]) ? $this->map['state'][$info->state_id] : null) : null;
                $cityId = $info ? (isset($this->map['city'][$info->city_id]) ? $this->map['city'][$info->city_id] : null) : null;
                $thanaId = ($info && $this->oldHasColumn('user_infos', 'thana_id') && $info->thana_id)
                    ? (isset($this->map['thana'][$info->thana_id]) ? $this->map['thana'][$info->thana_id] : null)
                    : null;

                $audit = $this->systemAudit($row->created_at, $row->updated_at);

                if ((int) $row->role === 5) {
                    $id = Capsule::connection($this->new)->table('admins')->insertGetId(array_merge([
                        'name' => $row->name,
                        'email' => $row->email,
                        'mobile' => $info ? $this->val($info, 'telnumber', '+000000000000') : '+000000000000',
                        'password' => $row->password ?: '',
                        'profile_pic_path' => $info ? $this->joinPath($this->val($info, 'path'), $this->val($info, 'image')) : null,
                        'profile_pic_url' => null,
                        'type' => 'admin',
                        'status' => 'active',
                        'skills' => $skills,
                        'experiences' => $experiences,
                        'interests' => $interests,
                        'educations' => $educations,
                        'present_address' => $info ? $this->val($info, 'address') : null,
                        'permanent_address' => null,
                        'country_id' => $countryId,
                        'state_id' => $stateId,
                        'city_id' => $cityId,
                        'thana_id' => $thanaId,
                        'last_logged_at' => null,
                    ], $audit));
                    $this->map['admin'][$row->id] = $id;
                    continue;
                }

                $isSeller = $info && (int) $this->val($info, 'section', 0) === 1;

                if ($isSeller) {
                    $id = Capsule::connection($this->new)->table('sellers')->insertGetId(array_merge([
                        'name' => $row->name,
                        'email' => $row->email,
                        'phone' => $info ? $this->val($info, 'telnumber', '+0000000000') : '+0000000000',
                        'password' => $row->password ?: '',
                        'profile_pic_path' => $info ? $this->joinPath($this->val($info, 'path'), $this->val($info, 'image')) : null,
                        'profile_pic_url' => null,
                        'email_verified_at' => $row->email_verified_at,
                        'phone_verified_at' => $row->email_verified_at, // legacy has no separate phone-verified timestamp
                        'email_otp_code' => null,
                        'email_otp_expires_at' => null,
                        'phone_otp_code' => null,
                        'phone_otp_expires_at' => null,
                        'phone_otp_attempts' => 0,
                        'password_reset_token' => null,
                        'password_reset_expires_at' => null,
                        'remember_token' => null,
                        // review: 0 = pending, 1 = approved, 2 = disapproved
                        // (checkDisapprove/UnderReview middleware; ProfileController.php:85)
                        'verification_status' => $this->sellerVerificationStatus($info),
                        'verification_note' => $info ? $this->val($info, 'reason') : null,
                        'skills' => $skills,
                        'education' => $educations,
                        'experience' => $experiences,
                        'interest' => $interests,
                        'present_address' => $info ? $this->val($info, 'address') : null,
                        'permanent_address' => null,
                        'country_id' => $countryId,
                        'state_id' => $stateId,
                        'city_id' => $cityId,
                        'thana_id' => $thanaId,
                        'wallet_balance' => $info ? $this->val($info, 'ballance', 0) : 0,
                        'bonus_balance' => $info ? $this->val($info, 'bonous', 0) : 0,
                        'status' => 'active',
                        'last_logged_at' => null,
                    ], $audit));
                    $this->map['seller'][$row->id] = $id;
                } else {
                    $id = Capsule::connection($this->new)->table('users')->insertGetId(array_merge([
                        'name' => $row->name,
                        'email' => $row->email,
                        'phone' => $info ? $this->val($info, 'telnumber', '+0000000000') : '+0000000000',
                        'password' => $row->password ?: '',
                        'profile_pic_path' => $info ? $this->joinPath($this->val($info, 'path'), $this->val($info, 'image')) : null,
                        'profile_pic_url' => null,
                        'description' => null,
                        'skills' => $skills,
                        'experiences' => $experiences,
                        'interests' => $interests,
                        'educations' => $educations,
                        'email_verified_at' => $row->email_verified_at,
                        'phone_verified_at' => $row->email_verified_at,
                        'email_otp_code' => null,
                        'email_otp_expires_at' => null,
                        'phone_otp_code' => null,
                        'phone_otp_expires_at' => null,
                        'phone_otp_attempts' => 0,
                        'password_reset_token' => null,
                        'password_reset_expires_at' => null,
                        'remember_token' => null,
                        'address_line' => $info ? $this->val($info, 'address') : null,
                        'country_id' => $countryId,
                        'state_id' => $stateId,
                        'city_id' => $cityId,
                        'thana_id' => $thanaId,
                        'wallet_balance' => $info ? $this->val($info, 'ballance', 0) : 0,
                        'status' => 'active',
                    ], $audit));
                    $this->map['user'][$row->id] = $id;
                }
            }
        });
    }

    protected function sellerVerificationStatus($info)
    {
        if (!$info || !$this->oldHasColumn('user_infos', 'review')) {
            return 'unverified';
        }
        $review = (int) $this->val($info, 'review', 0);
        if ($review === 1) return 'verified';
        if ($review === 2) return 'rejected';
        return 'pending';
    }

    protected function joinPath($path, $file)
    {
        if (!$path && !$file) return null;
        return trim(($path ?: '') . '/' . ($file ?: ''), '/');
    }

    protected function jsonFromChild($table, $column, $userId)
    {
        if (!Capsule::connection($this->old)->getSchemaBuilder()->hasTable($table)) {
            return null;
        }
        $row = Capsule::connection($this->old)->table($table)->where('user_id', $userId)->first();
        return $row ? $this->val($row, $column) : null; // legacy column is already JSON text
    }

    protected function educationJson($userId)
    {
        $row = Capsule::connection($this->old)->table('edcations')->where('user_id', $userId)->first();
        if (!$row) return null;
        return json_encode([
            'degree' => json_decode($this->val($row, 'degree', 'null')),
            'institute_name' => json_decode($this->val($row, 'institute_name', 'null')),
            'start_year' => json_decode($this->val($row, 'start_year', 'null')),
            'end_year' => json_decode($this->val($row, 'end_year', 'null')),
        ]);
    }

    protected function experienceJson($userId)
    {
        $row = Capsule::connection($this->old)->table('experiences')->where('user_id', $userId)->first();
        if (!$row) return null;
        return json_encode([
            'designation' => json_decode($this->val($row, 'designation', 'null')),
            'company_name' => json_decode($this->val($row, 'company_name', 'null')),
            'start_year' => json_decode($this->val($row, 'start_year', 'null')),
            'end_year' => json_decode($this->val($row, 'end_year', 'null')),
        ]);
    }

    // -------------------------------------------------------------------
    // 3. Seller business profile: companies (+ contacts) -> seller_infos
    // -------------------------------------------------------------------

    protected function migrateSellerInfos()
    {
        $this->chunk('companies', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_Id]) ? $this->map['seller'][$row->user_Id] : null;
                if ($sellerId === null) {
                    return; // company row belongs to a users.id that wasn't classified as a seller
                }
                $contact = Capsule::connection($this->old)->table('contacts')->where('user_id', $row->user_Id)->first();
                $seller = Capsule::connection($this->new)->table('sellers')->where('id', $sellerId)->first();

                Capsule::connection($this->new)->table('seller_infos')->insert(array_merge([
                    'seller_id' => $sellerId,
                    'business_type' => $this->val($row, 'btype', 'General'),
                    'main_product' => $this->val($row, 'mainproduct', 'N/A'),
                    'owner_name' => $this->val($row, 'owner', $seller ? $seller->name : 'N/A'),
                    'employees_range' => $this->val($row, 'employes', 'N/A'),
                    'annual_revenue' => $this->val($row, 'ravenue', 'N/A'),
                    'established_year' => $this->val($row, 'establish', 'N/A'),
                    'description' => $this->val($row, 'info'),
                    'public_email' => $contact ? $this->val($contact, 'email') : null,
                    'whatsapp' => $contact ? $this->val($contact, 'whatsapp') : null,
                    'facebook' => $contact ? $this->val($contact, 'facebook') : null,
                    'wechat' => $contact ? $this->val($contact, 'wechat') : null,
                    'skype' => $contact ? $this->val($contact, 'skype') : null,
                    // companies.country is free text, not a real FK in the legacy schema — best-effort name match only.
                    'country_id' => $this->findCountryIdByName($this->val($row, 'country')) ?: ($seller ? $seller->country_id : null),
                    'state_id' => $seller ? $seller->state_id : null,
                    'city_id' => $seller ? $seller->city_id : null,
                    'thana_id' => $seller ? $seller->thana_id : null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));

                // company_files (gallery) + certificats -> seller_media
                Capsule::connection($this->old)->table('company_files')->where('company_id', $row->id)
                    ->orderBy('id')->chunk($this->chunkSize, function ($files) use ($sellerId) {
                        foreach ($files as $f) {
                            Capsule::connection($this->new)->table('seller_media')->insert(array_merge([
                                'seller_id' => $sellerId,
                                'media_type' => 'image',
                                'media_for' => 'gallery_image',
                                'media_path' => $this->joinPath($this->val($f, 'path'), $this->val($f, 'name')),
                                'media_url' => null,
                            ], $this->systemAudit($f->created_at, $f->updated_at)));
                        }
                    });

                Capsule::connection($this->old)->table('certificats')->where('company_id', $row->id)
                    ->orderBy('id')->chunk($this->chunkSize, function ($certs) use ($sellerId) {
                        foreach ($certs as $c) {
                            Capsule::connection($this->new)->table('seller_media')->insert(array_merge([
                                'seller_id' => $sellerId,
                                'media_type' => 'image',
                                'media_for' => 'certificate',
                                'media_path' => $this->joinPath($this->val($c, 'path'), $this->val($c, 'image')),
                                'media_url' => null,
                            ], $this->systemAudit($c->created_at, $c->updated_at)));
                        }
                    });
            }
        });
    }

    // -------------------------------------------------------------------
    // 4. Categories: two 3-level legacy trees -> one self-referencing tree
    // -------------------------------------------------------------------

    protected function migrateCategories()
    {
        $usedSlugs = [];

        $this->chunk('grand_categories', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => null,
                    'type' => 'product',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'gc' . $row->id),
                    'image_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_product'][$row->id] = $id;
            }
        });

        $this->chunk('parent_categories', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $parentId = isset($this->map['category_product'][$row->grand_category_id]) ? $this->map['category_product'][$row->grand_category_id] : null;
                if ($parentId === null) return;
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => $parentId,
                    'type' => 'product',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'pc' . $row->id),
                    'image_path' => null,
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_product'][$row->id] = $id;
            }
        });

        $this->chunk('child_categories', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $parentId = isset($this->map['category_product'][$row->parent_category_id]) ? $this->map['category_product'][$row->parent_category_id] : null;
                if ($parentId === null) return;
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => $parentId,
                    'type' => 'product',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'cc' . $row->id),
                    'image_path' => null,
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_product'][$row->id] = $id;
            }
        });

        // NOTE: the same three-table pattern maps grand_category_id -> the
        // TOP-level id, but products only reliably store the LEAF
        // (child_category_id). $map['category_product'] therefore only
        // needs to resolve leaf ids for listing migration, which it does.

        $this->chunk('service_grands', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => null,
                    'type' => 'service',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'sg' . $row->id),
                    'image_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_service'][$row->id] = $id;
            }
        });

        $this->chunk('service_parents', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $parentId = isset($this->map['category_service'][$row->service_grand_id]) ? $this->map['category_service'][$row->service_grand_id] : null;
                if ($parentId === null) return;
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => $parentId,
                    'type' => 'service',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'sp' . $row->id),
                    'image_path' => null,
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_service'][$row->id] = $id;
            }
        });

        $this->chunk('service_children', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $parentId = isset($this->map['category_service'][$row->service_parent_id]) ? $this->map['category_service'][$row->service_parent_id] : null;
                if ($parentId === null) return;
                $id = Capsule::connection($this->new)->table('categories')->insertGetId(array_merge([
                    'parent_id' => $parentId,
                    'type' => 'service',
                    'name' => $row->name,
                    'name_bn' => null,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'sc' . $row->id),
                    'image_path' => null,
                    'image_url' => null,
                    'sort_order' => $row->id,
                    'status' => 'active',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['category_service'][$row->id] = $id;
            }
        });
    }

    // -------------------------------------------------------------------
    // 5. Measurements (unchanged 1:1 copy)
    // -------------------------------------------------------------------

    protected function migrateMeasurements()
    {
        $this->chunk('measurements', function ($rows) {
            foreach ($rows as $row) {
                $id = Capsule::connection($this->new)->table('measurements')->insertGetId(array_merge([
                    'name' => $row->name,
                    'symbol' => $row->symbol,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['measurement'][$row->id] = $id;
            }
        });
    }

    // -------------------------------------------------------------------
    // 6. Catalog / listings — six product-format tables + services all
    //    collapse into one `listings` table.
    // -------------------------------------------------------------------

    protected function migrateListings()
    {
        $usedSlugs = [];
        $this->migrateProductsTable($usedSlugs);
        $this->migrateUsedMalls($usedSlugs);
        $this->migrateSimpleShopTable('village_products', 'village', $usedSlugs);
        $this->migrateSimpleShopTable('retail_shops', 'retail', $usedSlugs);
        $this->migrateWholesales($usedSlugs);
        $this->migrateBrandWalls($usedSlugs);
        $this->migrateServices($usedSlugs);
    }

    protected function geoForRow($row)
    {
        $countryId = isset($row->country_id) && isset($this->map['country'][$row->country_id]) ? $this->map['country'][$row->country_id] : null;
        $cityId = isset($row->city_id) && isset($this->map['city'][$row->city_id]) ? $this->map['city'][$row->city_id] : null;
        $stateId = isset($row->state_id) && isset($this->map['state'][$row->state_id])
            ? $this->map['state'][$row->state_id]
            : ($cityId ? $this->newStateCountryForCity($cityId)[0] : null);
        $thanaId = isset($row->thana_id) && isset($this->map['thana'][$row->thana_id]) ? $this->map['thana'][$row->thana_id] : null;
        return [$countryId, $stateId, $cityId, $thanaId];
    }

    protected function migrateProductsTable(&$usedSlugs)
    {
        $this->chunk('products', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $categoryId = isset($this->map['category_product'][$row->child_category_id]) ? $this->map['category_product'][$row->child_category_id] : null;
                if ($categoryId === null) return;
                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);

                $attributes = array_filter([
                    'import' => $this->val($row, 'import'),
                    'use' => $this->val($row, 'use'),
                    'type' => $this->val($row, 'type'),
                    'certificate' => $this->val($row, 'certificate'),
                    'capacity' => $this->oldHasColumn('products', 'capacity') ? $this->val($row, 'capacity') : null,
                    'alreadySoldQty' => $this->val($row, 'alrqty'),
                ], function ($v) { return $v !== null; });

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => null,
                    'category_id' => $categoryId,
                    'catalog_type' => 'product',
                    'listing_type' => 'general',
                    'posted_by_role' => null,
                    'title' => $row->title,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->title, 'p' . $row->id),
                    'description' => (string) $this->val($row, 'other', ''),
                    'brand' => $this->val($row, 'brand'),
                    'model' => $this->val($row, 'model'),
                    'color' => $this->val($row, 'color'),
                    'origin' => $this->val($row, 'origin'),
                    'warranty' => null,
                    'price' => $this->val($row, 'price', 0),
                    'old_price' => $this->val($row, 'oldPrice'),
                    'currency' => 'BDT',
                    'min_order_qty' => $this->val($row, 'min', 1),
                    'stock_qty' => $this->val($row, 'qty', 0),
                    'measurement_id' => null,
                    'colors' => $this->val($row, 'color') ? json_encode([$row->color]) : null,
                    'sizes' => null,
                    'reserved_qty' => $this->val($row, 'alrqty', 0),
                    'condition_note' => null,
                    'attributes' => empty($attributes) ? null : json_encode($attributes),
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    // legacy `approv` defaults to 1 (approved) — see redesign notes, this is a faithful copy, not a bug introduced here.
                    'status' => ((int) $this->val($row, 'approv', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing']['products'][$row->id] = $id;
            }
        });
    }

    protected function migrateUsedMalls(&$usedSlugs)
    {
        $this->chunk('used_malls', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $categoryId = isset($this->map['category_product'][$row->child_category_id]) ? $this->map['category_product'][$row->child_category_id] : null;
                if ($categoryId === null) return;
                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);

                $conditionParts = array_filter([
                    $this->val($row, 'use_year') ? $row->use_year . 'y' : null,
                    $this->val($row, 'use_month') ? $row->use_month . 'm' : null,
                    $this->val($row, 'use_day') ? $row->use_day . 'd' : null,
                ]);
                $condition = empty($conditionParts) ? null : ('Used for ' . implode(' ', $conditionParts));
                if ($this->val($row, 'purchase_date')) {
                    $condition = trim(($condition ? $condition . ', ' : '') . 'purchased ' . $row->purchase_date);
                }

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => null,
                    'category_id' => $categoryId,
                    'catalog_type' => 'product',
                    'listing_type' => 'used',
                    'posted_by_role' => null,
                    'title' => $row->name,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'um' . $row->id),
                    'description' => (string) $this->val($row, 'other', ''),
                    'brand' => $this->val($row, 'brand'),
                    'model' => $this->val($row, 'model'),
                    'color' => $this->val($row, 'color'),
                    'origin' => null,
                    'warranty' => null,
                    'price' => $this->val($row, 'price', 0),
                    'old_price' => null,
                    'currency' => 'BDT',
                    'min_order_qty' => 1,
                    'stock_qty' => $this->val($row, 'quantity', 0),
                    'measurement_id' => isset($this->map['measurement'][$row->measurement_id]) ? $this->map['measurement'][$row->measurement_id] : null,
                    'colors' => $this->val($row, 'color') ? json_encode([$row->color]) : null,
                    'sizes' => null,
                    'reserved_qty' => null,
                    'condition_note' => $condition,
                    'attributes' => null,
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    'status' => ((int) $this->val($row, 'approve', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing']['used_malls'][$row->id] = $id;
            }
        });
    }

    protected function migrateSimpleShopTable($table, $listingType, &$usedSlugs)
    {
        $this->chunk($table, function ($rows) use (&$usedSlugs, $table, $listingType) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $categoryId = isset($this->map['category_product'][$row->child_category_id]) ? $this->map['category_product'][$row->child_category_id] : null;
                if ($categoryId === null) return;
                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => null,
                    'category_id' => $categoryId,
                    'catalog_type' => 'product',
                    'listing_type' => $listingType,
                    'posted_by_role' => null,
                    'title' => $row->name,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, substr($table, 0, 2) . $row->id),
                    'description' => (string) $this->val($row, 'other', ''),
                    'brand' => null, 'model' => null, 'color' => null, 'origin' => null, 'warranty' => null,
                    'price' => $this->val($row, 'price', 0),
                    'old_price' => null,
                    'currency' => 'BDT',
                    'min_order_qty' => 1,
                    'stock_qty' => $this->val($row, 'quantity', 0),
                    'measurement_id' => isset($this->map['measurement'][$row->measurement_id]) ? $this->map['measurement'][$row->measurement_id] : null,
                    'colors' => null, 'sizes' => null, 'reserved_qty' => null, 'condition_note' => null, 'attributes' => null,
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    'status' => ((int) $this->val($row, 'approve', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing'][$table][$row->id] = $id;
            }
        });
    }

    protected function migrateWholesales(&$usedSlugs)
    {
        $this->chunk('wholesales', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $categoryId = isset($this->map['category_product'][$row->child_category_id]) ? $this->map['category_product'][$row->child_category_id] : null;
                if ($categoryId === null) return;
                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => null,
                    'category_id' => $categoryId,
                    'catalog_type' => 'product',
                    'listing_type' => 'wholesale',
                    'posted_by_role' => null,
                    'title' => $row->name,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'w' . $row->id),
                    'description' => (string) $this->val($row, 'other', ''),
                    'brand' => $this->val($row, 'brand'), 'model' => null, 'color' => null, 'origin' => null, 'warranty' => null,
                    'price' => $this->val($row, 'price', 0),
                    'old_price' => null,
                    'currency' => 'BDT',
                    'min_order_qty' => $this->val($row, 'minimum_quantity', 1),
                    'stock_qty' => $this->val($row, 'quantity', 0),
                    'measurement_id' => isset($this->map['measurement'][$row->measurement_id]) ? $this->map['measurement'][$row->measurement_id] : null,
                    'colors' => $this->csvToJson($this->val($row, 'colors')),
                    'sizes' => $this->csvToJson($this->val($row, 'sizes')),
                    'reserved_qty' => null, 'condition_note' => null, 'attributes' => null,
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    'status' => ((int) $this->val($row, 'approve', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing']['wholesales'][$row->id] = $id;

                // first/second/third_quantity[_price] -> listing_price_tiers.
                // The legacy quantity columns are free-text strings (e.g. "10-49");
                // parsed heuristically below — spot check against real data.
                $tiers = [
                    [$this->val($row, 'first_quantity'), $this->val($row, 'first_quantity_price')],
                    [$this->val($row, 'second_quantity'), $this->val($row, 'second_quantity_price')],
                    [$this->val($row, 'third_quantity'), $this->val($row, 'third_quantity_price')],
                ];
                foreach ($tiers as $tier) {
                    list($qtyText, $price) = $tier;
                    if ($qtyText === null || $price === null) continue;
                    list($min, $max) = $this->parseQtyRange($qtyText);
                    Capsule::connection($this->new)->table('listing_price_tiers')->insert(array_merge([
                        'listing_id' => $id,
                        'min_qty' => $min,
                        'max_qty' => $max,
                        'price' => $price,
                    ], $this->systemAudit($row->created_at, $row->updated_at)));
                }
            }
        });
    }

    protected function parseQtyRange($text)
    {
        if (preg_match('/(\d+)\s*-\s*(\d+)/', (string) $text, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/(\d+)\s*\+/', (string) $text, $m)) {
            return [(int) $m[1], null];
        }
        $n = (int) preg_replace('/\D+/', '', (string) $text);
        return [$n ?: 1, null];
    }

    protected function csvToJson($value)
    {
        if (!$value) return null;
        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, function ($p) { return $p !== ''; }));
        return empty($parts) ? null : json_encode($parts);
    }

    protected function migrateBrandWalls(&$usedSlugs)
    {
        $this->chunk('brand_walls', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $categoryId = isset($this->map['category_product'][$row->child_category_id]) ? $this->map['category_product'][$row->child_category_id] : null;
                if ($categoryId === null) return;
                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => null,
                    'category_id' => $categoryId,
                    'catalog_type' => 'product',
                    'listing_type' => 'brand',
                    'posted_by_role' => null,
                    'title' => $row->name,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->name, 'bw' . $row->id),
                    'description' => (string) $this->val($row, 'other', ''),
                    'brand' => $this->val($row, 'brand'), 'model' => null, 'color' => null, 'origin' => null,
                    'warranty' => $this->val($row, 'warranty'),
                    'price' => $this->val($row, 'price', 0),
                    'old_price' => null,
                    'currency' => 'BDT',
                    'min_order_qty' => 1,
                    'stock_qty' => $this->val($row, 'quantity', 0),
                    'measurement_id' => isset($this->map['measurement'][$row->measurement_id]) ? $this->map['measurement'][$row->measurement_id] : null,
                    'colors' => $this->csvToJson($this->val($row, 'colors')),
                    'sizes' => $this->csvToJson($this->val($row, 'sizes')),
                    'reserved_qty' => null, 'condition_note' => null, 'attributes' => null,
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    'status' => ((int) $this->val($row, 'approve', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing']['brand_walls'][$row->id] = $id;
            }
        });
    }

    protected function migrateServices(&$usedSlugs)
    {
        $this->chunk('services', function ($rows) use (&$usedSlugs) {
            foreach ($rows as $row) {
                $categoryOldId = $this->val($row, 'child_id') ?: $this->val($row, 'parent_id') ?: $row->grand_id;
                $categoryId = isset($this->map['category_service'][$categoryOldId]) ? $this->map['category_service'][$categoryOldId] : null;
                if ($categoryId === null) return;

                // saler_post: named for "seller posted" — 1 means a seller offered this
                // service, 0 means it's a buyer-posted job request. Inferred from the
                // column name + how it lines up with the redesign's postedByRole split;
                // not confirmed against a live data sample.
                $salerPost = $this->oldHasColumn('services', 'saler_post') ? (int) $this->val($row, 'saler_post', 0) : 1;
                $sellerId = null; $buyerId = null; $postedByRole = null;
                if ($salerPost === 1) {
                    $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                    $postedByRole = 'seller_offer';
                    if ($sellerId === null) return;
                } else {
                    $buyerId = isset($this->map['user'][$row->user_id]) ? $this->map['user'][$row->user_id] : null;
                    $postedByRole = 'buyer_request';
                    if ($buyerId === null) return;
                }

                $hourPrice = $this->oldHasColumn('services', 'hourPrice') ? $this->val($row, 'hourPrice') : null;
                $priceType = $hourPrice !== null ? 'hourly' : 'fixed';

                list($countryId, $stateId, $cityId, $thanaId) = $this->geoForRow($row);
                if ($thanaId === null && $this->oldHasColumn('services', 'thana')) {
                    $thanaId = isset($this->map['thana'][$row->thana]) ? $this->map['thana'][$row->thana] : null;
                }

                $id = Capsule::connection($this->new)->table('listings')->insertGetId(array_merge([
                    'seller_id' => $sellerId,
                    'buyer_id' => $buyerId,
                    'category_id' => $categoryId,
                    'catalog_type' => 'service',
                    'listing_type' => null,
                    'posted_by_role' => $postedByRole,
                    'title' => $row->title,
                    'slug' => $this->uniqueSlug($usedSlugs, $row->title, 's' . $row->id),
                    'description' => (string) $row->description,
                    'brand' => null, 'model' => null, 'color' => null, 'origin' => null, 'warranty' => null,
                    'price' => $priceType === 'fixed' ? $this->val($row, 'price', 0) : 0,
                    'old_price' => null,
                    'currency' => 'BDT',
                    'price_type' => $priceType,
                    'hourly_rate' => $hourPrice,
                    'delivery_days' => $this->val($row, 'delivery'),
                    'urgent' => (bool) $this->val($row, 'urgent', 0),
                    'package_includes' => $this->val($row, 'include'),
                    'available_from' => $this->oldHasColumn('services', 'start_time') ? $this->val($row, 'start_time') : null,
                    'available_to' => $this->oldHasColumn('services', 'end_time') ? $this->val($row, 'end_time') : null,
                    'min_order_qty' => null, 'stock_qty' => null, 'measurement_id' => null,
                    'colors' => null, 'sizes' => null, 'reserved_qty' => null, 'condition_note' => null,
                    'attributes' => $this->oldHasColumn('services', 'hour') && $row->hour !== null ? json_encode(['estimatedHours' => $row->hour]) : null,
                    'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                    'status' => ((int) $this->val($row, 'approv', 1) === 1) ? 'approved' : 'pending',
                    'deleted_at' => $this->val($row, 'deleted_at'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['listing']['services'][$row->id] = $id;
            }
        });
    }

    // -------------------------------------------------------------------
    // 6b. listing_media: product_images, service_images, documents
    // -------------------------------------------------------------------

    protected function migrateListingMedia()
    {
        $this->chunk('product_images', function ($rows) {
            foreach ($rows as $row) {
                $listingId = $this->resolveListingId($row);
                if ($listingId === null) return;
                Capsule::connection($this->new)->table('listing_media')->insert(array_merge([
                    'listing_id' => $listingId,
                    'media_type' => 'image',
                    'media_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'media_url' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('service_images', function ($rows) {
            foreach ($rows as $row) {
                $listingId = isset($this->map['listing']['services'][$row->service_id]) ? $this->map['listing']['services'][$row->service_id] : null;
                if ($listingId === null) return;
                Capsule::connection($this->new)->table('listing_media')->insert(array_merge([
                    'listing_id' => $listingId,
                    'media_type' => 'image',
                    'media_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'media_url' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('documents', function ($rows) {
            foreach ($rows as $row) {
                $listingId = isset($this->map['listing']['brand_walls'][$row->brand_wall_id]) ? $this->map['listing']['brand_walls'][$row->brand_wall_id] : null;
                if ($listingId === null) return;
                Capsule::connection($this->new)->table('listing_media')->insert(array_merge([
                    'listing_id' => $listingId,
                    'media_type' => 'document',
                    'media_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'file')),
                    'media_url' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    // -------------------------------------------------------------------
    // 7. Bids (bid_for_services)
    // -------------------------------------------------------------------

    protected function migrateBids()
    {
        // A bid's real outcome (accepted/rejected/withdrawn) isn't recorded on
        // bid_for_services itself — only whether a `hireds` row exists for it.
        $hiredBidIds = Capsule::connection($this->old)->table('hireds')->pluck('accept', 'bid_id');

        $this->chunk('bid_for_services', function ($rows) use ($hiredBidIds) {
            foreach ($rows as $row) {
                $listingId = isset($this->map['listing']['services'][$row->service_id]) ? $this->map['listing']['services'][$row->service_id] : null;
                $bidderId = isset($this->map['seller'][$row->saler_id]) ? $this->map['seller'][$row->saler_id] : null;
                if ($listingId === null || $bidderId === null) return;

                $priceType = $this->val($row, 'hourPrice') !== null ? 'hourly' : 'fixed';
                $status = 'pending';
                if (isset($hiredBidIds[$row->id])) {
                    $status = 'accepted'; // a hireds row exists regardless of its own accept/cancel state — the bid itself WAS accepted at some point
                }

                $id = Capsule::connection($this->new)->table('bids')->insertGetId(array_merge([
                    'listing_id' => $listingId,
                    'bidder_id' => $bidderId,
                    'price_type' => $priceType,
                    'price' => $priceType === 'fixed' ? $this->val($row, 'price') : null,
                    'hourly_rate' => $priceType === 'hourly' ? $this->val($row, 'hourPrice') : null,
                    'hours' => $this->val($row, 'hour'),
                    'description' => substr((string) $row->description, 0, 300),
                    'proposed_start' => $this->val($row, 'start_time'),
                    'proposed_end' => $this->val($row, 'end_time'),
                    'status' => $status,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['bid'][$row->id] = $id;
            }
        });
    }

    // -------------------------------------------------------------------
    // 8. Carts + cart_items
    //    Transient data (an in-progress basket) — migrated for completeness
    //    but the lowest-value step here; skip it with --only if a cutover
    //    makes stale carts pointless. Old table has product_id nullable,
    //    plus service_id/used_mall_id/... in various shop-id columns.
    // -------------------------------------------------------------------

    protected function migrateCarts()
    {
        $this->chunk('carts', function ($rows) {
            foreach ($rows as $row) {
                $buyerId = isset($this->map['user'][$row->user_id]) ? $this->map['user'][$row->user_id] : null;
                if ($buyerId === null) return;
                $listingId = $this->resolveListingId($row);
                if ($listingId === null) return;

                if (!isset($this->map['cart'][$buyerId])) {
                    $cartId = Capsule::connection($this->new)->table('carts')->insertGetId(array_merge([
                        'buyer_id' => $buyerId,
                    ], $this->systemAudit($row->created_at, $row->updated_at)));
                    $this->map['cart'][$buyerId] = $cartId;
                }

                Capsule::connection($this->new)->table('cart_items')->insert(array_merge([
                    'cart_id' => $this->map['cart'][$buyerId],
                    'listing_id' => $listingId,
                    'qty' => (int) $this->val($row, 'qty', 1),
                    'color' => $this->val($row, 'color'),
                    'size' => $this->val($row, 'size'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    // -------------------------------------------------------------------
    // 9. Orders: product path (sales/orders/billings/shippings) +
    //    service path (service_orders, hireds) -> orders/order_items/addresses
    // -------------------------------------------------------------------

    protected function migrateOrders()
    {
        $this->migrateProductOrders();
        $this->migrateServiceOrders('service_orders', 'service', null);
        $this->migrateServiceOrders('hireds', 'job', 'bid_id');
    }

    protected function defaultCommissionRate()
    {
        // No commission-rate setting was found in the legacy `others` key/value
        // table read during this migration; the redesign doc notes the legacy
        // app hardcodes 20% in application code (not the DB). Using that as
        // the fallback snapshot rate for historical orders.
        return 20.00;
    }

    protected function migrateProductOrders()
    {
        $this->chunk('orders', function ($rows) {
            foreach ($rows as $row) {
                $sale = Capsule::connection($this->old)->table('sales')->where('id', $row->sale_id)->first();
                if (!$sale) return;
                $buyerId = isset($this->map['user'][$sale->user_id]) ? $this->map['user'][$sale->user_id] : null;
                $sellerId = isset($this->map['seller'][$row->saler_id]) ? $this->map['seller'][$row->saler_id] : null;
                if ($buyerId === null || $sellerId === null) return;

                // delivary: 1 pending, 2 delivered/completed, 3/4 both used as
                // cancel codes across two different code paths in
                // UserProductController (a duplication the redesign's own audit
                // flagged as a cancel-guard bug) — both treated as cancelled here.
                $delivary = (int) $this->val($row, 'delivary', 1);
                $status = $delivary === 2 ? 'completed' : (in_array($delivary, [3, 4], true) ? 'cancelled' : 'pending');

                $total = (float) $this->val($sale, 'total', 0);
                $deliveryCost = (float) $this->val($sale, 'cost', 0);
                $subtotal = max($total - $deliveryCost, 0);
                $rate = $this->defaultCommissionRate();

                $orderId = Capsule::connection($this->new)->table('orders')->insertGetId(array_merge([
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'order_type' => 'product',
                    'listing_id' => null,
                    'bid_id' => null,
                    'address_id' => null,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'delivery_cost' => $deliveryCost,
                    'discount' => 0,
                    'total' => $total,
                    'commission_rate' => $rate,
                    'commission_amount' => round($total * $rate / 100, 2),
                    'expected_delivery_at' => null,
                    'completed_at' => $status === 'completed' ? $row->updated_at : null,
                    'cancelled_reason' => null,
                    'confirmation_secret' => $this->val($row, 'secret'),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['order']['orders'][$row->id] = $orderId;

                $shipping = Capsule::connection($this->old)->table('shippings')->where('id', $row->shipping_id)->first();
                if ($shipping) {
                    $countryId = isset($this->map['country'][$shipping->country]) ? $this->map['country'][$shipping->country] : null;
                    $stateId = isset($this->map['state'][$shipping->state]) ? $this->map['state'][$shipping->state] : null;
                    $cityId = isset($this->map['city'][$shipping->city]) ? $this->map['city'][$shipping->city] : null;
                    $thanaId = isset($this->map['thana'][$shipping->thana]) ? $this->map['thana'][$shipping->thana] : null;
                    if ($countryId && $stateId && $cityId && $thanaId) {
                        $addressId = Capsule::connection($this->new)->table('addresses')->insertGetId(array_merge([
                            'addressable_type' => 'order',
                            'addressable_id' => $orderId,
                            'name' => $shipping->name,
                            'phone' => $shipping->mobile,
                            'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'thana_id' => $thanaId,
                            'address_line1' => $shipping->address1,
                            'address_line2' => $this->val($shipping, 'address2'),
                            'zip' => $this->val($shipping, 'zip'),
                        ], $this->systemAudit($shipping->created_at, $shipping->updated_at)));
                        Capsule::connection($this->new)->table('orders')->where('id', $orderId)->update(['address_id' => $addressId]);
                    }
                }

                Capsule::connection($this->old)->table('billings')->where('order_id', $row->id)
                    ->orderBy('id')->chunk($this->chunkSize, function ($billings) use ($orderId) {
                        foreach ($billings as $b) {
                            $listingId = $this->resolveListingId($b);
                            if ($listingId === null) return;
                            $listing = Capsule::connection($this->new)->table('listings')->where('id', $listingId)->first();
                            $price = (float) $this->val($b, 'price', 0);
                            $qty = (float) $this->val($b, 'qty', 1);
                            $bDelivary = (int) $this->val($b, 'delivary', 1);
                            Capsule::connection($this->new)->table('order_items')->insert(array_merge([
                                'order_id' => $orderId,
                                'listing_id' => $listingId,
                                'title_snapshot' => $listing ? $listing->title : 'Unknown listing',
                                'price_snapshot' => $price,
                                'qty' => (int) $qty,
                                'color' => $this->val($b, 'color'),
                                'size' => $this->val($b, 'size'),
                                'status' => $bDelivary === 2 ? 'delivered' : 'pending',
                                'deliverable_path' => null,
                                'subtotal' => round($price * $qty, 2),
                            ], $this->systemAudit($b->created_at, $b->updated_at)));
                        }
                    });
            }
        });
    }

    protected function migrateServiceOrders($table, $orderType, $bidColumn)
    {
        $payTable = $table === 'service_orders' ? 'order_pays' : 'hire_pays';
        $payFk = $table === 'service_orders' ? 'service_od_id' : 'hire_od_id';

        $this->chunk($table, function ($rows) use ($table, $orderType, $bidColumn, $payTable, $payFk) {
            foreach ($rows as $row) {
                $listingId = isset($this->map['listing']['services'][$row->service_id]) ? $this->map['listing']['services'][$row->service_id] : null;
                $buyerId = isset($this->map['user'][$row->buyer_id]) ? $this->map['user'][$row->buyer_id] : null;
                $sellerId = isset($this->map['seller'][$row->saler_id]) ? $this->map['seller'][$row->saler_id] : null;
                if ($listingId === null || $buyerId === null || $sellerId === null) return;

                $bidId = null;
                if ($bidColumn && isset($this->map['bid'][$row->{$bidColumn}])) {
                    $bidId = $this->map['bid'][$row->{$bidColumn}];
                }

                // accept: 0 pending, 1 accepted, 2 cancelled, 3 completed
                // (HireController/ServiceReqController/UserServiceController).
                $accept = (int) $this->val($row, 'accept', 0);
                $status = [0 => 'pending', 1 => 'accepted', 2 => 'cancelled', 3 => 'completed'];
                $status = isset($status[$accept]) ? $status[$accept] : 'pending';

                $priceType = (int) $this->val($row, 'priceType', 0) === 1 || $this->val($row, 'hourPrice') !== null ? 'hourly' : 'fixed';
                $total = $priceType === 'hourly'
                    ? (float) $this->val($row, 'hourPrice', 0) * (float) $this->val($row, 'hour', 1)
                    : (float) $this->val($row, 'price', 0);
                $rate = $this->defaultCommissionRate();

                $orderId = Capsule::connection($this->new)->table('orders')->insertGetId(array_merge([
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'order_type' => $orderType,
                    'listing_id' => $listingId,
                    'bid_id' => $bidId,
                    'address_id' => null,
                    'status' => $status,
                    'subtotal' => $total,
                    'delivery_cost' => 0,
                    'discount' => 0,
                    'total' => $total,
                    'commission_rate' => $rate,
                    'commission_amount' => round($total * $rate / 100, 2),
                    'expected_delivery_at' => null,
                    'completed_at' => $status === 'completed' ? $row->updated_at : null,
                    'cancelled_reason' => null,
                    'confirmation_secret' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $this->map['order'][$table][$row->id] = $orderId;

                $listing = Capsule::connection($this->new)->table('listings')->where('id', $listingId)->first();
                Capsule::connection($this->new)->table('order_items')->insert(array_merge([
                    'order_id' => $orderId,
                    'listing_id' => $listingId,
                    'title_snapshot' => $listing ? $listing->title : 'Unknown listing',
                    'price_snapshot' => $total,
                    'qty' => 1,
                    'color' => null, 'size' => null,
                    'status' => null,
                    'deliverable_path' => $this->oldHasColumn($table, 'file') ? $this->val($row, 'file') : null,
                    'subtotal' => $total,
                ], $this->systemAudit($row->created_at, $row->updated_at)));

                // order_pays / hire_pays -> payments (checkout_payment)
                $pay = Capsule::connection($this->old)->table($payTable)->where($payFk, $row->id)->first();
                if ($pay) {
                    $payment = Capsule::connection($this->old)->table('payments')->where('id', $pay->payment_id)->first();
                    if ($payment) {
                        Capsule::connection($this->new)->table('payments')->insert(array_merge([
                            'order_id' => $orderId,
                            'user_id' => $buyerId,
                            'user_type' => 'user',
                            'purpose' => 'checkout_payment',
                            'direction' => 'debit',
                            'amount' => $this->val($payment, 'amount', $total),
                            'method' => 'legacy_gateway',
                            'gateway_txn_id' => $this->val($payment, 'trxID'),
                            'payout_account_number' => null,
                            'status' => ((int) $this->val($payment, 'is_done', 0) === 1) ? 'completed' : 'pending',
                            'meta' => json_encode(['legacyPaymentId' => $payment->id, 'legacySource' => $payTable, 'paymentMethodCode' => $this->val($row, 'paymentMethod')]),
                        ], $this->systemAudit($payment->created_at, $payment->updated_at)));
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------------
    // 10. Standalone ledger tables not already covered via order_pays/hire_pays:
    //     balances, payment_requests, seller_payments (THREE parallel
    //     withdrawal systems in the legacy schema — kept distinguishable via
    //     payments.meta.legacySource rather than silently merged), plus
    //     commissions, commission_payments, refund_histories.
    // -------------------------------------------------------------------

    protected function migrateStandalonePayments()
    {
        $this->chunk('balances', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => null,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'seller_payout',
                    'direction' => 'credit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => 'legacy_gateway',
                    'gateway_txn_id' => null,
                    'payout_account_number' => null,
                    'status' => ((int) $this->val($row, 'is_refunded', 0) === 1) ? 'refunded' : (((int) $this->val($row, 'is_requested', 0) === 1) ? 'pending' : 'completed'),
                    'meta' => json_encode(['legacySource' => 'balances', 'legacyId' => $row->id, 'legacyPaymentId' => $this->val($row, 'payment_id')]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('payment_requests', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => null,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'seller_payout',
                    'direction' => 'credit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => $this->val($row, 'method', 'legacy_gateway'),
                    'gateway_txn_id' => $this->val($row, 'txn_id'),
                    'payout_account_number' => $this->val($row, 'account_number'),
                    // legacy int status meaning wasn't confirmed against a live sample; 1 assumed completed, else pending.
                    'status' => ((int) $this->val($row, 'status', 0) === 1) ? 'completed' : 'pending',
                    'meta' => json_encode(['legacySource' => 'payment_requests', 'legacyId' => $row->id]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('seller_payments', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => null,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'seller_payout',
                    'direction' => 'credit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => $this->val($row, 'payment_method', 'legacy_gateway'),
                    'gateway_txn_id' => null,
                    'payout_account_number' => null,
                    'status' => $this->val($row, 'payment_status', 'unpaid') === 'paid' ? 'completed' : 'pending',
                    'meta' => json_encode(['legacySource' => 'seller_payments', 'legacyId' => $row->id, 'legacyPaymentId' => $this->val($row, 'payment_id')]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('commission_payments', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => null,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'commission_payment',
                    'direction' => 'debit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => 'legacy_gateway',
                    'gateway_txn_id' => $this->val($row, 'transaction_id'),
                    'payout_account_number' => null,
                    'status' => ((int) $this->val($row, 'verified', 0) === 1) ? 'completed' : 'pending',
                    'meta' => json_encode(['legacySource' => 'commission_payments', 'legacyId' => $row->id]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('commissions', function ($rows) {
            foreach ($rows as $row) {
                $sellerId = isset($this->map['seller'][$row->user_id]) ? $this->map['seller'][$row->user_id] : null;
                if ($sellerId === null) return;
                $orderId = null;
                if ($this->val($row, 'order_id') && isset($this->map['order']['orders'][$row->order_id])) {
                    $orderId = $this->map['order']['orders'][$row->order_id];
                } elseif ($this->val($row, 'hire_id') && isset($this->map['order']['hireds'][$row->hire_id])) {
                    $orderId = $this->map['order']['hireds'][$row->hire_id];
                }
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => $orderId,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'commission_payment',
                    'direction' => 'debit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => 'legacy_gateway',
                    'gateway_txn_id' => null,
                    'payout_account_number' => null,
                    'status' => ((int) $this->val($row, 'is_done', 0) === 1) ? 'completed' : 'pending',
                    'meta' => json_encode(['legacySource' => 'commissions', 'legacyId' => $row->id]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('refund_histories', function ($rows) {
            foreach ($rows as $row) {
                $balance = Capsule::connection($this->old)->table('balances')->where('id', $row->balance_id)->first();
                $sellerId = $balance && isset($this->map['seller'][$balance->user_id]) ? $this->map['seller'][$balance->user_id] : null;
                if ($sellerId === null) return;
                Capsule::connection($this->new)->table('payments')->insert(array_merge([
                    'order_id' => null,
                    'user_id' => $sellerId,
                    'user_type' => 'seller',
                    'purpose' => 'refund',
                    'direction' => 'credit',
                    'amount' => $this->val($row, 'amount', 0),
                    'method' => 'legacy_gateway',
                    'gateway_txn_id' => $this->val($row, 'refundTrxID'),
                    'payout_account_number' => null,
                    'status' => 'completed',
                    'meta' => json_encode(['legacySource' => 'refund_histories', 'legacyId' => $row->id, 'legacyBalanceId' => $row->balance_id]),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    // -------------------------------------------------------------------
    // 11. Quote requests (simple_reqs)
    // -------------------------------------------------------------------

    protected function migrateQuoteRequests()
    {
        $this->chunk('simple_reqs', function ($rows) {
            foreach ($rows as $row) {
                $listingId = isset($this->map['listing']['products'][$row->product_id]) ? $this->map['listing']['products'][$row->product_id] : null;
                if ($listingId === null) return;
                $listing = Capsule::connection($this->new)->table('listings')->where('id', $listingId)->first();
                $sellerId = $listing ? $listing->seller_id : null;
                if ($sellerId === null) return;

                // legacy simple_reqs.user_id is documented (schema_data.js "Buyer
                // Inquiries" module) as actually being the SELLER, with the buyer's
                // own contact captured only as free text (name/email/phone/company)
                // — this is the exact bug the redesign fixes, so no buyer_id FK
                // is populated here unless the free-text email matches a known buyer.
                $buyerId = null;
                $matchedUser = Capsule::connection($this->new)->table('users')->where('email', $this->val($row, 'email'))->first();
                if ($matchedUser) $buyerId = $matchedUser->id;

                Capsule::connection($this->new)->table('quote_requests')->insert(array_merge([
                    'listing_id' => $listingId,
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'qty' => $this->val($row, 'qty', 1),
                    'message' => $this->val($row, 'remark', ''),
                    'contact_name' => $this->val($row, 'name'),
                    'contact_email' => $this->val($row, 'email'),
                    'contact_phone' => $this->val($row, 'phone'),
                    'contact_company' => $this->val($row, 'company'),
                    'contact_city' => $this->val($row, 'city'),
                    'contact_road' => $this->val($row, 'road'),
                    'status' => 'new',
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    // -------------------------------------------------------------------
    // 12. Messaging (chats -> conversations, messages -> messages)
    // -------------------------------------------------------------------

    protected function migrateMessaging()
    {
        $conversationMap = []; // old chat id -> new conversation id

        $this->chunk('chats', function ($rows) use (&$conversationMap) {
            foreach ($rows as $row) {
                // chats.buyer/saler both store users.id — resolve each to whichever
                // bucket (buyer vs seller) that user actually landed in.
                $buyerId = isset($this->map['user'][$row->buyer]) ? $this->map['user'][$row->buyer] : null;
                $sellerId = isset($this->map['seller'][$row->saler]) ? $this->map['seller'][$row->saler] : null;
                if ($buyerId === null || $sellerId === null) return;

                $listingId = null;
                // `plus` was an untyped shop-id column on chats — best-effort resolve
                // against every source table since we don't know which one it meant.
                foreach (['products', 'services', 'used_malls', 'village_products', 'retail_shops', 'wholesales', 'brand_walls'] as $src) {
                    if (isset($this->map['listing'][$src][$row->plus])) {
                        $listingId = $this->map['listing'][$src][$row->plus];
                        break;
                    }
                }

                $id = Capsule::connection($this->new)->table('conversations')->insertGetId(array_merge([
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'listing_id' => $listingId,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
                $conversationMap[$row->id] = $id;
            }
        });

        $this->chunk('messages', function ($rows) use (&$conversationMap) {
            foreach ($rows as $row) {
                if (!isset($conversationMap[$row->chat_id])) return;
                $conversation = Capsule::connection($this->new)->table('conversations')->where('id', $conversationMap[$row->chat_id])->first();
                $senderType = ((int) $row->user_id === (int) $this->oldUserIdForNewBuyer($conversation->buyer_id)) ? 'buyer' : 'seller';

                Capsule::connection($this->new)->table('messages')->insert(array_merge([
                    'conversation_id' => $conversationMap[$row->chat_id],
                    'sender_id' => $row->user_id, // legacy id, best-effort — see note below
                    'sender_type' => $senderType,
                    'body' => $this->val($row, 'message'),
                    'attachment_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'attachment_url' => null,
                    'seen_at' => ((int) $this->val($row, 'seen', 1) === 1) ? $row->updated_at : null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    /**
     * messages.user_id is the legacy users.id of the sender, but we only know
     * the NEW buyer/seller id on the conversation. Reverse-map new buyer id
     * back to its legacy id once per lookup (cheap enough at this data size)
     * so the sender can be classified as buyer vs seller.
     */
    protected function oldUserIdForNewBuyer($newBuyerId)
    {
        static $reverse = null;
        if ($reverse === null) {
            $reverse = array_flip($this->map['user']);
        }
        return isset($reverse[$newBuyerId]) ? $reverse[$newBuyerId] : null;
    }

    // -------------------------------------------------------------------
    // 13. Reviews & favourites
    // -------------------------------------------------------------------

    protected function migrateReviewsAndFavourites()
    {
        $this->chunk('reviews', function ($rows) {
            foreach ($rows as $row) {
                $reviewerId = isset($this->map['user'][$row->user_id]) ? $this->map['user'][$row->user_id] : null;
                if ($reviewerId === null) return;
                $listingId = $this->resolveListingId($row);
                if ($listingId === null) return;

                // reviews predate the "must reference a completed order" rule this
                // redesign introduces — best-effort match to the most recent
                // completed order between this buyer/listing; skipped if none found,
                // since orders.id is NOT NULL on the new reviews table.
                $order = Capsule::connection($this->new)->table('orders')
                    ->where('buyer_id', $reviewerId)
                    ->where('listing_id', $listingId)
                    ->orderByDesc('id')->first();
                if (!$order) return;

                Capsule::connection($this->new)->table('reviews')->insert(array_merge([
                    'order_id' => $order->id,
                    'reviewer_id' => $reviewerId,
                    'listing_id' => $listingId,
                    'rating' => max(1, min(5, (int) $this->val($row, 'review', 5))),
                    'content' => $this->val($row, 'content') ?: $this->val($row, 'service_content'),
                    'seller_reply' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('favourits', function ($rows) {
            foreach ($rows as $row) {
                $userId = isset($this->map['user'][$row->user_id]) ? $this->map['user'][$row->user_id] : null;
                if ($userId === null) return;
                $listingId = $this->resolveListingId($row);
                if ($listingId === null) return;

                Capsule::connection($this->new)->table('favourites')->insertOrIgnore(array_merge([
                    'user_id' => $userId,
                    'listing_id' => $listingId,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }

    // -------------------------------------------------------------------
    // 14. Notifications — unchanged, straight copy (Laravel's own table).
    // -------------------------------------------------------------------

    protected function migrateNotifications()
    {
        $this->chunk('notifications', function ($rows) {
            foreach ($rows as $row) {
                Capsule::connection($this->new)->table('notifications')->insert([
                    'id' => $row->id,
                    'type' => $row->type,
                    'notifiable_type' => $row->notifiable_type,
                    'notifiable_id' => $row->notifiable_id,
                    'data' => $row->data,
                    'read_at' => $row->read_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        });
    }

    // -------------------------------------------------------------------
    // 15. CMS: banners + sidebar_banners -> banners, email_contents ->
    //     email_templates, others -> settings
    // -------------------------------------------------------------------

    protected function migrateCms()
    {
        $this->chunk('banners', function ($rows) {
            foreach ($rows as $row) {
                Capsule::connection($this->new)->table('banners')->insert(array_merge([
                    'placement' => 'hero',
                    'heading' => $this->val($row, 'heading'),
                    'small_heading' => $this->val($row, 'small_heading'),
                    'link_url' => null,
                    'image_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'image_url' => null,
                    'category_id' => null,
                    // legacy carries two status ints (status default 2, statusS
                    // default 4) whose exact meaning the redesign notes flag as
                    // unrecoverable from schema alone — collapsed to a simple
                    // active/inactive here; re-check against the admin panel.
                    'status' => 'active',
                    'sort_order' => $row->id,
                    'starts_at' => null,
                    'ends_at' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('sidebar_banners', function ($rows) {
            foreach ($rows as $row) {
                $categoryId = isset($this->map['category_product'][$row->category_id]) ? $this->map['category_product'][$row->category_id] : null;
                Capsule::connection($this->new)->table('banners')->insert(array_merge([
                    'placement' => 'sidebar',
                    'heading' => null,
                    'small_heading' => null,
                    'link_url' => null,
                    'image_path' => $this->joinPath($this->val($row, 'path'), $this->val($row, 'image')),
                    'image_url' => null,
                    'category_id' => $categoryId,
                    'status' => 'active',
                    'sort_order' => $row->id,
                    'starts_at' => null,
                    'ends_at' => null,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('email_contents', function ($rows) {
            foreach ($rows as $row) {
                Capsule::connection($this->new)->table('email_templates')->insertOrIgnore(array_merge([
                    'key' => Str::slug($row->name),
                    'subject' => $row->name,
                    'content' => $row->content,
                    // most Mail::send() dispatch calls are commented out in the
                    // legacy app per the platform audit — is_active is a guess
                    // (defaulted true) since the DB itself never recorded this.
                    'is_active' => true,
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });

        $this->chunk('others', function ($rows) {
            foreach ($rows as $row) {
                $value = array_filter([
                    'value1' => $this->val($row, 'value1'),
                    'value2' => $this->val($row, 'value2'),
                    'value3' => $this->val($row, 'value3'),
                ], function ($v) { return $v !== null; });
                Capsule::connection($this->new)->table('settings')->insertOrIgnore(array_merge([
                    'key' => Str::slug($row->name, '_'),
                    'value' => json_encode(count($value) === 1 ? array_values($value)[0] : $value),
                ], $this->systemAudit($row->created_at, $row->updated_at)));
            }
        });
    }
}
