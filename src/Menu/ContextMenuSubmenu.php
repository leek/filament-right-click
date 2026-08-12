<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Menu;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\ComponentAttributeBag;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;

use function Filament\Support\generate_icon_html;

/**
 * Nested flyout submenu. The parent row is not an action — it only opens a
 * child menu of {@see ContextMenuEntry} items (items, sections, separators,
 * or further submenus). Leaf actions still register via {@see getActions()}.
 */
class ContextMenuSubmenu implements ContextMenuEntry
{
    protected ?string $label = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public function __construct(protected array $entries = []) {}

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public static function make(array $entries = []): static
    {
        return app(static::class, ['entries' => $entries]);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  array<ContextMenuEntry>  $entries
     */
    public function items(array $entries): static
    {
        $this->entries = $entries;

        return $this;
    }

    /**
     * @return array<Action>
     */
    public function getActions(): array
    {
        return collect($this->entries)
            ->flatMap(fn (ContextMenuEntry $entry): array => $entry->getActions())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'type' => 'submenu',
            'label' => $this->label ?? 'More',
            'icon' => $this->getIconHtml(),
            'items' => array_map(
                fn (ContextMenuEntry $entry): array => $entry->toPayload(),
                $this->entries,
            ),
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function getIconHtml(): ?string
    {
        if (blank($this->icon)) {
            return null;
        }

        return generate_icon_html(
            $this->icon,
            null,
            new ComponentAttributeBag(['class' => 'fi-right-click-menu-item-icon']),
            IconSize::Small,
        )?->toHtml();
    }
}
