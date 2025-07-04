<?php

namespace OnaOnbir\OOAutoWeave\Core\DynamicContext;

use Illuminate\Support\Arr;

class ModelExtractor
{
    public static function extract(mixed $model, array $filterableColumns, bool $undot = false): array
    {
        $data = self::processColumns($model, $filterableColumns);

        return $undot ? Arr::undot($data) : $data;
    }

    protected static function processColumns($model, array $columnTypes, string $prefix = ''): array
    {
        $results = [];

        foreach ($columnTypes as $column) {
            if (! isset($column['columnType'], $column['columnName'], $column['columnKey'])) {
                continue;
            }

            $fullKey = $prefix ? "{$prefix}.{$column['columnKey']}" : $column['columnKey'];

            if ($column['columnType'] == 'enum') {
                $enumValue = $model->{$column['columnName']};
                $results[$fullKey] = is_object($enumValue)
                    ? $enumValue->value
                    : $enumValue;
            }

            if (in_array($column['columnType'], ['text', 'datetime'])) {
                $results[$fullKey] = $model->{$column['columnName']};
            }

            if ($column['columnType'] === 'json') {
                $jsonData = $model->{$column['columnName']};
                $decoded = is_array($jsonData) ? $jsonData : (json_decode($jsonData, true) ?? []);

                $flattened = self::dotFlatten($decoded, $fullKey);
                $results = array_merge($results, $flattened);

            }

            if (str_starts_with($column['columnType'], 'relation_')) {
                $relationData = $model->{$column['columnName']};

                if (in_array($column['columnType'], ['relation_belongsTo', 'relation_hasOne']) && $relationData) {
                    $results = array_merge(
                        $results,
                        self::processColumns($relationData, $column['inner'] ?? [], $fullKey)
                    );
                }

                if ($column['columnType'] === 'relation_hasMany' && $relationData) {
                    foreach ($relationData as $index => $relatedItem) {
                        $indexedPrefix = "{$fullKey}.{$index}";
                        $results = array_merge(
                            $results,
                            self::processColumns($relatedItem, $column['inner'] ?? [], $indexedPrefix)
                        );
                    }
                }
            }
        }

        return $results;
    }

    protected static function dotFlatten(array $array, string $prefix = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $dotKey = $prefix.'.'.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $results += self::dotFlatten($value, $dotKey);
            } else {
                $results[$dotKey] = $value;
            }
        }

        return $results;
    }
}
