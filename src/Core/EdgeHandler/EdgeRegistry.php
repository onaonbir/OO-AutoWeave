<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;

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

            $executor = function (array $edge, ContextManager $manager) use ($instance) {
                return $instance->shouldPass($edge, $manager);
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

    public static function run(string $type, array $edge, ContextManager $manager): bool
    {
        $definition = static::$map[$type] ?? null;

        if (! $definition) {
            return false;
        }

        $executor = $definition['executor'] ?? null;

        if (! is_callable($executor)) {
            return false;
        }

        return $executor($edge, $manager);
    }

    public static function all(): array
    {
        return static::$map;
    }

    public static function makeEdge(
        string $type,
        string $key,
        string $from,
        string $to,
        array $attributes = []
    ): array {
        $definition = static::$map[$type] ?? null;

        if (! $definition) {
            throw new \InvalidArgumentException("Tanımlı bir edge tipi bulunamadı: {$type}");
        }

        $defaults = $definition['attributes'] ?? [];
        $options = $defaults['__options__'] ?? [];

        $cleanDefaults = collect($defaults)
            ->reject(fn ($_, $key) => str_starts_with($key, '__'))
            ->toArray();

        $mergedAttributes = array_merge($cleanDefaults, $attributes);

        return [
            'type' => $type,
            'key' => $key,
            'connection' => [
                'from' => $from,
                'to' => $to,
            ],

            'attributes' => array_merge($mergedAttributes, ['__options__' => $options]),
        ];
    }
}
