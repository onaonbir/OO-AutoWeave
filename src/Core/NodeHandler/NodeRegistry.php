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
            $options = $attributes['__options__'] ?? [];

        } else {
            $options = [];
        }

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

        $mergedAttributes = array_merge($defaults, $attributes);

        return [
            'key' => $key,
            'type' => $type,
            'attributes' => $mergedAttributes,
            'auto_tick' => $autoTick,
            'auto_progress' => $autoProgress,
        ];
    }
}
