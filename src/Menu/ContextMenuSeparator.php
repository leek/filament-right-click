<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Menu;

use Leek\FilamentRightClick\Contracts\ContextMenuEntry;

class ContextMenuSeparator implements ContextMenuEntry
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getActions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function toPayload(): array
    {
        return ['type' => 'separator'];
    }
}
