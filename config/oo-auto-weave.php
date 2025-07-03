<?php

return [
    'nodes' => [
        'path' => base_path('app/AutoWeave/Nodes'),
        'namespace' => 'App\\AutoWeave\\Nodes',
    ],
    'edges' => [
        'path' => base_path('app/AutoWeave/Edges'),
        'namespace' => 'App\\AutoWeave\\Edges',
    ],
    'placeholders' => [
        'variable' => ['start' => '{{', 'end' => '}}'],
        'function' => ['start' => '@@', 'end' => '@@'],
    ],
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
