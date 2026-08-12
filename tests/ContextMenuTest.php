<?php

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use Leek\FilamentRightClick\Macros\RegisterMacros;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use Leek\FilamentRightClick\Menu\ContextMenuSection;
use Leek\FilamentRightClick\Menu\ContextMenuSeparator;
use Leek\FilamentRightClick\Menu\ContextMenuSubmenu;
use Leek\FilamentRightClick\Tests\Fixtures\FakeTableComponent;

it('encodes items sections and separators for the browser menu', function (): void {
    $entries = [
        ContextMenuSection::make([
            ContextMenuItem::for(Action::make('archive'))
                ->label('Archive')
                ->color('warning'),
        ])->label('Manage'),
        ContextMenuSeparator::make(),
        ContextMenuItem::for(Action::make('delete'))
            ->label('Delete')
            ->color('danger'),
    ];

    $payload = json_decode(base64_decode(RegisterMacros::encodeConfig($entries)), associative: true);

    expect($payload)
        ->version->toBe(1)
        ->target->toBe('record')
        ->items->toHaveCount(3)
        ->items->{0}->type->toBe('section')
        ->items->{0}->label->toBe('Manage')
        ->items->{0}->items->{0}->action->toBe('archive')
        ->items->{1}->type->toBe('separator')
        ->items->{2}->action->toBe('delete')
        ->items->{2}->color->toBe('danger');
});

it('registers wrapped actions as context-only table actions', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    $table = Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuActions([
            ContextMenuItem::for(Action::make('archive'))->label('Archive'),
        ]);

    $attributes = $table->getExtraAttributes();
    $payload = json_decode(base64_decode($attributes['data-filament-right-click-record-config']), associative: true);

    expect($table->hasAction('archive'))->toBeTrue();
    expect($table->getRecordActions())->toBeEmpty();
    expect($attributes['class'])->toContain('fi-right-click-table');
    expect($attributes['data-filament-right-click-config'])->toBe($attributes['data-filament-right-click-record-config']);
    expect($attributes['data-filament-right-click-script-src'])->toContain('filament-right-click.js');
    expect($attributes['data-filament-right-click-style-href'])->toContain('filament-right-click.css');
    expect($attributes['x-init']->toHtml())->toContain('FilamentRightClick');
    expect($payload['items'][0]['action'])->toBe('archive');
});

it('registers bulk actions as context-only table bulk actions', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    $table = Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuBulkActions([
            BulkAction::make('archiveSelected')->label('Archive selected'),
        ]);

    $attributes = $table->getExtraAttributes();
    $payload = json_decode(base64_decode($attributes['data-filament-right-click-bulk-config']), associative: true);

    expect($table->hasBulkAction('archiveSelected'))->toBeTrue();
    expect($table->hasAction('archiveSelected'))->toBeFalse();
    expect($table->getRecordActions())->toBeEmpty();
    expect($payload['target'])->toBe('bulk');
    expect($payload['items'][0]['action'])->toBe('archiveSelected');
    expect($payload['items'][0]['target'])->toBe('bulk');
    expect($payload['items'][0]['label'])->toBe('Archive selected');
});

it('wraps raw bulk actions as bulk menu items for the bulk macro', function (): void {
    $entries = RegisterMacros::normalizeBulkEntries([
        BulkAction::make('deleteSelected'),
    ]);

    $payload = json_decode(base64_decode(RegisterMacros::encodeConfig($entries, target: 'bulk')), associative: true);

    expect($payload['target'])->toBe('bulk');
    expect($payload['items'][0]['action'])->toBe('deleteSelected');
    expect($payload['items'][0]['target'])->toBe('bulk');
});

it('rejects bulk actions in record context menus', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    expect(fn () => Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuActions([
            BulkAction::make('deleteSelected'),
        ]))->toThrow(InvalidArgumentException::class, 'Use contextMenuBulkActions()');
});

it('rejects record actions in bulk context menus', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    expect(fn () => Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuBulkActions([
            Action::make('edit'),
        ]))->toThrow(InvalidArgumentException::class, 'only accepts Filament BulkAction');
});

it('can explicitly target a bulk action', function (): void {
    $entry = ContextMenuItem::forBulkAction(BulkAction::make('exportSelected'));

    expect($entry->toPayload())
        ->action->toBe('exportSelected')
        ->target->toBe('bulk');
});

it('inherits icon and color from the underlying action', function (): void {
    $entry = ContextMenuItem::for(
        Action::make('delete')
            ->icon(new HtmlString('<svg data-icon="trash"></svg>'))
            ->color('danger'),
    );

    expect($entry->toPayload())
        ->color->toBe('danger')
        ->icon->toContain('data-icon="trash"');
});

it('prefers explicit item color over the action color', function (): void {
    $entry = ContextMenuItem::for(Action::make('delete')->color('danger'))
        ->color('warning');

    expect($entry->toPayload())->color->toBe('warning');
});

it('omits non-string action colors from the payload', function (): void {
    $entry = ContextMenuItem::for(Action::make('delete')->color(Color::Red));

    expect($entry->toPayload())->not->toHaveKey('color');
});

it('encodes nested submenus without a parent action and registers nested leaf actions', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    $entries = [
        ContextMenuSubmenu::make([
            ContextMenuItem::for(Action::make('setStatusTodo'))->label('Todo'),
            ContextMenuSection::make([
                ContextMenuItem::for(Action::make('setStatusDone'))->label('Done'),
            ])->label('Closed'),
            ContextMenuSubmenu::make([
                ContextMenuItem::for(Action::make('setStatusBlocked'))->label('Blocked'),
            ])->label('More statuses')->icon(new HtmlString('<svg data-icon="status"></svg>')),
        ])
            ->label('Status')
            ->icon(new HtmlString('<svg data-icon="circle"></svg>')),
        ContextMenuSeparator::make(),
        ContextMenuItem::for(Action::make('assign'))->label('Assign'),
    ];

    $payload = json_decode(base64_decode(RegisterMacros::encodeConfig($entries)), associative: true);

    expect($payload['items'][0])
        ->type->toBe('submenu')
        ->label->toBe('Status')
        ->not->toHaveKey('action')
        ->icon->toContain('data-icon="circle"')
        ->items->toHaveCount(3);

    expect($payload['items'][0]['items'][0])
        ->type->toBe('item')
        ->action->toBe('setStatusTodo')
        ->label->toBe('Todo');

    expect($payload['items'][0]['items'][1])
        ->type->toBe('section')
        ->label->toBe('Closed')
        ->items->{0}->action->toBe('setStatusDone');

    expect($payload['items'][0]['items'][2])
        ->type->toBe('submenu')
        ->label->toBe('More statuses')
        ->not->toHaveKey('action')
        ->items->{0}->action->toBe('setStatusBlocked');

    expect($payload['items'][2]['action'])->toBe('assign');

    // Nested leaf actions must still land in the action cache (table path).
    $table = Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuActions($entries);

    expect($table->hasAction('setStatusTodo'))->toBeTrue();
    expect($table->hasAction('setStatusDone'))->toBeTrue();
    expect($table->hasAction('setStatusBlocked'))->toBeTrue();
    expect($table->hasAction('assign'))->toBeTrue();
    expect($table->getRecordActions())->toBeEmpty();

    // Parent submenu contributes no action of its own.
    expect(ContextMenuSubmenu::make([
        ContextMenuItem::for(Action::make('leaf')),
    ])->label('Parent')->toPayload())->not->toHaveKey('action');
});

it('rejects bulk actions nested inside a submenu in a record menu', function (): void {
    FilamentRightClickPlugin::make()->register(Panel::make());

    expect(fn () => Table::make(new FakeTableComponent)
        ->columns([])
        ->contextMenuActions([
            ContextMenuSubmenu::make([
                ContextMenuItem::forBulkAction(BulkAction::make('deleteSelected')),
            ])->label('Danger'),
        ]))->toThrow(InvalidArgumentException::class, 'Use contextMenuBulkActions()');
});
