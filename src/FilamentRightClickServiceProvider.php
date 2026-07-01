<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick;

use Leek\FilamentRightClick\Macros\RegisterMacros;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentRightClickServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-right-click';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews();
    }

    public function packageBooted(): void
    {
        RegisterMacros::register();
    }
}
