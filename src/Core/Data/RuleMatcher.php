<?php

namespace OnaOnbir\OOAutoWeave\Core\Data;

use Illuminate\Support\Arr;

class RuleMatcher
{
    public static function matches(array $rules, array $context): bool
    {
        $overallMatch = false;
        $isFirst = true;

        foreach ($rules as $rule) {
            $columnKey = $rule['columnKey'] ?? null;
            $operator = $rule['operator'] ?? null;
            $value = $rule['value'] ?? null;
            $type = $rule['type'] ?? 'and';

            if (! $columnKey || ! $operator) {
                continue;
            }

            $values = self::extractWildcardValues($context, explode('.', $columnKey));
            $matched = false;

            foreach ($values as $item) {
                if (self::evaluate($item, $operator, $value)) {
                    $matched = true;
                    break;
                }
            }

            if ($isFirst) {
                $overallMatch = $matched;
                $isFirst = false;
            } else {
                $overallMatch = ($type === 'and') ? ($overallMatch && $matched) : ($overallMatch || $matched);
            }
        }

        return $overallMatch;
    }

    protected static function evaluate($columnData, $operator, $value): bool
    {
        if (is_object($columnData) && enum_exists(get_class($columnData))) {
            $columnData = $columnData->value;
        }

        return match ($operator) {
            '=' => $columnData == $value,
            '!=' => $columnData != $value,
            '>' => $columnData > $value,
            '<' => $columnData < $value,
            '>=' => $columnData >= $value,
            '<=' => $columnData <= $value,
            'in' => in_array($columnData, (array) $value),
            'not_in' => ! in_array($columnData, (array) $value),
            default => false,
        };
    }

    protected static function extractWildcardValues(array $data, array $keys): array
    {
        $fullKey = implode('.', $keys); // <<< bak burası

        // Önce düz key var mı diye bakalım
        if (array_key_exists($fullKey, $data)) {
            return [$data[$fullKey]];
        }

        // Yoksa yine eski yöntem (Arr::get) ile deneyelim
        $currentKey = array_shift($keys);

        if ($currentKey === '*') {
            if (! is_array($data)) {
                return [];
            }

            $values = [];
            foreach ($data as $item) {
                $values = array_merge($values, self::extractWildcardValues((array) $item, $keys));
            }

            return $values;
        }

        $value = Arr::get($data, $currentKey);

        if (empty($keys)) {
            return [$value];
        }

        return self::extractWildcardValues((array) $value, $keys);
    }
}
