<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
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

        FilamentAsset::register(
            assets: [
                Js::make('filament-right-click', __DIR__.'/../resources/dist/filament-right-click.js'),
                Css::make('filament-right-click', __DIR__.'/../resources/dist/filament-right-click.css'),
            ],
            package: 'leek/filament-right-click',
        );
    }
}
