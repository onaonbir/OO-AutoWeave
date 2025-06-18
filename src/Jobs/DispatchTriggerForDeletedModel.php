<?php

namespace OnaOnbir\OOAutoWeave\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OnaOnbir\OOAutoWeave\Core\Registry\TriggerRegistry;
use OnaOnbir\OOAutoWeave\Core\Support\Logger;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class DispatchTriggerForDeletedModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $modelClass;

    public int|string $modelId;

    public array $attributes;

    public function __construct(string $modelClass, int|string $modelId, array $attributes)
    {
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->attributes = $attributes;

        $this->onQueue(config('oo-auto-weave.queue.automation',"default"));
    }

    public function handle(): void
    {
        $source = 'OnDeleted Job';

        $triggers = Trigger::query()
            ->active()
            ->where('group', 'model')
            ->where('type', 'record_deleted')
            ->where('settings->model', $this->modelClass)
            ->get();

        foreach ($triggers as $trigger) {
            try {
                $context = [
                    'model' => null, // Geri getirilemez
                    'model_id' => $this->modelId,
                    'attributes' => $this->attributes,
                    'source' => 'model.deleted',
                ];

                Logger::info('Running trigger', [
                    'trigger_id' => $trigger->id,
                    'trigger_key' => $trigger->key,
                    'trigger_group' => $trigger->group,
                    'trigger_type' => $trigger->type,
                    'model' => $this->modelClass,
                    'deleted_attributes' => $this->attributes,
                ], $source);

                TriggerRegistry::execute($trigger, $context);

                Logger::info('Trigger executed successfully', [
                    'trigger_id' => $trigger->id,
                ], $source);
            } catch (\Throwable $e) {
                Logger::error('Automation error: '.$e->getMessage(), [
                    'trigger_id' => $trigger->id,
                    'exception' => $e,
                ], $source);
            }
        }
    }
}
