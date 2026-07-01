<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Tests;

use Leek\FilamentRightClick\FilamentRightClickServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentRightClickServiceProvider::class,
        ];
    }
}
