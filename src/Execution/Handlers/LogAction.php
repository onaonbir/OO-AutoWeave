<?php

namespace OnaOnbir\OOAutoWeave\Execution\Handlers;

use OnaOnbir\OOAutoWeave\Core\Contracts\ActionInterface;
use OnaOnbir\OOAutoWeave\Core\Support\Logger;

class LogAction implements ActionInterface
{
    public function execute(array $parameters, array $context = []): void
    {
        $title = $parameters['title'] ?? 'No Title';
        $message = $parameters['message'] ?? 'No Message';

        Logger::info('LogAction executed', [
            'title' => $title,
            'message' => $message,
            'context' => $context,
        ], 'LogAction');
    }
}
