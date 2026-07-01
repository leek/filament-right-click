<?php

declare(strict_types=1);

namespace Leek\FilamentRightClick\Menu;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\IconSize;
use Filament\Support\View\ComponentAttributeBag;
use Illuminate\Contracts\Support\Htmlable;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;

use function Filament\Support\generate_icon_html;

class ContextMenuItem implements ContextMenuEntry
{
    protected ?string $label = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    protected ?string $color = null;

    protected string $target = 'record';

    public function __construct(protected Action $action) {}

    public static function for(Action $action): static
    {
        return app(static::class, ['action' => $action]);
    }

    public static function make(Action $action): static
    {
        return static::for($action);
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
            'target' => $this->target,
            'label' => $this->getLabel(),
            'icon' => $this->getIconHtml(),
            'color' => $this->color,
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function getLabel(): string
    {
        if (filled($this->label)) {
            return $this->label;
        }

        return (string) str($this->action->getName())->headline();
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
