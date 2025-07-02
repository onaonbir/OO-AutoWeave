<?php

namespace OnaOnbir\OOAutoWeave\Core\NodeHandler;

class NodeHandlerResult
{
    public bool $success;

    public array $resultContext;

    public array $overrides;

    public ?string $message;

    private function __construct(
        bool $success,
        array $resultContext = [],
        array $overrides = [],
        ?string $message = null,
    ) {
        $this->success = $success;
        $this->resultContext = $resultContext;
        $this->overrides = $overrides;
        $this->message = $message;
    }

    public static function success(array $resultContext = [], array $overrides = [], ?string $message = null): self
    {
        return new self(true, $resultContext, $overrides, $message);
    }

    public static function error(array $resultContext = [], array $overrides = [], ?string $message = null): self
    {
        return new self(false, $resultContext, $overrides, $message);
    }
}
