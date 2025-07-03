<?php

namespace OnaOnbir\OOAutoWeave\Core\EdgeHandler;

use OnaOnbir\OOAutoWeave\Models\FlowRun;

interface EdgeInterface
{
    public function shouldPass(FlowRun $run, array $edge): bool;

    public static function definition(): array;
}
