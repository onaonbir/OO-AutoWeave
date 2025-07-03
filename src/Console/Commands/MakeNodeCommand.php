<?php

namespace OnaOnbir\OOAutoWeave\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeNodeCommand extends Command
{
    protected $signature = 'oo-auto-weave:make-node {name}';

    protected $description = 'Create a custom AutoWeave node handler';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $config = config('oo-auto-weave.nodes');
        $path = rtrim($config['path'], '/');
        $namespace = rtrim($config['namespace'], '\\');

        $filePath = "{$path}/{$name}.php";

        if (File::exists($filePath)) {
            $this->error("Node class already exists: {$filePath}");

            return;
        }

        File::ensureDirectoryExists($path);

        $stub = File::get(__DIR__.'/../../../stubs/node.stub');

        $typeKebab = Str::kebab($name);

        $content = str_replace(
            ['{{ class }}', '{{ namespace }}', '{{ type }}'],
            [$name, $namespace, $typeKebab],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Node class created at: {$filePath}");
    }
}
