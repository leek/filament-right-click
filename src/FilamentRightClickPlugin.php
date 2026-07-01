<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Leek\FilamentRightClick\Macros\RegisterMacros;

class FilamentRightClickPlugin implements Plugin
{
    protected static bool $assetsRegistered = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-right-click';
    }

    public function register(Panel $panel): void
    {
        RegisterMacros::register();

        if (static::$assetsRegistered && $this->hasRegisteredAssets()) {
            return;
        }

        FilamentAsset::register(
            assets: [
                Js::make('filament-right-click', __DIR__.'/../resources/dist/filament-right-click.js')
                    ->loadedOnRequest(),
                Css::make('filament-right-click', __DIR__.'/../resources/dist/filament-right-click.css')
                    ->loadedOnRequest(),
            ],
            package: 'leek/filament-right-click',
        );

        static::$assetsRegistered = true;
    }

    public function boot(Panel $panel): void
    {
        //
    }

    protected function hasRegisteredAssets(): bool
    {
        return collect(FilamentAsset::getScripts(['leek/filament-right-click']))
            ->contains(fn (Js $script): bool => $script->getId() === 'filament-right-click');
    }
}
