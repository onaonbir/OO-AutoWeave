<?php

namespace OnaOnbir\OOAutoWeave\Core\DynamicContext;

interface FilterableColumnsProviderInterface
{
    public static function filterableColumns(int $deepLevel = 0, int $currentLevel = 0): array;
}
