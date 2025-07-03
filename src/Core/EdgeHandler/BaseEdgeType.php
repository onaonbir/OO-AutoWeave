<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;

abstract class BaseEdgeType implements EdgeInterface
{
    abstract public function shouldPass(array $edge, ContextManager $manager): bool;

    public static function definition(): array
    {
        return [
            'type' => '',           // override edilmeli
            'attributes' => [],     // örn: ['icon' => 'switch'],
        ];
    }
}
