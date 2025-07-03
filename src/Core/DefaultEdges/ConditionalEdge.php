<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;

class ConditionalEdge extends BaseEdgeType
{
    public function shouldPass(array $edge, ContextManager $manager): bool
    {
        $condition = $edge['attributes']['condition'] ?? [];

        $actual = data_get($manager, $condition['key'] ?? '');
        $expected = $condition['value'] ?? null;

        return match ($condition['type'] ?? '') {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => ! in_array($actual, (array) $expected),
            default => false,
        };
    }

    public static function definition(): array
    {
        return [
            'type' => 'conditional_edge',
            'attributes' => [
                'operator' => 'equals',
                'value' => null,
                '__options__' => [
                    'label' => 'Koşullu Bağlantı',
                    'description' => 'Context üzerinde koşula göre edge geçişi.',
                    'form_fields' => [
                        [
                            'key' => 'operator',
                            'label' => 'Operatör',
                            'type' => 'select',
                            'options' => [
                                ['label' => 'Eşittir', 'value' => 'equals'],
                                ['label' => 'Değildir', 'value' => 'not_equals'],
                            ],
                            'required' => true,
                        ],
                        [
                            'key' => 'value',
                            'label' => 'Beklenen Değer',
                            'type' => 'input.text',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];
    }
}
