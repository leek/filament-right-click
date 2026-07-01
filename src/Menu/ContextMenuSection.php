<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Menu;

use Filament\Actions\Action;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;

class ContextMenuSection implements ContextMenuEntry
{
    protected ?string $label = null;

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
            'type' => 'section',
            'label' => $this->label,
            'items' => array_map(
                fn (ContextMenuEntry $entry): array => $entry->toPayload(),
                $this->entries,
            ),
        ], fn (mixed $value): bool => $value !== null);
    }
}
