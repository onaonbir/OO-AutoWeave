<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

class DefaultEdge extends BaseEdgeType
{
    public function shouldPass( array $edge, ContextManager $manager): bool
    {
        return true;
    }

    public static function definition(): array
    {
        return [
            'type' => 'default',
            'attributes' => ['icon' => 'arrow-right'],
        ];
    }
}
