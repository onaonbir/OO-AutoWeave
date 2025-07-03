<?php

namespace OnaOnbir\OOAutoWeave\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeEdgeCommand extends Command
{
    protected $signature = 'oo-auto-weave:make-edge {name}';

    protected $description = 'Create a custom AutoWeave edge type';

    public function handle(): void
    {
        $name = Str::studly($this->argument('name'));

        $config = config('oo-auto-weave.edges');
        $path = rtrim($config['path'] ?? '', '/');
        $namespace = rtrim($config['namespace'] ?? '', '\\');

        if (! $path || ! $namespace) {
            $this->error('Please configure `edges.path` and `edges.namespace` in `oo-auto-weave.php` config file.');

            return;
        }

        $filePath = "{$path}/{$name}.php";

        if (File::exists($filePath)) {
            $this->error("Edge class already exists: {$filePath}");

            return;
        }

        File::ensureDirectoryExists($path);

        $stubPath = __DIR__.'/../../../stubs/edge.stub';

        if (! File::exists($stubPath)) {
            $this->error("Edge stub not found: {$stubPath}");

            return;
        }

        $stub = File::get($stubPath);

        $typeKebab = Str::kebab($name);

        $content = str_replace(
            ['{{ class }}', '{{ namespace }}', '{{ type }}'],
            [$name, $namespace, $typeKebab],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Edge class created at: {$filePath}");
    }
}
