<?php

// app/Jobs/DispatchTriggerForUpdatedModel.php

namespace OnaOnbir\OOAutoWeave\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OnaOnbir\OOAutoWeave\Core\Registry\TriggerRegistry;
use OnaOnbir\OOAutoWeave\Models\Trigger;
use OnaOnbir\OOAutoWeave\Core\Support\Logger;

class DispatchTriggerForUpdatedModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $modelClass;
    public int|string $modelId;
    public array $changedAttributes;
    public array $originalAttributes;

    public function __construct(string $modelClass, int|string $modelId, array $changed, array $original)
    {
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->changedAttributes = $changed;
        $this->originalAttributes = $original;
    }

    public function handle(): void
    {
        $source = 'OnUpdated Job';

        $model = (new $this->modelClass())->find($this->modelId);
        if (! $model) {
            Logger::warning("Model not found for updated event job", [
                'model' => $this->modelClass,
                'id' => $this->modelId,
            ], $source);
            return;
        }

        $triggers = Trigger::query()
            ->active()
            ->where('group', 'model')
            ->where('type', 'record_updated')
            ->where('settings->model', $this->modelClass)
            ->get();

        foreach ($triggers as $trigger) {
            try {
                $context = [
                    'model' => $model,
                    'model_id' => $this->modelId,
                    'attributes' => [
                        'changedAttributes' => $this->changedAttributes,
                        'originalAttributes' => $this->originalAttributes,
                    ],
                    'source' => 'model.updated',
                ];

                Logger::info('Running trigger', [
                    'trigger_id' => $trigger->id,
                    'trigger_key' => $trigger->key,
                    'trigger_group' => $trigger->group,
                    'trigger_type' => $trigger->type,
                    'model' => $this->modelClass,
                    'changes' => $this->changedAttributes,
                ], $source);

                TriggerRegistry::execute($trigger, $context);

                Logger::info('Trigger executed successfully', [
                    'trigger_id' => $trigger->id,
                ], $source);
            } catch (\Throwable $e) {
                Logger::error("Automation error: " . $e->getMessage(), [
                    'trigger_id' => $trigger->id,
                    'exception' => $e,
                ], $source);
            }
        }
    }
}

