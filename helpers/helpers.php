<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OnaOnbir\OOWeaveReplace\Filterable\Contracts\FilterableColumnsProviderInterface;

if (! function_exists('hexToRgba')) {

    function hexToRgba($hex, $opacity = 0.5)
    {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba($r, $g, $b, $opacity)";
    }
}

if (! function_exists('oo_wa_automation_get_eligible_models')) {
    function oo_wa_automation_get_eligible_models(): array
    {
        $modelsPath = app_path('Models');
        $modelsNamespace = 'App\\Models\\';
        $files = File::allFiles($modelsPath);

        $eligibleModels = [];

        foreach ($files as $file) {
            $className = $modelsNamespace . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($className)) {
                continue;
            }

            if (! is_subclass_of($className, FilterableColumnsProviderInterface::class)) {
                continue;
            }

            $eligibleModels[] = [
                'label' => class_basename($className),
                'value' => $className,
            ];
        }

        return $eligibleModels;
    }
}
