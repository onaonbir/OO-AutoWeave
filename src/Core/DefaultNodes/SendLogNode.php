<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class SendLogNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $message = $node['attributes']['message'] ?? 'Log entry';

        Log::info('[FlowRun Log] '.$message, [
            'node' => $node['key'] ?? null,
            'context' => $manager->getNodeContext($node['key'] ?? ''),
        ]);

        $manager->set('log_message', $message, 'temp');

        $getTempData = $manager->get('log_message', 'bulunamadı', null, 'temp');

        return NodeHandlerResult::success([
            'tempDataRead' => $getTempData,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public static function definition(): array
    {
        return [
            'type' => 'send_log',
            'attributes' => [
                'icon' => 'log',
                'label' => 'Send Log',
                'description' => 'Writes a log message to the Laravel logger',
            ],
        ];
    }
}
