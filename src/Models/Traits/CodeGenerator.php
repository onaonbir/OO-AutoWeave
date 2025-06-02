<?php

namespace OnaOnbir\OOAutoWeave\Models\Traits;

use Illuminate\Support\Str;

trait CodeGenerator
{
    public static function createUniqueCode($model, $value): string
    {
        $getClass = get_class($model);
        $slug = Str::slug($value);
        $count = $getClass::whereRaw("unique_code RLIKE '^{$slug}(-[0-9]+)?$'")->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }

    public static function createUniqueUuid($model): string
    {
        $getClass = get_class($model);
        $key = Str::uuid()->toString();
        $find = $getClass::where('uuid', $key)->first();

        if ($find) {
            return self::createUniqueUuid($model);
        } else {
            return $key;
        }
    }

    public static function createUniqueTextKey($model, $column, $length = 20, $prefix = '', $strUpper = false): string
    {
        $getClass = get_class($model);
        $key = self::generateRandomKey($length, $prefix, $strUpper);
        $find = $getClass::where($column, $key)->first();

        if ($find) {
            return self::createUniqueTextKey($model, $column, $length, $strUpper);
        } else {
            return $key;
        }
    }

    private static function generateRandomKey($length = 20, $prefix = '', $strUpper = false)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $key = '';

        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[rand(0, strlen($characters) - 1)];
        }

        if ($strUpper) {
            $key = strtoupper($prefix.$key);
        } else {
            $key = $prefix.$key;
        }

        return $key;
    }
}
