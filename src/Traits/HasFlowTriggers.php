<?php

namespace OnaOnbir\OOAutoWeave\Traits;

use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Models\Flow;

trait HasFlowTriggers
{
    protected static function bootHasFlowTriggers()
    {
        static::created(fn ($model) => static::triggerFlows($model, 'created'));
        static::updated(fn ($model) => static::triggerFlows($model, 'updated'));
        static::deleted(fn ($model) => static::triggerFlows($model, 'deleted'));
    }

    protected static function triggerFlows($model, string $event)
    {
        $flows = Flow::where('is_active', true)
            ->whereJsonContains('structure->nodes', [['type' => 'model_trigger']])
            ->get()->filter(function ($flow) use ($event, $model) {
                foreach ($flow->structure['nodes'] ?? [] as $node) {
                    if (
                        $node['type'] === 'model_trigger' &&
                        ($node['attributes']['model'] ?? null) === get_class($model) &&
                        ($node['attributes']['event'] ?? null) === $event
                    ) {
                        return true;
                    }
                }

                return false;
            });

        foreach ($flows as $flow) {
            try {
                app(\OnaOnbir\OOAutoWeave\Services\FlowRunService::class)->start(
                    $flow->key,
                    $model,
                    [
                        'model' => $model->toArray(),
                        'event' => $event,
                        'changes' => method_exists($model, 'getDirty') ? $model->getDirty() : [],
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("Flow trigger failed for flow {$flow->key}: ".$e->getMessage());
            }
        }
    }
}
