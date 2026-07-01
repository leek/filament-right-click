<?php

use Filament\Actions\Action;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\View\ComponentAttributeBag;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use Leek\FilamentRightClick\Macros\RegisterMacros;
use Livewire\Component;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\SortableBoard;

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

    class SortableBoard extends Board {}
    PHP);
}

function resetRightClickMacroRegistration(): void
{
    $tableMacrosRegistered = new ReflectionProperty(RegisterMacros::class, 'tableMacrosRegistered');
    $tableMacrosRegistered->setAccessible(true);
    $tableMacrosRegistered->setValue(false);

    $flowforgeMacrosRegistered = new ReflectionProperty(RegisterMacros::class, 'flowforgeMacrosRegistered');
    $flowforgeMacrosRegistered->setAccessible(true);
    $flowforgeMacrosRegistered->setValue(false);

    $flowforgeContextMenuAttributes = new ReflectionProperty(RegisterMacros::class, 'flowforgeContextMenuAttributes');
    $flowforgeContextMenuAttributes->setAccessible(true);
    $flowforgeContextMenuAttributes->setValue(null);
}

it('can retry optional Flowforge macro registration after base macros are already registered', function (): void {
    resetRightClickMacroRegistration();

    RegisterMacros::register();
    defineFakeFlowforgeBoard();
    RegisterMacros::register();

    expect(Board::hasMacro('contextMenuCardActions'))->toBeTrue();
});

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

it('exposes Flowforge card context menu actions on custom Board subclasses', function (): void {
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

    $board = SortableBoard::make($livewire)
        ->contextMenuCardActions([
            Action::make('move')->label('Move card'),
        ]);

    $attributes = RegisterMacros::getFlowforgeContextMenuAttributes($board);
    $payload = json_decode(base64_decode($attributes['data-filament-right-click-flowforge-card-config']), associative: true);

    expect($livewire->cachedActionNames)->toBe(['move']);
    expect($payload['items'][0]['action'])->toBe('move');
});

it('wraps the resolved Flowforge board view instead of replacing it', function (): void {
    defineFakeFlowforgeBoard();
    resetRightClickMacroRegistration();

    FilamentRightClickPlugin::make()->register(Panel::make());

    $viewsPath = storage_path('framework/testing/flowforge-views');

    File::ensureDirectoryExists($viewsPath);
    File::put($viewsPath.'/index.blade.php', <<<'BLADE'
        <section data-custom-flowforge-override>
            Custom Flowforge Board
        </section>
        BLADE);

    View::addNamespace('flowforge', $viewsPath);

    $livewire = new class extends Component
    {
        public function render(): string
        {
            return '';
        }

        protected function cacheAction(Action $action): void {}
    };

    $board = Board::make($livewire)
        ->contextMenuCardActions([
            Action::make('view')->label('View card'),
        ]);

    $html = view($board->getView(), [
        'attributes' => new ComponentAttributeBag,
        'board' => $board,
        'columns' => [],
        'config' => [],
    ])->render();

    expect($html)->toContain('data-filament-right-click-flowforge-card-config')
        ->and($html)->toContain('data-custom-flowforge-override')
        ->and($html)->toContain('Custom Flowforge Board');
});
