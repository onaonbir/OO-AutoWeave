<?php

return [
    'tables' => [
        'flows' => 'oo_wa_flows',
        'flow_runs' => 'oo_wa_flow_runs',
    ],
    'models' => [
        'flow' => \OnaOnbir\OOAutoWeave\Models\Flow::class,
        'flow_run' => \OnaOnbir\OOAutoWeave\Models\FlowRun::class,
    ],
    'event_listeners' => [

    ],
];
