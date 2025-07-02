<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class ModelTriggerNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        return NodeHandlerResult::success([
            'status' => 'model_event_triggered',
            'event' => $manager->get('event', 'unknown', scope: 'global'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public static function definition(): array
    {
        return [
            'type' => 'model_trigger',
            'attributes' => ['icon' => 'bolt'],
        ];
    }
}
