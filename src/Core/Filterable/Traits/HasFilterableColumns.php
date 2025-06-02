<?php

namespace OnaOnbir\OOAutoWeave\Core\Filterable\Traits;

use LogicException;

trait HasFilterableColumns
{
    public static function filterableColumns(int $deepLevel = 0, int $currentLevel = 0): array
    {
        if (! method_exists(static::class, 'defineFilterableColumns')) {
            throw new LogicException(static::class . ' must implement defineFilterableColumns() when using HasFilterableColumns trait.');
        }

        return static::defineFilterableColumns($deepLevel, $currentLevel);
    }
}
