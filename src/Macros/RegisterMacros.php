<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Macros;

use Filament\Actions\Action;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use JsonException;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;
use Leek\FilamentRightClick\Menu\ContextMenuItem;

class RegisterMacros
{
    protected static bool $registered = false;

    public static function register(): void
    {
        if (static::$registered) {
            return;
        }

        Table::macro('contextMenuActions', function (array $entries): Table {
            /** @var Table $this */
            $entries = RegisterMacros::normalizeEntries($entries);

            foreach (RegisterMacros::getActions($entries) as $action) {
                $action->table($this);
                $this->cacheAction($action);
            }

            return $this->extraAttributes([
                'class' => 'fi-right-click-table',
                'data-filament-right-click-config' => RegisterMacros::encodeConfig($entries),
                'data-filament-right-click-script-src' => FilamentAsset::getScriptSrc('filament-right-click', 'leek/filament-right-click'),
                'data-filament-right-click-style-href' => RegisterMacros::getStyleHref(),
                'x-init' => RegisterMacros::assetLoaderExpression(),
            ], merge: true);
        });

        static::$registered = true;
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
    public static function encodeConfig(array $entries): string
    {
        try {
            $json = json_encode([
                'version' => 1,
                'target' => 'record',
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
}
