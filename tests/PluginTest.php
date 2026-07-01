<?php

use Filament\Panel;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Table;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use Leek\FilamentRightClick\FilamentRightClickServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getProviders(FilamentRightClickServiceProvider::class))
        ->not->toBeEmpty();
});

it('can be instantiated via make()', function (): void {
    $plugin = FilamentRightClickPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentRightClickPlugin::class);
    expect($plugin->getId())->toBe('filament-right-click');
});

it('registers assets and table macros when the panel plugin registers', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    expect(Table::hasMacro('contextMenuActions'))->toBeTrue();
    expect(Table::hasMacro('contextMenuBulkActions'))->toBeTrue();
    expect(FilamentAsset::getScriptSrc('filament-right-click', 'leek/filament-right-click'))->not->toBeEmpty();

    $scripts = collect(FilamentAsset::getScripts(['leek/filament-right-click']))
        ->mapWithKeys(fn ($script): array => [$script->getId() => $script->isLoadedOnRequest()]);

    $styles = collect(FilamentAsset::getStyles(['leek/filament-right-click']))
        ->mapWithKeys(fn ($style): array => [$style->getId() => $style->isLoadedOnRequest()]);

    expect(collect(FilamentAsset::getStyles(['leek/filament-right-click']))
        ->first(fn ($style): bool => $style->getId() === 'filament-right-click')
        ?->getHref())->not->toBeEmpty();
    expect($scripts->get('filament-right-click'))->toBeTrue();
    expect($styles->get('filament-right-click'))->toBeTrue();
});

it('does not register package views that break view caching', function (): void {
    $this->artisan('view:cache')
        ->assertSuccessful();
});
