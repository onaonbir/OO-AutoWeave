<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class WaitNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $seconds = $node['attributes']['seconds'] ?? 1;

        sleep($seconds);

        return NodeHandlerResult::success([
            'status' => 'wait_completed',
            'waited_seconds' => $seconds,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public static function definition(): array
    {
        return [
            'type' => 'wait',
            'attributes' => [
                'seconds' => 1,
                '__options__' => [
                    'label' => 'Bekleme Adımı',
                    'description' => 'Belirtilen süre boyunca akışı duraklatır.',
                    'form_fields' => [
                        [
                            'key' => 'seconds',
                            'label' => 'Bekleme Süresi (saniye)',
                            'type' => 'input.number',
                            'hint' => 'Bu adımda akışın kaç saniye bekleyeceğini belirtin.',
                            'required' => true,
                            'min' => 1,
                            'default' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }
}
