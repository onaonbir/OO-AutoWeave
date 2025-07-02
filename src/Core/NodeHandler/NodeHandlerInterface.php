<?php

namespace OnaOnbir\OOAutoWeave\Core\NodeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;

interface NodeHandlerInterface
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult;
}
