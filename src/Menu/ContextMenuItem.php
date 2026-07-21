<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Menu;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\ComponentAttributeBag;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;
use Throwable;

use function Filament\Support\generate_icon_html;

class ContextMenuItem implements ContextMenuEntry
{
    protected ?string $label = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    protected ?string $color = null;

    protected ?string $target = null;

    public function __construct(protected Action $action) {}

    public static function for(Action $action): static
    {
        return app(static::class, ['action' => $action]);
    }

    public static function make(Action $action): static
    {
        return static::for($action);
    }

    public static function forBulkAction(BulkAction $action): static
    {
        return static::for($action)->bulk();
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

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function target(string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function record(): static
    {
        $this->target = 'record';

        return $this;
    }

    public function bulk(): static
    {
        $this->target = 'bulk';

        return $this;
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    /**
     * @return array<Action>
     */
    public function getActions(): array
    {
        return [$this->action];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'type' => 'item',
            'action' => $this->action->getName(),
            'target' => $this->getTarget(),
            'label' => $this->getLabel(),
            'icon' => $this->getIconHtml(),
            'color' => $this->getColor(),
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function getTarget(): string
    {
        if (filled($this->target)) {
            return $this->target;
        }

        return $this->action instanceof BulkAction ? 'bulk' : 'record';
    }

    protected function getLabel(): string
    {
        if (filled($this->label)) {
            return $this->label;
        }

        try {
            $label = $this->action->getLabel();

            if ($label instanceof Htmlable) {
                $label = trim(strip_tags($label->toHtml()));
            }

            if (is_string($label) && filled($label)) {
                return $label;
            }
        } catch (Throwable) {
        }

        return (string) str($this->action->getName())->headline();
    }

    protected function getColor(): ?string
    {
        if (filled($this->color)) {
            return $this->color;
        }

        try {
            $color = $this->action->getColor();
        } catch (Throwable) {
            return null;
        }

        return is_string($color) ? $color : null;
    }

    protected function getIcon(): string|BackedEnum|Htmlable|null
    {
        if (filled($this->icon)) {
            return $this->icon;
        }

        try {
            return $this->action->getIcon();
        } catch (Throwable) {
            return null;
        }
    }

    protected function getIconHtml(): ?string
    {
        $icon = $this->getIcon();

        if (blank($icon)) {
            return null;
        }

        return generate_icon_html(
            $icon,
            null,
            new ComponentAttributeBag(['class' => 'fi-right-click-menu-item-icon']),
            IconSize::Small,
        )?->toHtml();
    }
}
