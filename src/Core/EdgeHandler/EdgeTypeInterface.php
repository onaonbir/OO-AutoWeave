<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Models\FlowRun;

interface EdgeTypeInterface
{
    public function shouldPass(FlowRun $run, array $edge): bool;
}
