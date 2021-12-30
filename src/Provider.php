<?php

namespace Akaunting\Sortable;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class Provider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/sortable.php', 'sortable');
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/sortable.php' => config_path('sortable.php'),
        ], 'sortable');

        $this->registerBladeDirectives();
        $this->registerMacros();
    }

    public function registerBladeDirectives()
    {
        Blade::directive('sortablelink', function ($expression) {
            $expression = ($expression[0] === '(') ? substr($expression, 1, -1) : $expression;

            return "<?php echo \Akaunting\Sortable\Support\SortableLink::render(array ({$expression}));?>";
        });
    }

    public function registerMacros()
    {
        request()->macro('allFilled', function (array $keys) {
            foreach ($keys as $key) {
                if (! $this->filled($key)) {
                    return false;
                }
            }

            return true;
        });
    }
}
