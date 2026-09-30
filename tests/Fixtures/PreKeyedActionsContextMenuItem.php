<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Tests\Fixtures;

use Leek\FilamentRightClick\Menu\ContextMenuItem;

class PreKeyedActionsContextMenuItem extends ContextMenuItem
{
    protected static function filamentKeysRenderedActions(): bool
    {
        return false;
    }
}
