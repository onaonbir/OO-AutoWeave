<?php

namespace OnaOnbir\OOAutoWeave\Core\NodeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;

class NodeRegistry
{
    protected static array $map = [];

    public static function register(string $type, mixed $executor, array $attributes = []): void
    {
        if (
            is_string($executor)
            && class_exists($executor)
            && is_subclass_of($executor, NodeHandlerInterface::class)
            && method_exists($executor, 'definition')
        ) {
            $definition = $executor::definition();
            $instance = app($executor);

            $executor = function (array $node, ContextManager $manager) use ($instance) {
                return $instance->handle($node, $manager);
            };

            $type = $definition['type'] ?? $type;
            $attributes = $definition['attributes'] ?? $attributes;
        }

        // 🔥 HER ZAMAN attributes'tan options çek
        $options = $attributes['__options__'] ?? [];

        static::$map[$type] = [
            'executor' => $executor,
            'attributes' => $attributes,
            'options' => $options,
        ];
    }

    public static function run(string $type, array $node, ContextManager $manager): NodeHandlerResult
    {
        $definition = static::$map[$type] ?? null;

        if (! $definition) {
            return NodeHandlerResult::error(message: "Tanımlı node bulunamadı: {$type}");
        }

        $executor = $definition['executor'] ?? null;

        if (! is_callable($executor)) {
            return NodeHandlerResult::error(message: "Node executor çalıştırılamıyor: {$type}");
        }

        $node['__registry_attributes'] = $definition['attributes'] ?? [];

        return $executor($node, $manager);
    }

    public static function all(): array
    {
        return static::$map;
    }

    public static function makeNode(
        string $type,
        string $key,
        array $attributes = [],
        bool $autoTick = true,
        bool $autoProgress = true
    ): array {
        $definition = static::$map[$type] ?? null;

        if (! $definition) {
            throw new \InvalidArgumentException("Tanımlı bir node tipi bulunamadı: {$type}");
        }

        $defaults = $definition['attributes'] ?? [];

        // __options__'u ayrı tut
        $options = $defaults['__options__'] ?? [];

        // __options__ dışındaki her şey default olarak alınabilir
        $cleanDefaults = collect($defaults)
            ->reject(fn ($_, $attrKey) => str_starts_with($attrKey, '__'))
            ->toArray();

        $mergedAttributes = array_merge($cleanDefaults, $attributes);

        return [
            'key' => $key,
            'type' => $type,
            'attributes' => array_merge($mergedAttributes, ['__options__' => $options]),
            'auto_tick' => $autoTick,
            'auto_progress' => $autoProgress,
        ];
    }
}
