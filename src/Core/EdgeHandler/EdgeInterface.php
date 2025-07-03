<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Core\ContextManager;

interface EdgeInterface
{
    public function shouldPass(array $edge, ContextManager $manager): bool;

    public static function definition(): array;
}
