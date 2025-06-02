<?php

namespace OnaOnbir\OOAutoWeave\Core\Registry;

use Closure;
use OnaOnbir\OOAutoWeave\Core\Contracts\TriggerHandlerInterface;
use OnaOnbir\OOAutoWeave\Core\DTO\TriggerHandlerResult;
use OnaOnbir\OOAutoWeave\Core\Execution\ExecutionResolver;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class TriggerRegistry
{
    protected static array $map = [];

    public static function register(string $key, string $group, string $type, mixed $handler, array $options = []): void
    {
        if (is_string($handler) && class_exists($handler) && is_subclass_of($handler, TriggerHandlerInterface::class)) {
            $instance = app($handler);

            $handler = function (Trigger $trigger, array $context = []) use ($instance) {
                return $instance->handle($trigger, $context);
            };
        }

        $compositeKey = static::buildKey($key, $group, $type);

        static::$map[$compositeKey] = [
            'key' => $key,
            'group' => $group,
            'type' => $type,
            'handler' => $handler,
            'options' => $options,
        ];
    }

    public static function execute(Trigger $trigger, array $context = []): void
    {
        $compositeKey = static::buildKey($trigger->key, $trigger->group, $trigger->type);

        $handler = static::$map[$compositeKey]['handler'] ?? null;

        if (is_callable($handler)) {
            $result = $handler($trigger, $context);

            if ($result instanceof TriggerHandlerResult && $result->shouldExecute) {
                ExecutionResolver::runTrigger($result->trigger ?? $trigger, $result->context ?: $context);
            }
        }
    }

    public static function get(string $key, string $group, string $type): ?Closure
    {
        return static::$map[static::buildKey($key, $group, $type)]['handler'] ?? null;
    }

    public static function getOption(string $key, string $group, string $type, string $optionKey, mixed $default = null): mixed
    {
        return static::$map[static::buildKey($key, $group, $type)]['options'][$optionKey] ?? $default;
    }

    public static function getType(string $key, string $group, string $type): ?string
    {
        return static::$map[static::buildKey($key, $group, $type)]['type'] ?? null;
    }

    public static function all(): array
    {
        return static::$map;
    }

    public static function registeredTriggerKeys(): array
    {
        return array_keys(static::$map);
    }

    public static function registeredTriggerTypesForHumans(): array
    {
        return collect(static::$map)
            ->mapWithKeys(fn ($item, $key) => [
                $key => $item['options']['label'] ?? "{$item['key']} ({$item['group']}/{$item['type']})",
            ])
            ->toArray();
    }

    public static function groupedByGroupAndType(): array
    {
        return collect(static::$map)
            ->groupBy(fn ($item) => "{$item['group']}/{$item['type']}")
            ->map(fn ($items) => collect($items)->mapWithKeys(fn ($item) => [
                static::buildKey($item['key'], $item['group'], $item['type']) => $item['options']['label'] ?? $item['key'],
            ]))
            ->toArray();
    }

    public static function buildKey(string $key, string $group, string $type): string
    {
        return "{$key}::{$group}::{$type}";
    }

    public static function parseKey(string $compositeKey): array
    {
        return explode('::', $compositeKey);
    }
}
