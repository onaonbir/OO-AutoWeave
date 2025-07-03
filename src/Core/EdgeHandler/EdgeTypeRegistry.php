<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

class EdgeTypeRegistry
{
    protected static array $types = [];

    public static function register(string $type, string $class): void
    {
        self::$types[$type] = $class;
    }

    public static function resolve(string $type): EdgeTypeInterface
    {
        if (! isset(self::$types[$type])) {
            throw new \InvalidArgumentException("Undefined edge type: {$type}");
        }

        return app(self::$types[$type]);
    }
}
