<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;

class ConditionalEdge extends BaseEdgeType
{
    public function shouldPass(array $edge, ContextManager $manager): bool
    {
        $condition = $edge['condition'] ?? [];

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
            'type' => 'conditional',
            'attributes' => ['icon' => 'filter'],
        ];
    }
}
