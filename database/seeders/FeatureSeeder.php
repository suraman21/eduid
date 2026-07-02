<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Feature registry
        $features = [
            ['key' => 'student_management',    'label' => 'Student Management',       'category' => 'core',       'is_default_enabled' => true],
            ['key' => 'attendance_homeroom',    'label' => 'Homeroom Attendance',      'category' => 'attendance', 'is_default_enabled' => true],
            ['key' => 'attendance_subject',     'label' => 'Per-Subject Attendance',   'category' => 'attendance', 'is_default_enabled' => false],
            ['key' => 'gradebook',              'label' => 'Gradebook',                'category' => 'academic',   'is_default_enabled' => true],
            ['key' => 'report_cards',           'label' => 'Report Cards',             'category' => 'academic',   'is_default_enabled' => true],
            ['key' => 'student_id_cards',       'label' => 'Student ID Cards',         'category' => 'cards',      'is_default_enabled' => true],
            ['key' => 'employee_id_cards',      'label' => 'Employee ID Cards',        'category' => 'cards',      'is_default_enabled' => false],
            ['key' => 'timetable',              'label' => 'Timetable',                'category' => 'academic',   'is_default_enabled' => false],
            ['key' => 'staff_attendance',       'label' => 'Staff Attendance',         'category' => 'attendance', 'is_default_enabled' => false],
            ['key' => 'parent_app',             'label' => 'Parent App',               'category' => 'parent',     'is_default_enabled' => false],
            ['key' => 'csv_import',             'label' => 'CSV Import',               'category' => 'data',       'is_default_enabled' => false],
            ['key' => 'data_export',            'label' => 'Data Export',              'category' => 'data',       'is_default_enabled' => true],
            ['key' => 'supplementary_exams',    'label' => 'Supplementary Exams',      'category' => 'academic',   'is_default_enabled' => false],
            ['key' => 'year_transition',        'label' => 'Year Transition Wizard',   'category' => 'academic',   'is_default_enabled' => false],
            ['key' => 'custom_card_templates',  'label' => 'Custom Card Templates',    'category' => 'cards',      'is_default_enabled' => false],
            ['key' => 'analytics',              'label' => 'Analytics Dashboard',      'category' => 'academic',   'is_default_enabled' => false],
            ['key' => 'alerts',                 'label' => 'Telegram Absence Alerts',  'category' => 'parent',     'is_default_enabled' => false],
        ];

        // Keys that already existed BEFORE this run — used below so that
        // defaults for BRAND-NEW features can seed additively on existing
        // installs without touching the admin's live plan matrix.
        $preExistingKeys = DB::table('feature_registry')->pluck('key')->flip();

        foreach ($features as $feature) {
            DB::table('feature_registry')->updateOrInsert(
                ['key' => $feature['key']],
                array_merge($feature, ['description' => null, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        // Plan features
        $starterFeatures = [
            'student_management',
            'attendance_homeroom',
            'gradebook',
            'report_cards',
            'student_id_cards',
            'data_export',
        ];

        $standardExtras = [
            'attendance_subject',
            'employee_id_cards',
            'staff_attendance',
            'csv_import',
            'custom_card_templates',
            'timetable',
            'year_transition',
        ];

        $premiumExtras = [
            'parent_app',
            'supplementary_exams',
            'analytics',
            'alerts',
        ];

        $planFeatures = [];

        foreach ($starterFeatures as $key) {
            $planFeatures[] = ['plan_tier' => 'starter', 'feature_key' => $key];
        }

        $standardFeatures = array_merge($starterFeatures, $standardExtras);
        foreach ($standardFeatures as $key) {
            $planFeatures[] = ['plan_tier' => 'standard', 'feature_key' => $key];
        }

        $premiumFeatures = array_merge($standardFeatures, $premiumExtras);
        foreach ($premiumFeatures as $key) {
            $planFeatures[] = ['plan_tier' => 'premium', 'feature_key' => $key];
        }

        // FIRST INSTALL ONLY. This seeder runs on EVERY deploy (standing
        // deploy command), and the super admin edits the plan×feature matrix
        // live on the Packages page — truncating + reseeding here would
        // silently revert those business decisions on each deploy. Row
        // presence = enabled, so we only lay down the default matrix when
        // the table has never been populated.
        if (DB::table('plan_features')->count() === 0) {
            foreach ($planFeatures as $pf) {
                DB::table('plan_features')->insert(array_merge($pf, ['created_at' => $now, 'updated_at' => $now]));
            }
        } else {
            // Additive path for existing installs: when a release introduces
            // a NEW feature key, its default tier rows seed once (the key was
            // absent from the registry until this run). Rows for features the
            // admin already curates are never touched.
            foreach ($planFeatures as $pf) {
                if (! $preExistingKeys->has($pf['feature_key'])) {
                    DB::table('plan_features')->updateOrInsert(
                        $pf,
                        array_merge($pf, ['created_at' => $now, 'updated_at' => $now])
                    );
                }
            }
        }

        // Subscription plans
        $plans = [
            [
                'name'               => 'Starter',
                'tier'               => 'starter',
                'price_monthly_etb'  => 12000.00,
                'price_annual_etb'   => 144000.00,
                'max_students'       => 300,
                'max_teachers'       => 10,
                'max_school_admins'  => 1,
                'description'        => 'Essential features for small schools',
                'is_active'          => true,
            ],
            [
                'name'               => 'Standard',
                'tier'               => 'standard',
                'price_monthly_etb'  => 22000.00,
                'price_annual_etb'   => 264000.00,
                'max_students'       => 800,
                'max_teachers'       => 30,
                'max_school_admins'  => 2,
                'description'        => 'Full-featured plan for growing schools',
                'is_active'          => true,
            ],
            [
                'name'               => 'Premium',
                'tier'               => 'premium',
                'price_monthly_etb'  => 35000.00,
                'price_annual_etb'   => 420000.00,
                'max_students'       => 999999,
                'max_teachers'       => 999,
                'max_school_admins'  => 10,
                'description'        => 'Unlimited growth with all features including parent app',
                'is_active'          => true,
            ],
        ];

        // Insert missing tiers only — never overwrite. Pricing and seat
        // limits are edited live on the Packages page; updateOrInsert here
        // used to reset them to these defaults on every deploy.
        foreach ($plans as $plan) {
            $exists = DB::table('subscription_plans')
                ->where('tier', $plan['tier'])->exists();
            if (! $exists) {
                DB::table('subscription_plans')->insert(
                    array_merge($plan, ['created_at' => $now, 'updated_at' => $now])
                );
            }
        }
    }
}
