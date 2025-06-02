<?php

namespace OnaOnbir\OOAutoWeave\Core\Contracts;

use OnaOnbir\OOAutoWeave\Core\DTO\TriggerHandlerResult;
use OnaOnbir\OOAutoWeave\Models\Trigger;

interface TriggerHandlerInterface
{
    public function handle(Trigger $trigger, array $context): TriggerHandlerResult;
}
