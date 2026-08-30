<?php

namespace Sttc\SsoClient\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sttc\SsoClient\SsoClientServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SsoClientServiceProvider::class];
    }
}
