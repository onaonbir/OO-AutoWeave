<?php

namespace OnaOnbir\OOAutoWeave\Core\NodeHandler;

abstract class BaseNodeHandler implements NodeHandlerInterface
{
    abstract public function handle(array $node, \OnaOnbir\OOAutoWeave\Core\ContextManager $manager): NodeHandlerResult;

    public static function definition(): array
    {
        return [
            'type' => '',           // override etmeyi unutma
            'attributes' => [],      // örn: ['icon' => 'log']
        ];
    }
}
