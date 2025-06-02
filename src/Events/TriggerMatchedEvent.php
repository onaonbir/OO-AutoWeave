<?php

namespace OnaOnbir\OOAutoWeave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class TriggerMatchedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Trigger $trigger,
        public array $context = []
    ) {}
}
