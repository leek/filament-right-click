<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Contracts;

use Filament\Actions\Action;

interface ContextMenuEntry
{
    /**
     * @return array<Action>
     */
    public function getActions(): array;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
