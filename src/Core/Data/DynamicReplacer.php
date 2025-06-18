<?php

namespace OnaOnbir\OOAutoWeave\Core\Data;

use Illuminate\Support\Arr;
use OnaOnbir\OOAutoWeave\Core\Registry\FunctionRegistry;

class DynamicReplacer
{
    public static function replace(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            return array_map(fn ($item) => self::replace($item, $context), $template);
        }

        if (! is_string($template)) {
            return $template;
        }

        $placeholders = config('oo-auto-weave.placeholders');

        // Regexler
        $funcRegex = self::buildFunctionRegex($placeholders['function']);
        $varRegex = self::buildVariableRegex($placeholders['variable']);
        $varExactRegex = self::buildVariableExactRegex($placeholders['variable']);

        // Fonksiyon ifadeleri (örneğin: @@json_encode(...)@@)
        $template = preg_replace_callback($funcRegex, function ($matches) use ($context) {
            $function = $matches[1];
            $inner = trim($matches[2]);
            $options = isset($matches[3]) ? json_decode($matches[3], true) : [];

            $resolved = self::replace($inner, $context);

            return self::applyFunction($function, $resolved, $options);
        }, $template);

        // Eğer sadece {{...}} varsa → direkt değerini döndür
        if (preg_match($varExactRegex, $template, $singleMatch)) {
            return self::resolveRaw(trim($singleMatch[1]), $context);
        }

        // Karmaşık string içinde değişken varsa → string olarak döndür
        return preg_replace_callback($varRegex, function ($matches) use ($context) {
            $resolved = self::resolveRaw(trim($matches[1]), $context);

            return is_array($resolved) ? json_encode($resolved) : $resolved;
        }, $template);
    }

    protected static function buildFunctionRegex(array $config): string
    {
        $start = preg_quote($config['start'], '/');
        $end = preg_quote($config['end'], '/');

        return "/{$start}(\w+)\((.*?)(?:,\s*(\{.*\}))?\){$end}/";
    }

    protected static function buildVariableRegex(array $config): string
    {
        $start = preg_quote($config['start'], '/');
        $end = preg_quote($config['end'], '/');

        return "/{$start}(.*?){$end}/";
    }

    protected static function buildVariableExactRegex(array $config): string
    {
        $start = preg_quote($config['start'], '/');
        $end = preg_quote($config['end'], '/');

        return "/^{$start}(.*?){$end}$/";
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
        return FunctionRegistry::call($function, $value, $options);
    }

    protected static function extractWildcardValues(array $context, array $keys): array
    {
        $results = [];

        foreach ($context as $flatKey => $flatValue) {
            if (! is_string($flatKey)) {
                continue;
            }

            $pattern = str_replace('\*', '\d+', preg_quote(implode('.', $keys)));
            if (preg_match('/^'.$pattern.'$/', $flatKey)) {
                $results[] = $flatValue;
            }
        }

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
