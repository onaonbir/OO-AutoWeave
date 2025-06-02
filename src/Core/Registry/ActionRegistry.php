<?php

namespace OnaOnbir\OOAutoWeave\Core\Registry;

use OnaOnbir\OOAutoWeave\Core\Contracts\ActionInterface;

class ActionRegistry
{
    protected static array $map = [];

    public static function register(string $type, mixed $executor, array $options = []): void
    {
        if (is_string($executor) && class_exists($executor) && is_subclass_of($executor, ActionInterface::class)) {
            $instance = app($executor);

            $executor = function (array $parameters, array $context = []) use ($instance) {
                $instance->execute($parameters, $context);
            };
        }

        static::$map[$type] = [
            'executor' => $executor,
            'options' => $options,
        ];
    }

    public static function get(string $type): array
    {
        return static::$map[$type] ?? [];
    }

    public static function getOption(string $type, string $key, mixed $default = null): mixed
    {
        return static::$map[$type]['options'][$key] ?? $default;
    }

    public static function execute(string $type, array $parameters = [], array $context = []): void
    {
        if (isset(static::$map[$type]['executor']) && is_callable(static::$map[$type]['executor'])) {
            call_user_func(static::$map[$type]['executor'], $parameters, $context);
        }
    }

    public static function all(): array
    {
        return static::$map;
    }

    public static function registeredTypes(): array
    {
        return array_keys(static::$map);
    }
}
