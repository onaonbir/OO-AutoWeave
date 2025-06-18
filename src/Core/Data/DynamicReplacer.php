<?php

namespace OnaOnbir\OOAutoWeave\Core\Data;

use Illuminate\Support\Arr;

class DynamicReplacer
{
    public static function replace(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            return array_map(fn ($item) => self::replace($item, $context), $template);
        }

        if (is_string($template)) {
            // 1. Fonksiyonlu ifadeleri yakala (@@json_encode(...)@@ gibi)
            $template = preg_replace_callback('/@@(\w+)\((.*?)\)@@/', function ($matches) use ($context) {
                $function = $matches[1];
                $inner = trim($matches[2]);

                // İçerideki {{...}} çözülmeli
                $resolved = self::replace($inner, $context);

                return self::applyFunction($function, $resolved);
            }, $template);

            // 2. Standart {{...}} ifadelerini çöz
            $template = preg_replace_callback('/\{\{(.*?)\}\}/', function ($matches) use ($context) {
                $key = trim($matches[1]);

                if (str_contains($key, '*')) {
                    $values = self::extractWildcardValues($context, explode('.', $key));
                    return implode(',', array_filter($values, fn ($v) => $v !== null));
                }

                $value = Arr::get($context, $key);
                return is_array($value) ? json_encode($value) : ($value ?? '');
            }, $template);

            return $template;
        }

        return $template;
    }

    protected static function applyFunction(string $function, mixed $value): mixed
    {
        return match ($function) {
            'json_encode' => json_encode(is_string($value) ? explode(',', $value) : $value),
            'count'       => is_array($value) ? count($value) : 0,
            default       => $value,
        };
    }

    protected static function extractWildcardValues(array $context, array $keys): array
    {
        $results = [];

        // 1. Flat key çözümlemesi (örneğin r_causer.r_managers.0.name)
        $wildcardPath = implode('.', $keys);
        $wildcardPattern = str_replace('\*', '[0-9]+', preg_quote($wildcardPath));
        $regex = '/^' . $wildcardPattern . '$/';

        foreach ($context as $flatKey => $value) {
            if (preg_match($regex, $flatKey)) {
                $results[] = $value;
            }
        }

        // 2. Nested fallback çözüm
        $resolvedFromNested = self::resolveFromNestedArray($context, $keys);
        return array_merge($results, $resolvedFromNested);
    }

    protected static function resolveFromNestedArray(array $context, array $keys): array
    {
        $results = [];

        $currentKey = array_shift($keys);

        if ($currentKey === '*') {
            if (!is_array($context)) {
                return [];
            }

            foreach ($context as $item) {
                $results = array_merge($results, self::resolveFromNestedArray($item, $keys));
            }

            return $results;
        }

        if (!isset($context[$currentKey])) {
            return [];
        }

        if (empty($keys)) {
            return [$context[$currentKey]];
        }

        return self::resolveFromNestedArray($context[$currentKey], $keys);
    }
}
