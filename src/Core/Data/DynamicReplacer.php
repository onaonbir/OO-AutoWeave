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
            // Fonksiyon ifadeleri: @@json_encode(...)@@, @@implode(...)@@
            $template = preg_replace_callback('/@@(\w+)\((.*?)(?:,\s*(\{.*\}))?\)@@/', function ($matches) use ($context) {
                $function = $matches[1];
                $inner = trim($matches[2]);
                $options = isset($matches[3]) ? json_decode($matches[3], true) : [];

                $resolved = self::replace($inner, $context);

                return self::applyFunction($function, $resolved, $options);
            }, $template);

            // Eğer sadece {{...}} içeriyorsa → tek ifade gibi dön
            if (preg_match('/^\{\{(.*?)\}\}$/', $template, $singleMatch)) {
                return self::resolveRaw(trim($singleMatch[1]), $context);
            }

            // Karmaşık string içinde değişken varsa → string olarak dön
            return preg_replace_callback('/\{\{(.*?)\}\}/', function ($matches) use ($context) {
                $resolved = self::resolveRaw(trim($matches[1]), $context);

                return is_array($resolved) ? json_encode($resolved) : $resolved;
            }, $template);
        }

        return $template;
    }

    protected static function resolveRaw(string $key, array $context): mixed
    {
        if (str_contains($key, '*')) {
            return self::extractWildcardValues($context, explode('.', $key));
        }

        return Arr::get($context, $key);
    }

    protected static function applyFunction(string $function, mixed $value, array $options = []): mixed
    {
        return match ($function) {
            'json_encode' => json_encode($value),
            'implode' => is_array($value) ? implode($options['separator'] ?? ',', $value) : (string) $value,
            'custom_function' => self::customFunctionExample($value, $options),
            default => $value,
        };
    }

    protected static function customFunctionExample(mixed $value, array $options = []): string
    {
        $prefix = $options['prefix'] ?? '';
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $prefix.$v, $value));
        }

        return $prefix.$value;
    }

    protected static function extractWildcardValues(array $context, array $keys): array
    {
        $results = [];

        // 1. Flat dot notation key'lerde eşleşme varsa
        foreach ($context as $flatKey => $flatValue) {
            if (! is_string($flatKey)) {
                continue;
            }

            $pattern = str_replace('\*', '\d+', preg_quote(implode('.', $keys)));
            if (preg_match('/^'.$pattern.'$/', $flatKey)) {
                $results[] = $flatValue;
            }
        }

        // 2. Nested lookup fallback
        return array_merge($results, self::resolveFromNestedArray($context, $keys));
    }

    protected static function resolveFromNestedArray(array $context, array $keys): array
    {
        $results = [];
        $currentKey = array_shift($keys);

        if ($currentKey === '*') {
            if (! is_array($context)) {
                return [];
            }
            foreach ($context as $item) {
                $results = array_merge($results, self::resolveFromNestedArray($item, $keys));
            }

            return $results;
        }

        if (! isset($context[$currentKey])) {
            return [];
        }

        if (empty($keys)) {
            return [$context[$currentKey]];
        }

        return self::resolveFromNestedArray($context[$currentKey], $keys);
    }
}
