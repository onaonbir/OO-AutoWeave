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
            'attributes' => [
                'model' => null,
                'event' => 'created',
                '__options__' => [
                    'label' => 'Model Olayı Tetikleyici',
                    'description' => 'Belirli bir model olayında akışı başlatır (örneğin: created, updated, deleted).',
                    'form_fields' => [
                        [
                            'key' => 'model',
                            'label' => 'Model',
                            'type' => 'input.text',
                            'required' => true,
                            'hint' => 'Tam sınıf adı: App\\Models\\User gibi',
                        ],
                        [
                            'key' => 'event',
                            'label' => 'Olay Türü',
                            'type' => 'select',
                            'required' => true,
                            'default' => 'created',
                            'options' => [
                                ['label' => 'Oluşturuldu (created)', 'value' => 'created'],
                                ['label' => 'Güncellendi (updated)', 'value' => 'updated'],
                                ['label' => 'Silindi (deleted)', 'value' => 'deleted'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
