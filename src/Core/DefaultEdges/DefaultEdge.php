<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\EdgeHandler\EdgeTypeInterface;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

class DefaultEdge implements EdgeTypeInterface
{
    public function shouldPass(FlowRun $run, array $edge): bool
    {
        return true;
    }
}
