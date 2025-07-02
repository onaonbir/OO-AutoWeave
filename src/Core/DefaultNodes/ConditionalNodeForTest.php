<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use Illuminate\Support\Arr;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class ConditionalNodeForTest extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $conditions = $node['attributes']['conditions'] ?? [];
        $logic = strtolower($node['attributes']['logic'] ?? 'and');

        if (empty($conditions) || ! in_array($logic, ['and', 'or'])) {
            return NodeHandlerResult::error([], [], 'Koşullar veya mantık geçersiz');
        }

        $context = $manager->all();

        $results = [];

        foreach ($conditions as $condition) {
            $path = $condition['path'] ?? null;
            $expected = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? '=';

            if (! $path) {
                $results[] = false;

                continue;
            }

            $actual = Arr::get($context, $path);

            $result = match ($operator) {
                '=', '==' => $actual == $expected,
                '===' => $actual === $expected,
                '!=' => $actual != $expected,
                '!==' => $actual !== $expected,
                '>' => $actual > $expected,
                '>=' => $actual >= $expected,
                '<' => $actual < $expected,
                '<=' => $actual <= $expected,
                default => false,
            };

            $results[] = $result;
        }

        $matched = $logic === 'and'
            ? ! in_array(false, $results, true)
            : in_array(true, $results, true);

        $trueNode = $node['attributes']['true_node'] ?? 'node_approved';
        $falseNode = $node['attributes']['false_node'] ?? 'node_rejected';
        $nextNode = $matched ? $trueNode : $falseNode;

        return NodeHandlerResult::success(
            resultContext: [
                'message' => $matched ? 'Tüm koşullar sağlandı' : 'Koşullardan biri sağlanamadı',
                'condition_matched' => $matched,
                'logic' => $logic,
                'results' => $results,
                'evaluated' => $conditions,
            ],
            overrides: [
                'next_node_key' => $nextNode,
            ]
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'conditional',
            'attributes' => ['icon' => 'filter'],
        ];
    }
}
