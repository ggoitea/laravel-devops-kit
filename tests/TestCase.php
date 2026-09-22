<?php

namespace Ggoitea\LaravelDevopsKit\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Ggoitea\LaravelDevopsKit\DevOpsKitServiceProvider::class,
        ];
    }
}
