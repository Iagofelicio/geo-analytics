<?php

namespace Iagofelicio\GeoAnalytics\Tests;

use Iagofelicio\GeoAnalytics\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
