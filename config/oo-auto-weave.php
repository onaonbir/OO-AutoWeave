<?php

return [

    'debug_logs' => env('OO_AUTO_WEAVE_DEBUG_LOGS', false),
    'worker_mode' => true,
    'placeholders' => [
        'variable' => ['start' => '{{', 'end' => '}}'],
        'function' => ['start' => '@@', 'end' => '@@'],
    ],
    'queue' => [
        'automation' => 'default',
    ],
    'tables' => [
        'automations' => 'oo_wa_automations',
        'triggers' => 'oo_wa_triggers',
        'action_sets' => 'oo_wa_action_sets',
        'actions' => 'oo_wa_actions',
    ],
    'models' => [
        'automation' => \OnaOnbir\OOAutoWeave\Models\Automation::class,
        'trigger' => \OnaOnbir\OOAutoWeave\Models\Trigger::class,
        'action_set' => \OnaOnbir\OOAutoWeave\Models\ActionSet::class,
        'action' => \OnaOnbir\OOAutoWeave\Models\Action::class,
    ],
    'event_listeners' => [
        \OnaOnbir\OOAutoWeave\Events\TriggerMatchedEvent::class => [

        ],
    ],
];
