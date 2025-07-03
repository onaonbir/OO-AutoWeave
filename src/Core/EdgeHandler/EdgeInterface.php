<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

interface EdgeInterface
{
    public function shouldPass(array $edge, ContextManager $manager): bool;

    public static function definition(): array;
}
