<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;

class DefaultEdge extends BaseEdgeType
{
    public function shouldPass(array $edge, ContextManager $manager): bool
    {
        return true;
    }

    public static function definition(): array
    {
        return [
            'type' => 'default',
            'attributes' => [
                '__options__' => [
                    'label' => 'Varsayılan Bağlantı',
                    'description' => 'Dümdüz geçen bağlantı',
                    'form_fields' => [

                    ],
                ],
            ],
        ];
    }
}
