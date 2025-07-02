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

            //TODO MAYBE ANOTHER LOVE ?
            unset($attributes['__options__']);
        } else {
            $options = [];
        }

        static::$map[$type] = [
            'executor' => $executor,
            'attributes' => $attributes,
            'options' => $options, // 🔥 burada saklıyoruz
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
}
