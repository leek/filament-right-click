<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Macros;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use JsonException;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use WeakMap;

class RegisterMacros
{
    protected static bool $tableMacrosRegistered = false;

    protected static bool $flowforgeMacrosRegistered = false;

    /**
     * @var WeakMap<object, array<string, mixed>>|null
     */
    protected static ?WeakMap $flowforgeContextMenuAttributes = null;

    public static function register(): void
    {
        static::registerTableMacros();
        static::registerFlowforgeMacros();
    }

    protected static function registerTableMacros(): void
    {
        if (static::$tableMacrosRegistered) {
            return;
        }

        Table::macro('contextMenuActions', function (array $entries): Table {
            /** @var Table $this */
            $entries = RegisterMacros::normalizeRecordEntries($entries);

            RegisterMacros::registerEntries($this, $entries);

            return RegisterMacros::applyContextMenuAttributes($this, [
                'data-filament-right-click-config' => RegisterMacros::encodeConfig($entries),
                'data-filament-right-click-record-config' => RegisterMacros::encodeConfig($entries),
            ]);
        });

        Table::macro('contextMenuBulkActions', function (array $entries): Table {
            /** @var Table $this */
            $entries = RegisterMacros::normalizeBulkEntries($entries);

            RegisterMacros::registerEntries($this, $entries);

            return RegisterMacros::applyContextMenuAttributes($this, [
                'data-filament-right-click-bulk-config' => RegisterMacros::encodeConfig($entries, target: 'bulk'),
            ]);
        });

        static::$tableMacrosRegistered = true;
    }

    protected static function registerFlowforgeMacros(): void
    {
        if (static::$flowforgeMacrosRegistered) {
            return;
        }

        if (! class_exists('Relaticle\\Flowforge\\Board')) {
            return;
        }

        $boardClass = 'Relaticle\\Flowforge\\Board';

        $boardClass::macro('contextMenuCardActions', function (array $entries): object {
            $entries = RegisterMacros::normalizeFlowforgeCardEntries($entries);

            RegisterMacros::registerFlowforgeEntries($this, $entries);
            RegisterMacros::applyFlowforgeContextMenuAttributes($this, [
                'data-filament-right-click-flowforge-card-config' => RegisterMacros::encodeConfig($entries),
            ]);

            return $this;
        });

        static::$flowforgeMacrosRegistered = true;
    }

    /**
     * @param  array<ContextMenuEntry|Action>  $entries
     * @return array<ContextMenuEntry>
     */
    public static function normalizeEntries(array $entries): array
    {
        return array_map(function (ContextMenuEntry|Action $entry): ContextMenuEntry {
            if ($entry instanceof ContextMenuEntry) {
                return $entry;
            }

            if ($entry instanceof Action) {
                return ContextMenuItem::for($entry);
            }

            throw new InvalidArgumentException('Context menu entries must be Filament actions or context menu entry objects.');
        }, $entries);
    }

    /**
     * @param  array<ContextMenuEntry|Action>  $entries
     * @return array<ContextMenuEntry>
     */
    public static function normalizeRecordEntries(array $entries): array
    {
        $entries = static::normalizeEntries($entries);

        static::assertEntriesUseTarget($entries, 'record', 'contextMenuActions() only accepts record actions. Use contextMenuBulkActions() for BulkAction instances.');
        static::assertEntriesUseActionType($entries, Action::class, 'contextMenuActions() only accepts record actions. Use contextMenuBulkActions() for BulkAction instances.', rejectBulkActions: true);

        return $entries;
    }

    /**
     * @param  array<ContextMenuEntry|Action>  $entries
     * @return array<ContextMenuEntry>
     */
    public static function normalizeBulkEntries(array $entries): array
    {
        $entries = static::normalizeEntries($entries);

        static::assertEntriesUseTarget($entries, 'bulk', 'contextMenuBulkActions() only accepts Filament BulkAction instances.');
        static::assertEntriesUseActionType($entries, BulkAction::class, 'contextMenuBulkActions() only accepts Filament BulkAction instances.');

        return $entries;
    }

    /**
     * @param  array<ContextMenuEntry|Action>  $entries
     * @return array<ContextMenuEntry>
     */
    public static function normalizeFlowforgeCardEntries(array $entries): array
    {
        $entries = static::normalizeEntries($entries);

        static::assertEntriesUseTarget($entries, 'record', 'contextMenuCardActions() only accepts record actions.');
        static::assertEntriesUseActionType($entries, Action::class, 'contextMenuCardActions() only accepts record actions.', rejectBulkActions: true);

        return $entries;
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     * @return array<Action>
     */
    public static function getActions(array $entries): array
    {
        return collect($entries)
            ->flatMap(fn (ContextMenuEntry $entry): array => $entry->getActions())
            ->all();
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public static function encodeConfig(array $entries, string $target = 'record'): string
    {
        try {
            $json = json_encode([
                'version' => 1,
                'target' => $target,
                'items' => array_map(
                    fn (ContextMenuEntry $entry): array => $entry->toPayload(),
                    $entries,
                ),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Context menu configuration could not be encoded.', previous: $exception);
        }

        return base64_encode($json);
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public static function registerEntries(Table $table, array $entries): void
    {
        (function (array $actions): void {
            foreach ($actions as $action) {
                $action->table($this);
                $this->cacheAction($action);
            }
        })->call($table, static::getActions($entries));
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public static function registerFlowforgeEntries(object $board, array $entries): void
    {
        if (! method_exists($board, 'getLivewire')) {
            throw new InvalidArgumentException('Flowforge context menu actions can only be registered on a board with a Livewire component.');
        }

        $livewire = $board->getLivewire();

        (function (array $actions) use ($livewire): void {
            foreach ($actions as $action) {
                $action->livewire($livewire);
                $this->cacheAction($action);
            }
        })->call($livewire, static::getActions($entries));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function applyContextMenuAttributes(Table $table, array $attributes): Table
    {
        return $table->extraAttributes([
            'class' => 'fi-right-click-table',
            'data-filament-right-click-script-src' => FilamentAsset::getScriptSrc('filament-right-click', 'leek/filament-right-click'),
            'data-filament-right-click-style-href' => static::getStyleHref(),
            'x-init' => static::assetLoaderExpression(),
            ...$attributes,
        ], merge: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function applyFlowforgeContextMenuAttributes(object $board, array $attributes): void
    {
        static::flowforgeContextMenuAttributes()[$board] = [
            'class' => 'fi-right-click-flowforge-board',
            'data-filament-right-click-script-src' => FilamentAsset::getScriptSrc('filament-right-click', 'leek/filament-right-click'),
            'data-filament-right-click-style-href' => static::getStyleHref(),
            'x-init' => static::assetLoaderExpression(),
            ...$attributes,
        ];

        if (method_exists($board, 'getView') && method_exists($board, 'view') && $board->getView() === 'flowforge::index') {
            $board->view('filament-right-click::flowforge.index');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getFlowforgeContextMenuAttributes(object $board): array
    {
        return static::flowforgeContextMenuAttributes()[$board] ?? [];
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    protected static function assertEntriesUseTarget(array $entries, string $target, string $message): void
    {
        foreach ($entries as $entry) {
            static::assertPayloadUsesTarget($entry->toPayload(), $target, $message);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertPayloadUsesTarget(array $payload, string $target, string $message): void
    {
        if (($payload['type'] ?? null) === 'item' && (($payload['target'] ?? 'record') !== $target)) {
            throw new InvalidArgumentException($message);
        }

        if (($payload['type'] ?? null) !== 'section') {
            return;
        }

        foreach ($payload['items'] ?? [] as $item) {
            if (is_array($item)) {
                static::assertPayloadUsesTarget($item, $target, $message);
            }
        }
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     * @param  class-string<Action>  $actionClass
     */
    protected static function assertEntriesUseActionType(array $entries, string $actionClass, string $message, bool $rejectBulkActions = false): void
    {
        foreach (static::getActions($entries) as $action) {
            if ($rejectBulkActions && $action instanceof BulkAction) {
                throw new InvalidArgumentException($message);
            }

            if (! $action instanceof $actionClass) {
                throw new InvalidArgumentException($message);
            }
        }
    }

    public static function assetLoaderExpression(): HtmlString
    {
        return new HtmlString(<<<'JS'
            (() => {
                const root = $el
                const styleHref = root.dataset.filamentRightClickStyleHref
                const scriptSrc = root.dataset.filamentRightClickScriptSrc

                if (styleHref && ! document.querySelector('link[data-filament-right-click-style]')) {
                    const link = document.createElement('link')
                    link.rel = 'stylesheet'
                    link.href = styleHref
                    link.dataset.filamentRightClickStyle = 'true'
                    document.head.appendChild(link)
                }

                if (window.FilamentRightClick) {
                    window.FilamentRightClick.init(root)

                    return
                }

                if (! scriptSrc || document.querySelector('script[data-filament-right-click-script]')) {
                    return
                }

                const script = document.createElement('script')
                script.src = scriptSrc
                script.dataset.filamentRightClickScript = 'true'
                script.addEventListener('load', () => window.FilamentRightClick?.init(root), { once: true })
                document.head.appendChild(script)
            })()
            JS);
    }

    public static function getStyleHref(): string
    {
        $style = collect(FilamentAsset::getStyles(['leek/filament-right-click']))
            ->first(fn ($style): bool => $style->getId() === 'filament-right-click');

        if (! $style) {
            throw new InvalidArgumentException('Context menu stylesheet asset has not been registered.');
        }

        return $style->getHref();
    }

    /**
     * @return WeakMap<object, array<string, mixed>>
     */
    protected static function flowforgeContextMenuAttributes(): WeakMap
    {
        return static::$flowforgeContextMenuAttributes ??= new WeakMap;
    }
}
