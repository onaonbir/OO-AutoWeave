<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultEdges;

use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\EdgeHandler\EdgeTypeInterface;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

class ConditionalEdge implements EdgeTypeInterface
{
    public function shouldPass(FlowRun $run, array $edge): bool
    {
        $condition = $edge['condition'] ?? null;

        if (! is_array($condition) || ! isset($condition['type'])) {
            return false;
        }

        $context = (new ContextManager($run))->all();

        $key = $condition['key'] ?? null;
        $expected = $condition['value'] ?? null;
        $actual = data_get($context, $key);

        return match ($condition['type']) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => !in_array($actual, (array) $expected),
            'greater_than' => $actual > $expected,
            'less_than' => $actual < $expected,
            default => false,
        };
    }
}
