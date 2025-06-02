<?php

namespace OnaOnbir\OOAutoWeave\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OnaOnbir\OOAutoWeave\Core\Registry\TriggerRegistry;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class DispatchTriggerExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Trigger $trigger;
    public array $context;

    public function __construct(Trigger $trigger, array $context = [])
    {
        $this->trigger = $trigger;
        $this->context = $context;
    }

    public function handle(): void
    {
        TriggerRegistry::execute($this->trigger, $this->context);
    }

    public static function dispatchForKey(string $triggerKey, array $context = [])
    {
        Trigger::query()
            ->active()
            ->where('key', $triggerKey)
            ->get()
            ->each(fn($trigger) => DispatchTriggerExecutionJob::dispatch($trigger, $context));
    }
}


