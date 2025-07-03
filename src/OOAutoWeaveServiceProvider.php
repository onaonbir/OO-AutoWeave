<?php

namespace OnaOnbir\OOAutoWeave;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeRegistry;

class OOAutoWeaveServiceProvider extends ServiceProvider
{
    private string $packageName = 'oo-auto-weave';

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->bootNodesFrom(__DIR__.'/Core/DefaultNodes', 'OnaOnbir\\OOAutoWeave\\Core\\DefaultNodes');
    }

    protected function bootNodesFrom(string $directory, string $baseNamespace): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $relativePath = trim(Str::replaceLast('.php', '', $file->getRelativePathname()), '/\\');
            $class = $baseNamespace.'\\'.str_replace(['/', '\\'], '\\', $relativePath);

            if (
                class_exists($class)
                && is_subclass_of($class, BaseNodeHandler::class)
                && method_exists($class, 'definition')
            ) {
                $definition = $class::definition();
                $type = $definition['type'] ?? class_basename($class);
                NodeRegistry::register($type, $class);
            }
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/'.$this->packageName.'.php',
            $this->packageName
        );

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], $this->packageName.'-migrations');

        $this->publishes([
            __DIR__.'/../config/'.$this->packageName.'.php' => config_path($this->packageName.'.php'),
        ], $this->packageName.'-config');

        $this->registerConfiguredEventListeners();
    }

    protected function registerConfiguredEventListeners(): void
    {
        $listeners = config($this->packageName.'.event_listeners', []);
        foreach ($listeners as $event => $handlers) {
            foreach ((array) $handlers as $handler) {
                Event::listen($event, $handler);
            }
        }
    }
}
