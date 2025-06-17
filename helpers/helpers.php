<?php

use Illuminate\Support\Str;

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

        $files = \Illuminate\Support\Facades\File::allFiles($modelsPath);

        $eligibleModels = [];

        foreach ($files as $file) {
            $contents = $file->getContents();
            if (Str::contains($contents, 'use HasAutomation')) {
                $className = $modelsNamespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $file->getRelativePathname()
                );
                if (class_exists($className)) {
                    $eligibleModels[] = [
                        'label' => class_basename($className),
                        'value' => $className,
                    ];
                }
            }
        }

        return $eligibleModels;
    }
}
