<?php

namespace Livewire\Features\SupportScriptsAndAssets;

use Livewire\Livewire;
use Livewire\Drawer\Utils;
use Illuminate\Support\Facades\Route;

class BrowserTest extends \Tests\BrowserTestCase
{
    /** @test */
    public function can_evaluate_a_script_inside_a_component()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public $message = 'original';

            public function render() { return <<<'HTML'
            <div>
                <h1 dusk="foo"></h1>
                <h2 dusk="bar" x-text="$wire.message"></h2>
            </div>

            @script
            <script>
                document.querySelector('[dusk="foo"]').textContent = 'evaluated'
                $wire.message = 'changed'
            </script>
            @endscript
            HTML; }
        })
        ->waitForText('evaluated')
        ->assertSeeIn('@foo', 'evaluated')
        ->assertSeeIn('@bar', 'changed')
        ;
    }

    /** @test */
    public function can_register_an_alpine_component_inside_a_script_tag()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public $message = 'original';

            public function render() { return <<<'HTML'
            <div>
                <h1 dusk="foo" x-dusk-test x-init="console.log('init')"></h1>
            </div>

            @script
            <script>
                console.log('hi')
                Alpine.directive('dusk-test', (el) => {
                    el.textContent = 'evaluated'
                })
            </script>
            @endscript
            HTML; }
        })
        ->waitForText('evaluated')
        ->assertSeeIn('@foo', 'evaluated')
        ;
    }

    /** @test */
    public function multiple_scripts_can_be_evaluated()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public function render() { return <<<'HTML'
            <div>
                <h1 dusk="foo"></h1>
                <h2 dusk="bar"></h2>
            </div>

            @script
            <script>
                document.querySelector('[dusk="foo"]').textContent = 'evaluated-first'
            </script>
            @endscript
            @script
            <script>
                document.querySelector('[dusk="bar"]').textContent = 'evaluated-second'
            </script>
            @endscript
            HTML; }
        })
        ->waitForText('evaluated-first')
        ->assertSeeIn('@foo', 'evaluated-first')
        ->assertSeeIn('@bar', 'evaluated-second')
        ;
    }

    /** @test */
    public function scripts_can_be_added_conditionally()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public $show = false;

            public function render() { return <<<'HTML'
            <div>
                <button dusk="button" wire:click="$set('show', true)">refresh</button>
                <h1 dusk="foo" wire:ignore></h1>
            </div>

            @if($show)
                @script
                <script>
                    document.querySelector('[dusk="foo"]').textContent = 'evaluated-second'
                </script>
                @endscript
            @endif

            @script
            <script>
                document.querySelector('[dusk="foo"]').textContent = 'evaluated-first'
            </script>
            @endscript
            HTML; }
        })
        ->assertSeeIn('@foo', 'evaluated-first')
        ->waitForLivewire()->click('@button')
        ->assertSeeIn('@foo', 'evaluated-second')
        ;
    }

    /** @test */
    public function assets_can_be_loaded()
    {
        Route::get('/test.js', function () {
            return Utils::pretendResponseIsFile(__DIR__.'/test.js');
        });

        Livewire::visit(new class extends \Livewire\Component {
            public function render() { return <<<'HTML'
            <div>
                <h1 dusk="foo" wire:ignore></h1>
            </div>

            @assets
            <script src="/test.js" defer></script>
            @endassets
            HTML; }
        })
        ->assertSeeIn('@foo', 'evaluated')
        ;
    }

    /** @test */
    public function remote_assets_can_be_loaded()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public function render() { return <<<'HTML'
            <div>
                <input type="text" data-picker>

                <span dusk="output" x-text="'foo'"></span>
            </div>

            @assets
                <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js" defer></script>
                <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css">
            @endassets

            @script
                <script>
                    window.datePicker = new Pikaday({ field: $wire.$el.querySelector('[data-picker]') });
                </script>
            @endscript
            HTML; }
        })
        ->waitForTextIn('@output', 'foo')
        ->assertScript('!! window.datePicker')
        ;
    }

    /** @test */
    public function remote_assets_can_be_loaded_lazily()
    {
        Livewire::visit(new class extends \Livewire\Component {
            public $load = false;

            public function render() { return <<<'HTML'
            <div>
                <input type="text" data-picker>

                <button wire:click="$toggle('load')" dusk="button">Load assets</button>

                <span dusk="output" x-text="'foo'"></span>
            </div>

            @if ($load)
                @assets
                    <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js" defer></script>
                    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css">
                @endassets

                @script
                    <script>
                        window.datePicker = new Pikaday({ field: $wire.$el.querySelector('[data-picker]') });
                    </script>
                @endscript
            @endif
            HTML; }
        })
        ->waitForTextIn('@output', 'foo')
        ->waitForLivewire()->click('@button')
        ->waitUntil('!! window.datePicker === true')
        ;
    }

    /** @test */
    public function remote_assets_can_be_loaded_from_a_deferred_nested_component()
    {
        Livewire::visit([new class extends \Livewire\Component {
            public $load = false;

            public function render() { return <<<'HTML'
            <div>
                <button wire:click="$toggle('load')" dusk="button">Load assets</button>

                <span dusk="output" x-text="'foo'"></span>

                @if ($load)
                    <livewire:child />
                @endif
            </div>
            HTML; }
        },
        'child' => new class extends \Livewire\Component {
            public function render() { return <<<'HTML'
            <div>
                <input type="text" data-picker>
            </div>

            @assets
                <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js"></script>
                <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css">
            @endassets

            @script
                <script>
                    window.datePicker = new Pikaday({ field: $wire.$el.querySelector('[data-picker]') });
                </script>
            @endscript
            HTML; }
        },
        ])
        ->waitForTextIn('@output', 'foo')
        ->waitForLivewire()->click('@button')
        ->waitUntil('!! window.datePicker === true')
        ;
    }

    /** @test */
    public function remote_inline_scripts_can_be_loaded_from_a_deferred_nested_component()
    {
        Livewire::visit([new class extends \Livewire\Component {
            public $load = false;

            public function render() { return <<<'HTML'
            <div>
                <button wire:click="$toggle('load')" dusk="button">Load assets</button>

                <span dusk="output" x-text="'foo'"></span>

                @if ($load)
                    <livewire:child />
                @endif
            </div>
            HTML; }
        },
        'child' => new class extends \Livewire\Component {
            public function render() { return <<<'HTML'
            <div>
                <input type="text" data-picker>
            </div>

            @assets
                <script>
                    window.Pikaday = function (options) {
                        // ...

                        return this
                    }
                </script>
            @endassets

            @script
                <script>
                    window.datePicker = new Pikaday({ field: $wire.$el.querySelector('[data-picker]') });
                </script>
            @endscript
            HTML; }
        },
        ])
        ->waitForTextIn('@output', 'foo')
        ->waitForLivewire()->click('@button')
        ->waitUntil('!! window.datePicker === true')
        ;
    }
}
