<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the feature registry + plans on every RefreshDatabase test run.
     *
     * Production always has these rows (the standard deploy command runs
     * FeatureSeeder), and the 'feature:*' route gates resolve to DISABLED
     * when the registry is empty — so an unseeded test database would 403
     * routes that are open on every real install.
     */
    protected $seed = true;

    protected $seeder = \Database\Seeders\FeatureSeeder::class;
}
