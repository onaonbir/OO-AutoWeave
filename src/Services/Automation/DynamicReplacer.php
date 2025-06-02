<?php

namespace OnaOnbir\OOAutoWeave\Services\Automation;

use Illuminate\Support\Arr;

class DynamicReplacer
{
    public static function replace(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            return array_map(fn ($item) => self::replace($item, $context), $template);
        }

        if (is_string($template)) {
            return preg_replace_callback('/\{\{(.*?)\}\}/', function ($matches) use ($context) {
                $key = trim($matches[1]);

                // Eğer wildcard içeriyorsa
                if (str_contains($key, '*')) {
                    return self::extractWildcardValues($context, explode('.', $key));
                }

                // Normal birebir değer çekme
                $value = Arr::get($context, $key);

                return is_array($value) ? json_encode($value) : ($value ?? '');
            }, $template);
        }

        return $template;
    }

    protected static function extractWildcardValues(array $context, array $keys, string $currentPath = '', array &$collected = []): string
    {
        $currentKey = array_shift($keys);

        if ($currentKey === '*') {
            // Şu anki pathte kaç tane eleman var?
            $prefix = rtrim($currentPath, '.');

            $subItems = array_filter(array_keys($context), fn ($k) => str_starts_with($k, $prefix));
            $indexGroups = [];

            foreach ($subItems as $subKey) {
                $remaining = str_replace($prefix.'.', '', $subKey);
                $parts = explode('.', $remaining);
                if (is_numeric($parts[0])) {
                    $indexGroups[$parts[0]] = true;
                }
            }

            foreach (array_keys($indexGroups) as $index) {
                self::extractWildcardValues($context, array_merge([$index], $keys), $currentPath, $collected);
            }

            return implode(',', array_filter($collected)); // Burada toplananları birleştiriyoruz
        }

        $currentPath = $currentPath ? "{$currentPath}.{$currentKey}" : $currentKey;

        if (empty($keys)) {
            $value = Arr::get($context, $currentPath);
            if (is_array($value)) {
                $collected[] = json_encode($value);
            } else {
                $collected[] = $value;
            }
        } else {
            self::extractWildcardValues($context, $keys, $currentPath, $collected);
        }

        return implode(',', array_filter($collected));
    }
}
