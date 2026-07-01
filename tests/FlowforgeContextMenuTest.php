<?php

use Filament\Actions\Action;
use Filament\Panel;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use Leek\FilamentRightClick\Macros\RegisterMacros;
use Livewire\Component;
use Relaticle\Flowforge\Board;

function defineFakeFlowforgeBoard(): void
{
    if (class_exists('Relaticle\\Flowforge\\Board')) {
        return;
    }

    eval(<<<'PHP'
    namespace Relaticle\Flowforge;

    class Board extends \Filament\Support\Components\ViewComponent
    {
        protected string $view = 'flowforge::index';

        protected string $viewIdentifier = 'board';

        protected string $evaluationIdentifier = 'board';

        public function __construct(protected object $livewire) {}

        public static function make(object $livewire): static
        {
            return new static($livewire);
        }

        public function getLivewire(): object
        {
            return $this->livewire;
        }
    }
    PHP);
}

function resetRightClickMacroRegistration(): void
{
    $registered = new ReflectionProperty(RegisterMacros::class, 'registered');
    $registered->setAccessible(true);
    $registered->setValue(false);

    $flowforgeContextMenuAttributes = new ReflectionProperty(RegisterMacros::class, 'flowforgeContextMenuAttributes');
    $flowforgeContextMenuAttributes->setAccessible(true);
    $flowforgeContextMenuAttributes->setValue(null);
}

it('registers optional Flowforge card context menu actions', function (): void {
    defineFakeFlowforgeBoard();
    resetRightClickMacroRegistration();

    FilamentRightClickPlugin::make()->register(Panel::make());

    $livewire = new class extends Component
    {
        /** @var array<int, string> */
        public array $cachedActionNames = [];

        public function render(): string
        {
            return '';
        }

        protected function cacheAction(Action $action): void
        {
            $this->cachedActionNames[] = $action->getName();
        }
    };

    $board = Board::make($livewire)
        ->contextMenuCardActions([
            Action::make('view')->label('View card'),
        ]);

    $attributes = RegisterMacros::getFlowforgeContextMenuAttributes($board);
    $payload = json_decode(base64_decode($attributes['data-filament-right-click-flowforge-card-config']), associative: true);

    expect($livewire->cachedActionNames)->toBe(['view']);
    expect($board->getView())->toBe('filament-right-click::flowforge.index');
    expect($attributes['class'])->toBe('fi-right-click-flowforge-board');
    expect($attributes['data-filament-right-click-script-src'])->toContain('filament-right-click.js');
    expect($attributes['data-filament-right-click-style-href'])->toContain('filament-right-click.css');
    expect($attributes['x-init']->toHtml())->toContain('FilamentRightClick');
    expect($payload['items'][0]['action'])->toBe('view');
    expect($payload['items'][0]['label'])->toBe('View card');
});
