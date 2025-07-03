<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Models\FlowRun;

abstract class BaseEdgeType implements EdgeInterface
{
    abstract public function shouldPass(FlowRun $run, array $edge): bool;

    public static function definition(): array
    {
        return [
            'type' => '',           // override edilmeli
            'attributes' => [],     // örn: ['icon' => 'switch']
        ];
    }
}
