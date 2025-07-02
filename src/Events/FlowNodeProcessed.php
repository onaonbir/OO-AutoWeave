<?php

namespace OnaOnbir\OOAutoWeave\Events;

use Illuminate\Queue\SerializesModels;
use OnaOnbir\OOAutoWeave\Models\FlowRun;

class FlowNodeProcessed
{
    use SerializesModels;

    // TODO CHANGE ?
    public function __construct(public FlowRun $run) {}
}
