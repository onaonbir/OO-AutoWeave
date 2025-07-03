<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\BaseEdgeType;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\EdgeInterface;
use OnaOnbir\OOAutoWeave\Models\FlowRun;



class ConditionalEdge extends BaseEdgeType
{
    public function shouldPass(FlowRun $run, array $edge): bool
    {
        $condition = $edge['condition'] ?? [];
        $context = (new ContextManager($run))->all();

        $actual = data_get($context, $condition['key'] ?? '');
        $expected = $condition['value'] ?? null;

        return match ($condition['type'] ?? '') {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => !in_array($actual, (array) $expected),
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
