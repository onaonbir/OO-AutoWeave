<?php

namespace OnaOnbir\OOAutoWeave;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use OnaOnbir\OOAutoWeave\Console\Commands\MakeEdgeCommand;
use OnaOnbir\OOAutoWeave\Console\Commands\MakeNodeCommand;
use OnaOnbir\OOAutoWeave\Core\DefaultEdges\ConditionalEdge;
use OnaOnbir\OOAutoWeave\Core\DefaultEdges\DefaultEdge;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\EdgeRegistry;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeRegistry;

class OOAutoWeaveServiceProvider extends ServiceProvider
{
    private string $packageName = 'oo-auto-weave';

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // DEFAULT KLASÖRLER
        $this->bootNodesFrom(__DIR__.'/Core/DefaultNodes', 'OnaOnbir\\OOAutoWeave\\Core\\DefaultNodes');
        $this->bootEdgesFrom(__DIR__.'/Core/DefaultEdges', 'OnaOnbir\\OOAutoWeave\\Core\\DefaultEdges');

        // KULLANICI KLASÖRLERİ
        $this->bootNodesFromConfig();
        $this->bootEdgesFromConfig();
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

    protected function bootEdgesFrom(string $directory, string $baseNamespace): void
    {
        $files = \Illuminate\Support\Facades\File::allFiles($directory);

        foreach ($files as $file) {
            $relativePath = trim(Str::replaceLast('.php', '', $file->getRelativePathname()), '/\\');
            $class = $baseNamespace.'\\'.str_replace(['/', '\\'], '\\', $relativePath);

            if (
                class_exists($class)
                && is_subclass_of($class, BaseEdgeType::class)
                && method_exists($class, 'definition')
            ) {
                $definition = $class::definition();
                $type = $definition['type'] ?? class_basename($class);
                EdgeRegistry::register($type, $class);
            }
        }
    }

    protected function bootNodesFromConfig(): void
    {
        $path = config('oo-auto-weave.nodes.path');
        $namespace = config('oo-auto-weave.nodes.namespace');

        if ($path && $namespace && is_dir($path)) {
            $this->bootNodesFrom($path, $namespace);
        }
    }

    protected function bootEdgesFromConfig(): void
    {
        $path = config('oo-auto-weave.edges.path');
        $namespace = config('oo-auto-weave.edges.namespace');

        if ($path && $namespace && is_dir($path)) {
            $this->bootEdgesFrom($path, $namespace);
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

        $this->commands([
            MakeEdgeCommand::class,
            MakeNodeCommand::class,
        ]);

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
