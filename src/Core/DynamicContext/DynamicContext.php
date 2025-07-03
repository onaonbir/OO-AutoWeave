<?php

namespace OnaOnbir\OOAutoWeave\Core\DynamicContext;

use Illuminate\Support\Arr;

class DynamicContext
{
    public static function replace(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            return array_map(function ($item) use ($context) {
                return self::replace($item, $context);
            }, $template);
        }

        if (!is_string($template)) {
            return $template;
        }

        $placeholders = config('oo-auto-weave.placeholders');
        $varRegex = self::buildVariableRegex($placeholders['variable']);
        $varExactRegex = self::buildVariableExactRegex($placeholders['variable']);

        // Eğer template sadece bir değişkense ve array dönerse direkt döndür
        if (preg_match($varExactRegex, $template, $singleMatch)) {
            $resolved = self::resolveRaw(trim($singleMatch[1]), $context);
            if (is_array($resolved)) {
                return $resolved;
            }
        }

        // Karmaşık template içindeki tüm değişkenleri çöz
        $template = preg_replace_callback($varRegex, function ($matches) use ($context, $varExactRegex) {
            $resolved = self::resolveRaw(trim($matches[1]), $context);
            return is_array($resolved) ? json_encode($resolved, JSON_UNESCAPED_UNICODE) : $resolved;
        }, $template);

        // Eğer template tamamen tek değişkense, tekrar kontrol et
        if (preg_match($varExactRegex, $template, $singleMatch)) {
            return self::resolveRaw(trim($singleMatch[1]), $context);
        }

        // Eğer template tamamı JSON array ise, onu döndür
        if (self::isJsonString($template)) {
            $decoded = json_decode($template, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $template;
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
        if (Arr::has($context, $key)) {
            return Arr::get($context, $key);
        }

        if (str_contains($key, '*')) {
            return self::resolveWildcardGroup($key, $context);
        }

        $undottedContext = Arr::undot($context);
        if (Arr::has($undottedContext, $key)) {
            return Arr::get($undottedContext, $key);
        }

        return null;
    }

    protected static function resolveWildcardGroup(string $wildcardPath, array $context): array
    {
        $resolvedValues = [];

        $escapedPath = preg_quote($wildcardPath, '/');
        $regexPattern = '/^' . str_replace('\*', '[^.]+', $escapedPath) . '$/';

        foreach ($context as $flatKey => $value) {
            if (preg_match($regexPattern, $flatKey)) {
                $resolvedValues[] = $value;
            }
        }

        return $resolvedValues;
    }

    protected static function isJsonString(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        $trimmed = trim($str);
        if (!in_array($trimmed[0], ['[', '{'])) {
            return false;
        }

        json_decode($trimmed);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
