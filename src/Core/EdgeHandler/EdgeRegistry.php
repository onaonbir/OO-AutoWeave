<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;


use OnaOnbir\OOAutoWeave\Models\FlowRun;

class EdgeRegistry
{
    protected static array $map = [];

    public static function register(string $type, mixed $executor, array $attributes = []): void
    {
        if (
            is_string($executor)
            && class_exists($executor)
            && is_subclass_of($executor, EdgeInterface::class)
            && method_exists($executor, 'definition')
        ) {
            $definition = $executor::definition();
            $instance = app($executor);

            $executor = function (FlowRun $run, array $edge) use ($instance) {
                return $instance->shouldPass($run, $edge);
            };

            $type = $definition['type'] ?? $type;
            $attributes = $definition['attributes'] ?? $attributes;
        }

        $options = $attributes['__options__'] ?? [];

        static::$map[$type] = [
            'executor' => $executor,
            'attributes' => $attributes,
            'options' => $options,
        ];
    }

    public static function run(string $type, FlowRun $run, array $edge): bool
    {
        $definition = static::$map[$type] ?? null;

        if (! $definition) {
            return false;
        }

        $executor = $definition['executor'] ?? null;

        if (! is_callable($executor)) {
            return false;
        }

        return $executor($run, $edge);
    }

    public static function all(): array
    {
        return static::$map;
    }
}
