<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OnaOnbir\OOAutoWeave\Models\AutomationAction;

if (! function_exists('test_func')) {
    function test_func()
    {
        return 'HEHE TEST';
    }
}

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

if (! function_exists('cloneAutomationActionWithExecutions')) {
    function cloneAutomationActionWithExecutions(int $actionId, array $overrides = []): AutomationAction
    {
        return DB::transaction(function () use ($actionId, $overrides) {
            $original = AutomationAction::with('executions')->findOrFail($actionId);

            // Yeni action kopyalanıyor
            $cloned = $original->replicate([
                'id', 'created_at', 'updated_at',
            ]);

            // Varsayılan olarak isme "(Kopya)" ekle
            $cloned->name = $overrides['name'] ?? $original->name.' (Kopya)';

            // Diğer override alanları uygula
            foreach ($overrides as $key => $value) {
                if ($key !== 'name') {
                    $cloned->{$key} = $value;
                }
            }

            $cloned->push();

            // Tüm executions'ları klonla
            foreach ($original->executions as $execution) {
                $newExecution = $execution->replicate([
                    'id', 'created_at', 'updated_at', 'order',
                ]);
                $newExecution->automation_action_id = $cloned->id;
                $newExecution->save();
            }

            return $cloned;
        });
    }
}
